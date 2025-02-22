# app/main.py
import asyncio
from concurrent.futures import ThreadPoolExecutor
from fastapi import FastAPI, Response, UploadFile, File
from fastapi.staticfiles import StaticFiles
from snmp_scanner import scan_snmp
from ssh_scanner import scan_ssh
from db_handler import save_results, get_results
from config import get_ip_range, MAX_WORKERS, logger, load_config_from_excel, IP_RANGES, SNMP_COMMUNITIES, SSH_CREDENTIALS
from datetime import datetime
from tqdm import tqdm
import uvicorn
import os
import pandas as pd

# Main app for static files
app = FastAPI()
app.mount("/static", StaticFiles(directory="static"), name="static")

# Sub-app for API endpoints under /api/
api_app = FastAPI()
app.mount("/api", api_app)

# Global state for scanning status
scan_state = {"running": False, "total_ips": 0, "completed_ips": 0, "results": []}

async def scan_device(ip, executor):
    loop = asyncio.get_event_loop()
    snmp_result = await loop.run_in_executor(executor, scan_snmp, ip)
    ssh_result = await loop.run_in_executor(executor, scan_ssh, ip)
    timestamp = datetime.now().strftime('%Y-%m-%d %H')
    return {**snmp_result, **ssh_result, 'timestamp': timestamp}

async def run_scan():
    if scan_state["running"]:
        logger.info("Scan already running, ignoring request")
        return {"message": "Scan already running"}
    scan_state["running"] = True
    scan_state["completed_ips"] = 0
    IP_RANGE = get_ip_range()
    scan_state["total_ips"] = len(IP_RANGE)
    scan_state["results"] = []
    
    logger.info(f"Scanning {scan_state['total_ips']} IPs with {MAX_WORKERS} workers...")
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        tasks = {asyncio.create_task(scan_device(ip, executor)): ip for ip in IP_RANGE}
        for task in tqdm(asyncio.as_completed(tasks), total=len(IP_RANGE), desc="Scanning IPs"):
            try:
                result = await task
                scan_state["results"].append(result)
                scan_state["completed_ips"] += 1
            except Exception as e:
                ip = tasks[task]
                logger.error(f"Task failed for {ip}: {e}")
    
    scan_state["results"] = save_results(scan_state["results"]).to_dict(orient='records')
    scan_state["running"] = False
    logger.info("Scanning complete.")
    return {"message": "Scan completed"}

def create_excel_template():
    template_path = 'static/template.xlsx'
    if not os.path.exists(template_path):
        template_data = {
            'IP_RANGE': ['172.16.1.0/24', '172.16.1.0/24', '172.16.1.0/24'],
            'SNMP_COMMUNITY': ['public', 'private', 'test'],
            'SSH_USERNAME': ['admin', 'user', 'guest'],
            'SSH_PASSWORD': ['admin123', 'password', 'guestpass']
        }
        df = pd.DataFrame(template_data)
        os.makedirs('static', exist_ok=True)
        df.to_excel(template_path, index=False)
        logger.info(f"Created Excel template at {template_path}")

@api_app.get("/status")
async def get_status():
    return {
        "running": scan_state["running"],
        "total_ips": scan_state["total_ips"],
        "completed_ips": scan_state["completed_ips"],
        "progress": (scan_state["completed_ips"] / scan_state["total_ips"] * 100) if scan_state["total_ips"] > 0 else 0,
        "results": scan_state["results"]
    }

@api_app.post("/upload-config")
async def upload_config(file: UploadFile = File(...)):
    logger.info("Received config upload request")
    file_path = f"/tmp/{file.filename}"
    with open(file_path, "wb") as f:
        f.write(await file.read())
    load_config_from_excel(file_path)
    os.remove(file_path)
    return await run_scan()

@api_app.post("/start-scan")
async def start_scan():
    logger.info("Received start-scan request")
    return await run_scan()

@api_app.get("/config")
async def get_config():
    return {
        "ip_ranges": IP_RANGES,
        "snmp_communities": SNMP_COMMUNITIES,
        "ssh_credentials": SSH_CREDENTIALS
    }

@api_app.get("/history")
async def get_history():
    return {"results": get_results()}

@app.on_event("startup")
async def startup_event():
    create_excel_template()

if __name__ == "__main__":
    uvicorn.run(app, host="10.10.10.99", port=8000)

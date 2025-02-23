import asyncio
from concurrent.futures import ThreadPoolExecutor
from fastapi import FastAPI, Depends, HTTPException, UploadFile, File, Response, BackgroundTasks
from fastapi.staticfiles import StaticFiles
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from auth import verify_token, create_token
from snmp_scanner import scan_snmp
from ssh_scanner import scan_ssh
from db_handler import save_results, get_results, execute_custom_sql
from config import get_ip_range, MAX_WORKERS, logger, load_config_from_excel, IP_RANGES, SNMP_COMMUNITIES, SSH_CREDENTIALS, USERS
from datetime import datetime
from tqdm import tqdm
import uvicorn
import os
import pandas as pd
import redis
import io
from pydantic import BaseModel
from typing import List, Dict, Optional

app = FastAPI()
app.mount("/static", StaticFiles(directory="static"), name="static")

api_app = FastAPI()
app.mount("/api", api_app)

redis_client = redis.Redis(host='10.10.10.250', port=6379, db=0, decode_responses=True)

oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/token")

async def get_current_user(token: str = Depends(oauth2_scheme)):
    credentials_exception = HTTPException(status_code=401, detail="Invalid or expired token")
    if not verify_token(token):
        raise credentials_exception
    return token

async def scan_device(ip, executor):
    loop = asyncio.get_event_loop()
    snmp_result = await loop.run_in_executor(executor, scan_snmp, ip)
    ssh_result = await loop.run_in_executor(executor, scan_ssh, ip)
    timestamp = datetime.now().strftime('%Y-%m-%d %H')
    return {**snmp_result, **ssh_result, 'timestamp': timestamp}

async def run_scan_background(background_tasks: BackgroundTasks):
    async def scan_task():
        if redis_client.get("scan_running") == "true":
            logger.info("Scan already running, ignoring request")
            return
        redis_client.set("scan_running", "true")
        redis_client.set("completed_ips", 0)
        IP_RANGE = get_ip_range()
        redis_client.set("total_ips", len(IP_RANGE))
        redis_client.delete("results")
        
        logger.info(f"Scanning {len(IP_RANGE)} IPs with {MAX_WORKERS} workers...")
        with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
            tasks = {asyncio.create_task(scan_device(ip, executor)): ip for ip in IP_RANGE}
            for task in tqdm(asyncio.as_completed(tasks), total=len(IP_RANGE), desc="Scanning IPs"):
                try:
                    result = await task
                    redis_client.rpush("results", str(result))
                    redis_client.incr("completed_ips")
                except Exception as e:
                    ip = tasks[task]
                    logger.error(f"Task failed for {ip}: {e}")
        
        saved_results = save_results([eval(r) for r in redis_client.lrange("results", 0, -1)])
        redis_client.set("scan_running", "false")
        logger.info("Scanning complete.")
    
    background_tasks.add_task(scan_task)
    return {"message": "Scan started in background"}

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

@api_app.post("/token")
async def login(form_data: OAuth2PasswordRequestForm = Depends()):
    username = form_data.username
    password = form_data.password
    if username not in USERS or USERS[username] != password:
        raise HTTPException(status_code=401, detail="Invalid username or password")
    token = create_token({"sub": username})
    return {"access_token": token, "token_type": "bearer"}

@api_app.get("/status", dependencies=[Depends(get_current_user)])
async def get_status():
    return {
        "running": redis_client.get("scan_running") == "true",
        "total_ips": int(redis_client.get("total_ips") or 0),
        "completed_ips": int(redis_client.get("completed_ips") or 0),
        "progress": (int(redis_client.get("completed_ips") or 0) / int(redis_client.get("total_ips") or 1) * 100),
        "results": [eval(r) for r in redis_client.lrange("results", 0, -1)]
    }

@api_app.post("/upload-config", dependencies=[Depends(get_current_user)])
async def upload_config(background_tasks: BackgroundTasks, file: UploadFile = File(...)):
    logger.info("Received config upload request")
    file_path = f"/tmp/{file.filename}"
    with open(file_path, "wb") as f:
        f.write(await file.read())
    load_config_from_excel(file_path)
    os.remove(file_path)
    return await run_scan_background(background_tasks)

@api_app.post("/start-scan", dependencies=[Depends(get_current_user)])
async def start_scan(background_tasks: BackgroundTasks):
    logger.info("Received start-scan request")
    return await run_scan_background(background_tasks)

@api_app.get("/config", dependencies=[Depends(get_current_user)])
async def get_config():
    return {
        "ip_ranges": IP_RANGES,
        "snmp_communities": SNMP_COMMUNITIES,
        "ssh_credentials": SSH_CREDENTIALS
    }

@api_app.get("/history", dependencies=[Depends(get_current_user)])
async def get_history():
    return {"results": get_results(), "columns": ["ip", "sysName", "vendor", "model", "snmp_status", "ssh_status", "ssh_user", "timestamp"]}

class ExportRequest(BaseModel):
    columns: List[str]
    data: Optional[List[Dict]] = None

@api_app.post("/export-history", dependencies=[Depends(get_current_user)])
async def export_history(request: ExportRequest):
    data = request.data if request.data is not None else get_results()
    df = pd.DataFrame(data)
    selected_cols = [col for col in request.columns if col in df.columns]
    if not selected_cols:
        raise HTTPException(status_code=400, detail="No valid columns selected")
    export_df = df[selected_cols]
    output = io.BytesIO()
    export_df.to_excel(output, index=False)
    output.seek(0)
    return Response(content=output.getvalue(), media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet", headers={"Content-Disposition": "attachment; filename=history_export.xlsx"})

@api_app.post("/custom-sql", dependencies=[Depends(get_current_user)])
async def custom_sql(query: str):
    try:
        results = execute_custom_sql(query)
        return {"results": results}
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"SQL error: {str(e)}")

@app.on_event("startup")
async def startup_event():
    create_excel_template()

if __name__ == "__main__":
    uvicorn.run(app, host="0.0.0.0", port=8000)

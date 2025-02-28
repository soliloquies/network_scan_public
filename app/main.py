import asyncio
import redis
from fastapi import FastAPI, Depends, HTTPException, UploadFile, File, Response, BackgroundTasks
from fastapi.staticfiles import StaticFiles
from fastapi.security import OAuth2PasswordBearer, OAuth2PasswordRequestForm
from snmp_scanner import scan_snmp
from ssh_scanner import scan_ssh
from config import get_ip_range, MAX_WORKERS, logger, load_config_from_excel, IP_RANGES, SNMP_COMMUNITIES, SSH_CREDENTIALS, USERS
from datetime import datetime
import uvicorn
import os
import pandas as pd
import io
from pydantic import BaseModel
from typing import List, Dict, Optional

# Placeholder dependencies (you need to implement these)
from db_handler import save_results, get_results, execute_custom_sql  # Implement database operations
from auth import verify_token, create_token  # Implement authentication logic

# Initialize FastAPI app and mount static files and API
app = FastAPI()
app.mount("/static", StaticFiles(directory="static"), name="static")
api_app = FastAPI()
app.mount("/api", api_app)

# Redis configuration
redis_host = '10.10.10.250'
current_ip = '10.10.10.202_whut'

class RedisWithPrefix:
    def __init__(self, host=redis_host, port=6379, db=0, decode_responses=True):
        self.client = redis.Redis(host=host, port=port, db=db, decode_responses=decode_responses)
        self.prefix = current_ip.replace('.', '_') + ':'

    def _add_prefix(self, key):
        return f"{self.prefix}{key}"

    def __getattr__(self, name):
        original_method = getattr(self.client, name)
        def wrapper(*args, **kwargs):
            if args and isinstance(args[0], str):
                modified_args = (self._add_prefix(args[0]),) + args[1:]
                return original_method(*modified_args, **kwargs)
            return original_method(*args, **kwargs)
        return wrapper

redis_client = RedisWithPrefix(host=redis_host, port=6379, db=0, decode_responses=True)

# OAuth2 authentication setup
oauth2_scheme = OAuth2PasswordBearer(tokenUrl="/api/token")

async def get_current_user(token: str = Depends(oauth2_scheme)):
    credentials_exception = HTTPException(status_code=401, detail="Invalid or expired token")
    if not verify_token(token):
        raise credentials_exception
    return token

# Device scanning with semaphore for concurrency control
async def scan_device(ip, sem: asyncio.Semaphore):
    async with sem:
        snmp_task = scan_snmp(ip)
        ssh_task = scan_ssh(ip)
        snmp_result, ssh_result = await asyncio.gather(snmp_task, ssh_task)
        timestamp = datetime.now().strftime('%Y-%m-%d %H')
        return {**snmp_result, **ssh_result, 'timestamp': timestamp}

# Background scanning task with batch processing and concurrency limit
async def run_scan_background(background_tasks: BackgroundTasks):
    async def scan_task():
        if redis_client.get("scan_running") == "true":
            logger.info("Scan already running, ignoring request")
            return
        redis_client.set("scan_running", "true")
        redis_client.set("completed_ips", 0)
        ip_range = get_ip_range()
        redis_client.set("total_ips", len(ip_range))
        redis_client.delete("results")

        logger.info(f"Scanning {len(ip_range)} IPs...")
        batch_size = 800  # Process 512 IPs per batch
        sem = asyncio.Semaphore(400)  # Limit to 200 concurrent tasks

        for i in range(0, len(ip_range), batch_size):
            batch = ip_range[i:i + batch_size]
            tasks = [scan_device(ip, sem) for ip in batch]
            results = await asyncio.gather(*tasks)
            for result in results:
                redis_client.rpush("results", str(result))
                redis_client.incr("completed_ips")
            save_results(results)  # Save to database
            logger.info(f"Completed batch {i // batch_size + 1} of {(len(ip_range) + batch_size - 1) // batch_size}")
        redis_client.set("scan_running", "false")
        logger.info("Scanning complete.")

    background_tasks.add_task(scan_task)
    return {"message": "Scan started in background"}

# API Endpoints

## Login and token generation
@api_app.post("/token")
async def login(form_data: OAuth2PasswordRequestForm = Depends()):
    username = form_data.username
    password = form_data.password
    if username not in USERS or USERS[username] != password:
        raise HTTPException(status_code=401, detail="Invalid username or password")
    token = create_token({"sub": username})
    return {"access_token": token, "token_type": "bearer"}

## Get scanning status
# @api_app.get("/status", dependencies=[Depends(get_current_user)])
# async def get_status():
#    return {
#        "running": redis_client.get("scan_running") == "true",
#        "total_ips": int(redis_client.get("total_ips") or 0),
#        "completed_ips": int(redis_client.get("completed_ips") or 0),
#        "progress": (int(redis_client.get("completed_ips") or 0) / int(redis_client.get("total_ips") or 1) * 100),
#        "results": [eval(r) for r in redis_client.lrange("results", 0, -1)]
#    }

@api_app.get("/status", dependencies=[Depends(get_current_user)])
async def get_status():
    return {
        "running": redis_client.get("scan_running") == "true",
        "total_ips": int(redis_client.get("total_ips") or 0),
        "completed_ips": int(redis_client.get("completed_ips") or 0),
        "progress": (int(redis_client.get("completed_ips") or 0) / int(redis_client.get("total_ips") or 1) * 100),
        "results": [eval(r) for r in redis_client.lrange("results", -20, -1)]
    }



## Upload configuration and trigger scan
@api_app.post("/upload-config", dependencies=[Depends(get_current_user)])
async def upload_config(background_tasks: BackgroundTasks, file: UploadFile = File(...)):
    logger.info("Received config upload request")
    file_path = f"/tmp/{file.filename}"
    with open(file_path, "wb") as f:
        f.write(await file.read())
    load_config_from_excel(file_path)
    os.remove(file_path)
    return await run_scan_background(background_tasks)

## Start a new scan
@api_app.post("/start-scan", dependencies=[Depends(get_current_user)])
async def start_scan(background_tasks: BackgroundTasks):
    logger.info("Received start-scan request")
    return await run_scan_background(background_tasks)

## Get current configuration
@api_app.get("/config", dependencies=[Depends(get_current_user)])
async def get_config():
    return {
        "ip_ranges": IP_RANGES,
        "snmp_communities": SNMP_COMMUNITIES,
        "ssh_credentials": SSH_CREDENTIALS
    }

## Get scan history (limited to 20 recent records)
@api_app.get("/history", dependencies=[Depends(get_current_user)])
async def get_history():
    query = "SELECT * FROM devices where snmp_status like '%s%' ORDER BY timestamp DESC LIMIT 10"
    results = execute_custom_sql(query)
    return {
        "results": results,
        "columns": ["ip", "sysName", "vendor", "model", "snmp_status", "ssh_status", "ssh_user", "timestamp"]
    }

## Custom SQL query endpoint
@api_app.post("/custom-sql", dependencies=[Depends(get_current_user)])
async def custom_sql(query: str):
    try:
        results = execute_custom_sql(query)
        return {"results": results}
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"SQL error: {str(e)}")

## Export history to Excel
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
    return Response(
        content=output.getvalue(),
        media_type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet",
        headers={"Content-Disposition": "attachment; filename=history_export.xlsx"}
    )

# Startup event to ensure directories exist
@app.on_event("startup")
async def startup_event():
    os.makedirs('static', exist_ok=True)
    os.makedirs('logs', exist_ok=True)

# Run the application
if __name__ == "__main__":
    uvicorn.run(app, host="10.10.10.202", port=8000)

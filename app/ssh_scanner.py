# app/ssh_scanner.py
import paramiko
from config import SSH_PORT, SSH_CREDENTIALS, SSH_TIMEOUT, SSH_RETRY_DELAY, logger
import time
import threading

def scan_ssh(ip):
    result = {'ssh_status': 'Failed', 'ssh_user': None}
    ssh_client = paramiko.SSHClient()
    ssh_client.set_missing_host_key_policy(paramiko.AutoAddPolicy())
    
    def timeout_handler():
        try:
            ssh_client.close()
        except:
            pass
    
    for i, cred in enumerate(SSH_CREDENTIALS):
        timer = None
        try:
            timer = threading.Timer(SSH_TIMEOUT, timeout_handler)
            timer.start()
            ssh_client.connect(
                ip,
                port=SSH_PORT,
                username=cred['username'],
                password=cred['password'],
                timeout=SSH_TIMEOUT
            )
            timer.cancel()
            result['ssh_status'] = f'Success (user: {cred["username"]})'
            result['ssh_user'] = cred['username']
            logger.info(f"SSH success for {ip} with {cred['username']}")
            ssh_client.close()
            return result
        
        except Exception as e:
            logger.warning(f"SSH failed for {ip} with {cred['username']}: {e}")
            if i < len(SSH_CREDENTIALS) - 1:
                time.sleep(SSH_RETRY_DELAY)
        finally:
            if timer:
                timer.cancel()
            ssh_client.close()
    
    return result

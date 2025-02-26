# app/config.py
import logging
import ipaddress
import pandas as pd

logger = logging.getLogger(__name__)
logger.setLevel(logging.DEBUG)

file_handler = logging.FileHandler('logs/scanner.log')
file_handler.setLevel(logging.INFO)
file_formatter = logging.Formatter('%(asctime)s - %(levelname)s - %(message)s')
file_handler.setFormatter(file_formatter)

console_handler = logging.StreamHandler()
console_handler.setLevel(logging.INFO)
console_formatter = logging.Formatter('%(levelname)s - %(message)s')
console_handler.setFormatter(console_formatter)

def info_only(record):
    return record.levelno == logging.INFO

console_handler.addFilter(info_only)

logger.handlers.clear()
logger.addHandler(file_handler)
logger.addHandler(console_handler)


# Network settings
IP_RANGES = ['172.16.2.0/30']  # CIDR notation for IP ranges
#IP_RANGES = ['172.16.1.18/32', '172.16.2.1/32',]
TIMEOUT = 10  # Seconds
MAX_WORKERS = 254  # Number of concurrent threads (adjustable)

# SNMP settings
SNMP_PORT = 161
SNMP_COMMUNITIES = ['private', 'public']
SNMP_TIMEOUT = 3  # Seconds for SNMP response (increased for old devices)
SNMP_RETRY_DELAY = 3  # Seconds between SNMP community attempts (increased)

# SSH settings
SSH_PORT = 22
SSH_CREDENTIALS = [
    {'username': 'admin', 'password': 'admin123'},
]
SSH_TIMEOUT = 60  # Seconds for SSH connection (increased for old devices)
SSH_RETRY_DELAY = 5  # Seconds between SSH credential attempts (increased)

OIDS = {
    'sysName': '1.3.6.1.2.1.1.5.0',
    'sysDescr': '1.3.6.1.2.1.1.1.0',
}

DB_TYPE = 'sqlite'
SQLITE_DB = 'device_scan.db'
MYSQL_CONFIG = {
    'host': 'mysql',
    'user': 'user',
    'password': 'password',
    'database': 'device_scan',
}

USERS = {
    'admin': '1admin123',
    'user1': 'password1',
}

def get_ip_range():
    ip_list = []
    for cidr in IP_RANGES:
        try:
            network = ipaddress.ip_network(cidr, strict=False)
            ip_list.extend([str(ip) for ip in network.hosts()])
        except ValueError as e:
            logger.error(f"Invalid CIDR range {cidr}: {e}")
    return ip_list

def load_config_from_excel(file_path):
    global IP_RANGES, SNMP_COMMUNITIES, SSH_CREDENTIALS
    try:
        df = pd.read_excel(file_path)
        if 'IP_RANGE' in df.columns:
            IP_RANGES = df['IP_RANGE'].dropna().tolist()
            logger.info(f"Loaded IP ranges from Excel: {IP_RANGES}")
        if 'SNMP_COMMUNITY' in df.columns:
            SNMP_COMMUNITIES = df['SNMP_COMMUNITY'].dropna().tolist()
            logger.info(f"Loaded SNMP communities from Excel: {SNMP_COMMUNITIES}")
        if 'SSH_USERNAME' in df.columns and 'SSH_PASSWORD' in df.columns:
            ssh_users = df['SSH_USERNAME'].dropna().tolist()
            ssh_passes = df['SSH_PASSWORD'].dropna().tolist()
            if len(ssh_users) == len(ssh_passes):
                SSH_CREDENTIALS = [{'username': u, 'password': p} for u, p in zip(ssh_users, ssh_passes)]
                logger.info(f"Loaded SSH credentials from Excel: {SSH_CREDENTIALS}")
            else:
                logger.error("Mismatch in SSH_USERNAME and SSH_PASSWORD counts; using defaults")
    except Exception as e:
        logger.error(f"Failed to load config from Excel: {e}")

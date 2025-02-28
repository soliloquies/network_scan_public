# app/snmp_scanner.py
import asyncio
import re
from aiosnmp import Snmp
from config import SNMP_PORT, SNMP_COMMUNITIES, OIDS, SNMP_TIMEOUT, logger

async def scan_snmp(ip):
    result = {'ip': ip, 'sysName': None, 'vendor': None, 'model': None, 'snmp_status': 'Failed'}
    
    for community in SNMP_COMMUNITIES:
        try:
            async with Snmp(host=ip, port=SNMP_PORT, community=community, timeout=SNMP_TIMEOUT) as snmp:
                var_binds = await snmp.get([OIDS['sysName'], OIDS['sysDescr']])
                if var_binds:
                    # 解码字节串为字符串
                    sysName = var_binds[0].value.decode('utf-8', errors='ignore') if isinstance(var_binds[0].value, bytes) else str(var_binds[0].value)
                    sysDescr = var_binds[1].value.decode('utf-8', errors='ignore') if isinstance(var_binds[1].value, bytes) else str(var_binds[1].value)
                    
                    # 清理特殊字符（可选）
                    sysName = re.sub(r'[\x00-\x1F\x7F]', '', sysName)
                    sysDescr = re.sub(r'[\x00-\x1F\x7F]', '', sysDescr)
                    
                    # 尝试提取 vendor 和 model
                    vendor, model = extract_vendor_model(sysDescr)
                    
                    result.update({
                        'sysName': sysName,
                        'sysDescr': sysDescr,
                        'vendor': vendor,
                        'model': model,
                        'snmp_status': f'Success (community: {community})'
                    })
                    logger.info(f"SNMP success for {ip} with {community}")
                    return result
        except Exception as e:
            logger.warning(f"SNMP failed for {ip} with {community}: {e}")
    
    return result

def extract_vendor_model(sysDescr):
    """尝试从 sysDescr 中提取 vendor 和 model"""
    parts = sysDescr.split()
    if len(parts) >= 2:
        return parts[0], ' '.join(parts[1:])
    return 'Unknown', sysDescr

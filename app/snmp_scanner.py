# app/snmp_scanner.py
from pysnmp.hlapi import *
from config import SNMP_PORT, SNMP_COMMUNITIES, OIDS, SNMP_TIMEOUT, SNMP_RETRY_DELAY, logger
import time
import threading

def scan_snmp(ip):
    result = {'ip': ip, 'sysName': None, 'vendor': None, 'model': None, 'snmp_status': 'Failed'}
    
    def timeout_handler(iterator):
        try:
            iterator.close()
        except:
            pass
    
    for i, community in enumerate(SNMP_COMMUNITIES):
        iterator = None
        timer = None
        try:
            iterator = getCmd(
                SnmpEngine(),
                CommunityData(community, mpModel=1),
                UdpTransportTarget((ip, SNMP_PORT), timeout=SNMP_TIMEOUT),
                ContextData(),
                ObjectType(ObjectIdentity(OIDS['sysName'])),
                ObjectType(ObjectIdentity(OIDS['sysDescr']))
            )
            timer = threading.Timer(SNMP_TIMEOUT, timeout_handler, args=(iterator,))
            timer.start()
            
            errorIndication, errorStatus, errorIndex, varBinds = next(iterator)
            timer.cancel()
            
            if errorIndication or errorStatus:
                logger.warning(f"SNMP failed for {ip} with {community}: {errorIndication or errorStatus}")
                if i < len(SNMP_COMMUNITIES) - 1:
                    time.sleep(SNMP_RETRY_DELAY)
                continue
            
            sysName = str(varBinds[0][1]) if varBinds[0][1] else 'Unknown'
            sysDescr = str(varBinds[1][1]) if varBinds[1][1] else 'Unknown'
            vendor = sysDescr.split()[0] if sysDescr else 'Unknown'
            model = sysDescr.split()[1] if len(sysDescr.split()) > 1 else 'Unknown'
            
            result.update({
                'sysName': sysName,
                'vendor': vendor,
                'model': model,
                'snmp_status': f'Success (community: {community})'
            })
            logger.info(f"SNMP success for {ip} with {community}")
            return result
        
        except Exception as e:
            logger.warning(f"SNMP failed for {ip} with {community}: {e}")
            if i < len(SNMP_COMMUNITIES) - 1:
                time.sleep(SNMP_RETRY_DELAY)
        finally:
            if timer:
                timer.cancel()
    
    return result

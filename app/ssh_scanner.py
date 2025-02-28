import asyncssh
from config import SSH_PORT, SSH_CREDENTIALS, SSH_TIMEOUT, logger

async def scan_ssh(ip):
    result = {'ssh_status': 'Failed', 'ssh_user': None}
    
    for cred in SSH_CREDENTIALS:
        try:
            async with asyncssh.connect(
                ip,
                port=SSH_PORT,
                username=cred['username'],
                password=cred['password'],
                known_hosts=None,  # Ignore host key checking (for testing)
                connect_timeout=SSH_TIMEOUT,
                server_host_key_algs=['ssh-rsa', 'rsa-sha2-256', 'rsa-sha2-512'],
                kex_algs=['diffie-hellman-group14-sha1', 'diffie-hellman-group-exchange-sha256'],
                encryption_algs=[
                    'aes256-ctr', 'aes192-ctr', 'aes128-ctr',  # Original CTR-mode algorithms
                    'aes256-cbc', 'aes192-cbc', 'aes128-cbc',  # Added CBC-mode algorithms
                    '3des-cbc', 'blowfish-cbc', 'arcfour'      # Additional common algorithms
                ],
                mac_algs=[
                    'hmac-sha2-256', 'hmac-sha2-512',  # Original SHA2 algorithms
                    'hmac-sha1', 'hmac-md5', 'hmac-sha1-96', 'hmac-md5-96'  # Added SHA1/MD5 algorithms
                ]
            ) as conn:
                result['ssh_status'] = f'Success (user: {cred["username"]})'
                result['ssh_user'] = cred['username']
                logger.info(f"SSH success for {ip} with {cred['username']}")
                return result
        except Exception as e:
            logger.warning(f"SSH failed for {ip} with {cred['username']}: {e}")
    
    return result

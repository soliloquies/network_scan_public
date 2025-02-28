# Scan the available management devices in the access network

> If you don't have total equipment asset information, only have management IP prefix and SNMP/SSH credential, Maybe you need it to get all of the device info

- Supports SNMP and SSH credential combinations
- Supports scan multi-IP range e.g: 172.16.0.0/16
- Frontend and backend different separate deployment
- Support continuous integration
- Backend provides API and frontend page you can self-defined If you want


# Performance

- 172.16.0.0/16 + 172.17.0.0/16

Host 8c32G, running above IP range total cost time 2 hours.




# Service file

> It's just backend service

- Project path `/usr/local/script/network_scanner_web`
- Running path `/usr/local/script/network_scanner_web/app`


```bash
[Unit]
Description=check managerment device
After=network.target

[Service]
Type=simple
WorkingDirectory=/usr/local/script/network_scanner_web/app
ExecStart=/usr/local/bin/python3 main.py
Restart=on-failure
RestartSec=10s

[Install]
WantedBy=multi-user.target

```

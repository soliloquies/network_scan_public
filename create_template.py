# create_template.py
import pandas as pd
import os

# Define sample data for the template
template_data = {
    'IP_RANGE': ['172.16.1.0/24', '172.16.1.0/24', '172.16.1.0/24'],
    'SNMP_COMMUNITY': ['public', 'private', 'test'],
    'SSH_USERNAME': ['admin', 'user', 'guest'],
    'SSH_PASSWORD': ['admin123', 'password', 'guestpass']
}

# Create DataFrame
df = pd.DataFrame(template_data)

# Ensure the static directory exists
os.makedirs('app/static', exist_ok=True)

# Save to Excel
output_path = 'app/static/template.xlsx'
df.to_excel(output_path, index=False)
print(f"Template saved to {output_path}")

# app/db_handler.py
import pandas as pd
import sqlite3
import mysql.connector
from config import DB_TYPE, SQLITE_DB, MYSQL_CONFIG, logger

def ip_to_tuple(ip):
    """Convert IP address to a tuple of integers for numerical sorting."""
    return tuple(int(part) for part in ip.split('.'))

def save_results(results):
    """Save scan results to SQLite or MySQL, sorted by IP."""
    df_new = pd.DataFrame(results)
    df_new['ip_tuple'] = df_new['ip'].apply(ip_to_tuple)
    df_new = df_new.sort_values(by='ip_tuple', ascending=True)
    df_new = df_new.drop(columns=['ip_tuple'])
    
    if DB_TYPE == 'sqlite':
        try:
            conn = sqlite3.connect(SQLITE_DB)
            df_new.to_sql('devices', conn, if_exists='append', index=False)
            conn.execute("CREATE INDEX IF NOT EXISTS idx_ip ON devices (ip)")
            conn.close()
            logger.info(f"Results appended to {SQLITE_DB}")
        except Exception as e:
            logger.error(f"Failed to append to SQLite: {e}")
    
    elif DB_TYPE == 'mysql':
        try:
            conn = mysql.connector.connect(**MYSQL_CONFIG)
            cursor = conn.cursor()
            # Create table if not exists
            cursor.execute("""
                CREATE TABLE IF NOT EXISTS devices (
                    ip VARCHAR(15),
                    sysName VARCHAR(255),
                    vendor VARCHAR(255),
                    model VARCHAR(255),
                    snmp_status VARCHAR(255),
                    ssh_status VARCHAR(255),
                    ssh_user VARCHAR(255),
                    timestamp VARCHAR(20)
                )
            """)
            # Append data
            for _, row in df_new.iterrows():
                cursor.execute("""
                    INSERT INTO devices (ip, sysName, vendor, model, snmp_status, ssh_status, ssh_user, timestamp)
                    VALUES (%s, %s, %s, %s, %s, %s, %s, %s)
                """, tuple(row))
            conn.commit()
            cursor.execute("CREATE INDEX IF NOT EXISTS idx_ip ON devices (ip)")
            conn.close()
            logger.info("Results appended to MySQL")
        except Exception as e:
            logger.error(f"Failed to append to MySQL: {e}")
    
    return df_new

def get_results():
    """Retrieve all results from the database."""
    if DB_TYPE == 'sqlite':
        conn = sqlite3.connect(SQLITE_DB)
        df = pd.read_sql_query("SELECT * FROM devices ORDER BY ip ASC", conn)
        conn.close()
    elif DB_TYPE == 'mysql':
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        df = pd.read_sql("SELECT * FROM devices ORDER BY ip ASC", conn)
        conn.close()
    return df.to_dict(orient='records')

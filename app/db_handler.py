# app/db_handler.py
import pandas as pd
import sqlite3
from config import DB_TYPE, SQLITE_DB, MYSQL_CONFIG, logger

try:
    import mysql.connector
    MYSQL_AVAILABLE = True
except ImportError:
    MYSQL_AVAILABLE = False
    logger.warning("MySQL support unavailable; falling back to SQLite")

def ip_to_tuple(ip):
    return tuple(int(part) for part in ip.split('.'))

def save_results(results):
    df_new = pd.DataFrame(results)
    df_new['ip_tuple'] = df_new['ip'].apply(ip_to_tuple)
    df_new = df_new.sort_values(by='ip_tuple', ascending=True)
    df_new = df_new.drop(columns=['ip_tuple'])
    
    if DB_TYPE == 'sqlite' or not MYSQL_AVAILABLE:
        try:
            conn = sqlite3.connect(SQLITE_DB)
            df_new.to_sql('devices', conn, if_exists='append', index=False)
            conn.execute("CREATE INDEX IF NOT EXISTS idx_ip ON devices (ip)")
            conn.close()
            logger.info(f"Results appended to {SQLITE_DB}")
        except Exception as e:
            logger.error(f"Failed to append to SQLite: {e}")
    elif DB_TYPE == 'mysql' and MYSQL_AVAILABLE:
        try:
            conn = mysql.connector.connect(**MYSQL_CONFIG)
            cursor = conn.cursor()
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
    if DB_TYPE == 'sqlite' or not MYSQL_AVAILABLE:
        conn = sqlite3.connect(SQLITE_DB)
        df = pd.read_sql_query("SELECT * FROM devices ORDER BY ip ASC", conn)
        conn.close()
    elif DB_TYPE == 'mysql' and MYSQL_AVAILABLE:
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        df = pd.read_sql("SELECT * FROM devices ORDER BY ip ASC", conn)
        conn.close()
    return df.to_dict(orient='records')

def execute_custom_sql(query):
    if DB_TYPE == 'sqlite' or not MYSQL_AVAILABLE:
        conn = sqlite3.connect(SQLITE_DB)
        df = pd.read_sql_query(query, conn)
        conn.close()
    elif DB_TYPE == 'mysql' and MYSQL_AVAILABLE:
        conn = mysql.connector.connect(**MYSQL_CONFIG)
        df = pd.read_sql(query, conn)
        conn.close()
    return df.to_dict(orient='records')

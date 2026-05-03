# Receipt Printing Slowness - Root Cause Analysis

## Issue Summary
- **Symptom**: Receipt printing took up to 1 minute on May 2, 2026 around 18:00
- **Affected System**: Modern GUI (iPad) receipt printing
- **Status**: Intermittent (works fine most of the time, but occasionally slow)

## Root Cause Identified

### **Database Backup Running Every 10 Minutes**

The server runs a database backup script (`/usr/local/bin/backup-db-dump.sh`) **every 10 minutes** via cron:
- 18:00, 18:10, 18:20, 18:30, 18:40, 18:50, etc.

**Backup Process:**
1. `mysqldump` - Dumps entire database (162.68 MB)
2. `zip` - Compresses the dump
3. `sshpass sftp` - Uploads to remote FTP server
4. `wget` - Downloads backup file from PHP endpoint
5. `sshpass sftp` - Uploads downloaded file
6. `sshpass sftp` - Uploads log file

### **Why This Causes Slow Receipt Printing**

When a receipt is printed:
1. Modern GUI calls `paydesk_pay` API
2. Backend queries database to generate receipt
3. **If mysqldump is running**, it locks the database for reading
4. Receipt query waits for lock to be released
5. User experiences 30-60 second delay

### **Evidence**

From `/var/log/syslog.1`:
```
May  2 18:00:01 kasse3 CRON[1263763]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
May  2 18:10:01 kasse3 CRON[1263900]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
May  2 18:20:01 kasse3 CRON[1263943]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
May  2 18:30:01 kasse3 CRON[1263979]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
May  2 18:40:01 kasse3 CRON[1264120]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
May  2 18:50:01 kasse3 CRON[1264154]: (root) CMD (/usr/local/bin/backup-db-dump.sh > /dev/null 2>&1)
```

### **Database Size**
- Current database: **162.68 MB**
- Backup includes: mysqldump + zip + SFTP upload + wget download + SFTP upload

### **Why It's Intermittent**

The slowness only occurs if:
1. A receipt is printed **during** the backup window (usually 1-2 minutes per backup)
2. The backup is in the `mysqldump` phase (which locks the database)

## Recommended Solutions

### **Option 1: Use Mysqldump with --single-transaction (RECOMMENDED)**
- Allows concurrent reads during backup
- No database locks
- Requires InnoDB tables (check if used)

**Implementation:**
```bash
# Modify /usr/local/bin/backup-db-dump.sh
# Change:
mysqldump --user=$user --password=$password --skip-dump-date --no-create-db $database

# To:
mysqldump --user=$user --password=$password --skip-dump-date --no-create-db --single-transaction --lock-tables=false $database
```

### **Option 2: Reduce Backup Frequency**
- Change from every 10 minutes to every 30-60 minutes
- Reduces lock contention
- Trade-off: Less frequent backups

### **Option 3: Use Percona XtraBackup**
- Non-blocking backup tool
- Better for large databases
- Requires installation

### **Option 4: Schedule Backups During Off-Hours**
- Move backups to night time (e.g., 22:00-23:00)
- Eliminates issue during business hours
- Requires coordination with backup retention policy

## Modern GUI Impact

The Modern GUI is **NOT the cause** of the slowness:
- Modern GUI correctly calls `paydesk_pay` API
- Modern GUI correctly calls `queueReceiptPrintJob`
- The delay is in the **backend database layer**, not the GUI

## Testing Recommendation

1. **Before Fix**: Monitor receipt printing during backup windows (18:00, 18:10, etc.)
2. **Apply Fix**: Implement Option 1 (--single-transaction)
3. **After Fix**: Test receipt printing during backup windows - should be instant

## Files to Check/Modify

- `/usr/local/bin/backup-db-dump.sh` - Database backup script
- `/etc/cron.d/*` or root crontab - Backup schedule
- `/k3bck/backup_script.log` - Backup execution log

## Next Steps

1. **Do NOT change anything yet** (as requested)
2. Verify the backup script is indeed running every 10 minutes
3. Check if database uses InnoDB (required for --single-transaction)
4. Implement Option 1 or Option 4 based on your preference
5. Test and monitor for 1-2 weeks

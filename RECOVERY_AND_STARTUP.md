# Paw Net - System Recovery and Startup Guide

This document provides exact startup, recovery, backup, and restore procedures for the **Paw Net** application on Windows with XAMPP.

---

## 1. Environment & Paths Reference

- **Project Root Directory:** `d:\study\WT\wt project\main\pet`
- **Database Engine:** MySQL / MariaDB (Port: `3306`)
- **Database Name:** `pawnet`
- **Database User:** `root` (Default empty password: `""`)
- **PHP Executable:** `C:\xampp\php\php.exe` (Version 8.2.12)
- **MySQL Client:** `C:\xampp\mysql\bin\mysql.exe`
- **mysqldump Executable:** `C:\xampp\mysql\bin\mysqldump.exe`
- **Initial Backup Dumps:**
  - Database: `d:\study\WT\wt project\main\pet\database\backups\pawnet_backup_initial.sql`
  - File Accounts: `d:\study\WT\wt project\main\pet\data\backups\accounts.php`
  - File Pets: `d:\study\WT\wt project\main\pet\data\backups\pets.json`

---

## 2. Standard Startup Instructions

### Method A: Running with XAMPP Control Panel
1. Open **XAMPP Control Panel** (`C:\xampp\xampp-control.exe`).
2. Start the **Apache** and **MySQL** modules.
3. If Apache document root points to `d:\study\WT\wt project\main\pet`, visit:
   ```
   http://localhost/welcome.php
   ```

### Method B: Running with Built-in PHP Server (Fast Local Dev)
In PowerShell or Command Prompt, run:
```powershell
cd "d:\study\WT\wt project\main\pet"
& "C:\xampp\php\php.exe" -S 127.0.0.1:8000 -t .
```
Then open your browser at:
```
http://127.0.0.1:8000/welcome.php
```

---

## 3. Creating & Managing Accounts

### Initial Accounts Ready for Testing

| Role | Email | Password | URL Path |
|---|---|---|---|
| **Administrator** | `admin@pawnet.local` | `AdminSecure2026!` | `http://127.0.0.1:8000/login.php?role=admin` |
| **Pet Seller** | `vaibhavtamboli01@gmail.com` | `12345678` | `http://127.0.0.1:8000/login.php?role=seller` |
| **Pet Adopter (Buyer)** | `lucifer@gmail.com` | `12345678` | `http://127.0.0.1:8000/login.php?role=buyer` |

### How to Recreate or Change the Admin Account
To create a fresh admin account locally or reset the password:
```powershell
& "C:\xampp\php\php.exe" "d:\study\WT\wt project\main\pet\scripts\create-admin.php"
```

---

## 4. Database Backup & Export Instructions

### Exporting via Command Line (`mysqldump`)
Run in PowerShell to create a dated backup:
```powershell
$date = Get-Date -Format "yyyy-MM-dd_HH-mm"
& "C:\xampp\mysql\bin\mysqldump.exe" -u root pawnet > "d:\study\WT\wt project\main\pet\database\backups\pawnet_$date.sql"
```

### Exporting via phpMyAdmin
1. Open browser to `http://localhost/phpmyadmin`.
2. Select database `pawnet` in the left sidebar.
3. Click the **Export** tab in the top navigation.
4. Choose **Quick** export method and Format: **SQL**.
5. Click **Export** to save `pawnet.sql`.

---

## 5. Database Restore & Recovery Instructions

### Restoring from SQL Backup
If database corruption occurs or tables need to be reset to checkpoint:
```powershell
# 1. Ensure pawnet database exists
& "C:\xampp\mysql\bin\mysql.exe" -u root -e "CREATE DATABASE IF NOT EXISTS pawnet CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# 2. Import initial checkpoint backup
& "C:\xampp\mysql\bin\mysql.exe" -u root pawnet < "d:\study\WT\wt project\main\pet\database\backups\pawnet_backup_initial.sql"
```

### Applying Incremental Migrations
To re-run the full schema and migrations from scratch:
```powershell
# Run Schema Migration
& "C:\xampp\mysql\bin\mysql.exe" -u root pawnet < "d:\study\WT\wt project\main\pet\database\migrations\001_pawnet_full_schema.sql"

# Run Data Migration Script
& "C:\xampp\php\php.exe" "d:\study\WT\wt project\main\pet\scripts\migrate_all_data.php"

# Recreate Local Admin
& "C:\xampp\php\php.exe" "d:\study\WT\wt project\main\pet\scripts\create-admin.php"
```

---

## 6. XAMPP Crash Recovery Troubleshooting

### Problem: MySQL fails to start ("Error: MySQL shutdown unexpectedly")
1. **Cause A: Port 3306 Conflict**
   - Check if another service is using port 3306:
     ```powershell
     Get-NetTCPConnection -LocalPort 3306 -ErrorAction SilentlyContinue
     ```
   - If another MySQL/MariaDB service is holding port 3306, stop it in Windows Services (`services.msc`).
2. **Cause B: Corrupted InnoDB Log Files (`ibdata1` / `aria_log`)**
   - Navigate to `C:\xampp\mysql\data`.
   - Backup `C:\xampp\mysql\data` to a separate folder.
   - Delete `aria_log.*` and `ib_logfile*` files (do **not** delete `ibdata1` or database folders).
   - Restart MySQL in XAMPP.
3. **Cause C: Lock File Stale (`mysql.pid`)**
   - Delete any `.pid` file in `C:\xampp\mysql\data\`.
   - Restart MySQL service.

### Problem: Image uploads return an error
- Ensure `uploads/pets/` exists and is writable:
  ```powershell
  Test-Path "d:\study\WT\wt project\main\pet\uploads\pets"
  ```
- If uploads are restricted, verify `uploads/.htaccess` exists. It prevents direct PHP execution while allowing image previews (`.jpg`, `.png`, `.webp`).

---

## 7. Running System Verification Suite

A built-in automated test script tests all 40 system checkpoints across Database, Roles, Bug Fixes, Workflow States, Recycle Bin, and Security:
```powershell
& "C:\xampp\php\php.exe" "d:\study\WT\wt project\main\pet\scripts\verify_pawnet_system.php"
```
All 40 automated tests should output `[PASS]` with exit code `0`.

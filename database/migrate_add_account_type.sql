-- Migration script to add account_type column to existing HMS database
-- Run this in phpMyAdmin on your existing 'hms' database
-- 
-- IF YOU GET "Duplicate column name" ERROR:
-- That means the column already exists - just run the UPDATE statements below instead!
-- Use the file: migrate_add_account_type_safe.sql (which only has UPDATE statements)

-- Step 1: Add account_type column (SKIP THIS IF COLUMN ALREADY EXISTS)
-- If you get error "Duplicate column name", the column already exists - skip to Step 2
ALTER TABLE users 
ADD COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') 
NOT NULL DEFAULT 'Admin' 
AFTER password_hash;

-- Step 2: Update existing admin user (if exists)
UPDATE users SET account_type = 'Admin' WHERE username = 'admin';

-- Step 3: Set default for any users without account_type (just in case)
UPDATE users SET account_type = 'Admin' WHERE account_type IS NULL OR account_type = '';


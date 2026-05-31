-- Migration script to add Receptionist account type and patient_location field
-- Run this in phpMyAdmin on your existing 'hms' database
-- 
-- This script:
-- 1. Adds 'Receptionist' to the account_type ENUM
-- 2. Adds patient_location field to appointments table for tracking patient direction

-- Step 1: Modify account_type ENUM to include Receptionist
-- Note: MySQL doesn't support direct ENUM modification, so we need to use ALTER TABLE MODIFY
ALTER TABLE users 
MODIFY COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') 
NOT NULL DEFAULT 'Admin';

-- Step 2: Add patient_location field to appointments table (if it doesn't exist)
-- This field tracks whether patient should go to doctor or waiting area
ALTER TABLE appointments 
ADD COLUMN patient_location ENUM('Waiting','With Doctor') DEFAULT 'Waiting' 
AFTER status;

-- Step 3: Set default for existing appointments
UPDATE appointments SET patient_location = 'Waiting' WHERE patient_location IS NULL;


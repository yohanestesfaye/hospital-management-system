-- Add account_type column to users table
ALTER TABLE users ADD COLUMN account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') NOT NULL DEFAULT 'Admin' AFTER password_hash;

-- Update existing admin user
UPDATE users SET account_type = 'Admin' WHERE username = 'admin';


-- Safe Migration Script - Only runs UPDATE statements
-- Use this if you already have the account_type column
-- This script only updates existing data, won't try to add the column

-- Step 1: Update existing admin user (if exists)
UPDATE users SET account_type = 'Admin' WHERE username = 'admin';

-- Step 2: Set default for any users without account_type (just in case)
UPDATE users SET account_type = 'Admin' WHERE account_type IS NULL OR account_type = '';

-- That's it! The column already exists, so we just need to update the data.


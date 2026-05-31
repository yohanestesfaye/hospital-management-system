-- Add default user accounts for all actor types
-- 
-- RECOMMENDED: Use the PHP script instead for proper password hashing:
-- http://localhost/hms/public/setup/add_all_users.php
--
-- If you prefer SQL, you'll need to generate password hashes first using PHP:
-- <?php echo password_hash('YourPassword', PASSWORD_BCRYPT); ?>
--
-- All passwords follow the pattern: [Role]@123
-- Example: Doctor@123, Patient@123, etc.

-- Admin account (usually already exists, but included for completeness)
-- Password: Admin@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('admin', 'Administrator', '$2y$10$0m9rO5iDk6lGqf7yTnqLoe9C9qkRk2Qm3Jr3Jm6m2Kk5v7m9bN6rK', 'Admin');

-- Doctor account
-- Password: Doctor@123
-- NOTE: Generate a new hash using: password_hash('Doctor@123', PASSWORD_BCRYPT)
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('doctor', 'Dr. John Smith', '[GENERATE_HASH_HERE]', 'Doctor');

-- Patient account
-- Password: Patient@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('patient', 'John Patient', '[GENERATE_HASH_HERE]', 'Patient');

-- Nurse account
-- Password: Nurse@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('nurse', 'Nurse Jane Doe', '[GENERATE_HASH_HERE]', 'Nurse');

-- Pharmacist account
-- Password: Pharmacist@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('pharmacist', 'Pharmacist Bob Wilson', '[GENERATE_HASH_HERE]', 'Pharmacist');

-- Laboratorist account
-- Password: Laboratorist@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('laboratorist', 'Lab Tech Sarah Johnson', '[GENERATE_HASH_HERE]', 'Laboratorist');

-- Accountant account
-- Password: Accountant@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('accountant', 'Accountant Mike Brown', '[GENERATE_HASH_HERE]', 'Accountant');

-- Receptionist account
-- Password: Receptionist@123
INSERT IGNORE INTO users (username, name, password_hash, account_type)
VALUES ('receptionist', 'Receptionist Lisa Anderson', '[GENERATE_HASH_HERE]', 'Receptionist');

-- Note: INSERT IGNORE will skip if username already exists, so it's safe to run multiple times
-- 
-- IMPORTANT: The PHP script at /hms/public/setup/add_all_users.php automatically
-- generates correct password hashes. Use that instead of this SQL file!


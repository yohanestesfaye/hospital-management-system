-- Add a default doctor user account
-- Username: doctor
-- Password: Doctor@123
-- Name: Dr. John Smith

INSERT INTO users (username, name, password_hash, account_type)
VALUES ('doctor', 'Dr. John Smith', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Doctor');

-- Note: The password hash above is for the password: Doctor@123
-- If you need to generate a new password hash, you can use this PHP code:
-- <?php echo password_hash('YourPassword', PASSWORD_BCRYPT); ?>

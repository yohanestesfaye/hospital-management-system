## Hospital Management System (HMS)

Stack: PHP 8, MySQL (XAMPP), Bootstrap 5.3, Vanilla JS

### Setup (XAMPP on Windows)
- Copy this folder to `C:\xampp\htdocs\hms` (already present if you used Cursor).
- Start Apache and MySQL from XAMPP Control Panel.
- Open `phpMyAdmin` at `http://localhost/phpmyadmin`.
- Import database: In phpMyAdmin, create database `hms` or run the SQL below.

#### Initialize Database
Run the SQL in `database/init.sql`. Default admin user:
- Username: `admin`
- Password: `Admin@123`

If needed, edit DB credentials in `config.php`.

### Run
- Navigate to `http://localhost/hms/public/` to login.

### Features
- Secure login/logout with hashed passwords
- Dashboard stats
- Patients CRUD
- Doctors CRUD
- Appointments CRUD with patient/doctor linkage and status

### Notes
- This is a clean baseline; extend as needed (billing, wards, reports).



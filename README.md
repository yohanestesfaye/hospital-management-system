## Hospital Management System (HMS)
Overview
The Hospital Management System (HMS) is a web-based application designed to streamline and automate hospital operations. The system helps healthcare facilities manage patient records, appointments, pharmacy services, laboratory workflows, and billing processes through a centralized platform.
The goal of this project is to improve operational efficiency, reduce manual paperwork, and provide a user-friendly interface for hospital staff and administrators.
Features
Patient Management
Register new patients
View and update patient information
Maintain patient medical records
Appointment Management
Schedule appointments
Manage doctor availability
Track appointment history
Pharmacy Management
Manage medicine inventory
Record prescriptions
Track medicine distribution
Laboratory Management
Create laboratory requests
Record test results
Manage laboratory reports
Billing System
Generate invoices
Track payments
Manage billing records
Administrative Dashboard
View hospital statistics
Monitor system activities
Manage users and permissions
Technologies Used
Frontend
HTML5
CSS3
JavaScript
Backend
PHP
Database
MySQL
Design & Prototyping
Figma
System Architecture
The application follows a client-server architecture:
User interacts with the web interface.
PHP processes business logic.
MySQL stores and retrieves data.
Results are displayed through responsive web pages.
Installation
Prerequisites
PHP 8+
MySQL
XAMPP/WAMP/LAMP
Steps
Clone the repository:
git clone https://github.com/your-username/hospital-management-system.git 
Move the project folder to your web server directory.
Create a MySQL database.
Import the SQL database file.
Update database configuration settings.
Start Apache and MySQL.
Open:
http://localhost/hospital-management-system 
Screenshots
Add screenshots of:
Login Page
Dashboard
Patient Management
Appointment Module
Pharmacy Module
Billing Module
Project Objectives
Improve hospital workflow efficiency
Reduce manual record-keeping
Enhance patient data management
Provide a responsive and user-friendly interface
Support multiple hospital departments within a single platform
Future Improvements
Role-based authentication
SMS/Email notifications
Electronic Medical Records (EMR)
Online appointment booking
Report generation and analytics
Mobile application support
Author
Yohanes Tesfaye
Computer Science Student | Full-Stack Developer | UI/UX Designer
Email: yohannestespro@gmail.com
Location: Addis Ababa, Ethiopia
License
This project is developed for educational and portfolio purposes.

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



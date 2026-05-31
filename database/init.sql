CREATE DATABASE IF NOT EXISTS hms CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hms;

CREATE TABLE IF NOT EXISTS users (
	id INT AUTO_INCREMENT PRIMARY KEY,
	username VARCHAR(64) NOT NULL UNIQUE,
	name VARCHAR(100) NOT NULL,
	password_hash VARCHAR(255) NOT NULL,
	account_type ENUM('Admin', 'Doctor', 'Patient', 'Nurse', 'Pharmacist', 'Laboratorist', 'Accountant', 'Receptionist') NOT NULL DEFAULT 'Admin',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS patients (
	id INT AUTO_INCREMENT PRIMARY KEY,
	first_name VARCHAR(100) NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	gender ENUM('Male','Female','Other') NOT NULL,
	dob DATE NOT NULL,
	phone VARCHAR(30),
	email VARCHAR(120),
	address VARCHAR(255),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS doctors (
	id INT AUTO_INCREMENT PRIMARY KEY,
	first_name VARCHAR(100) NOT NULL,
	last_name VARCHAR(100) NOT NULL,
	specialty VARCHAR(120) NOT NULL,
	phone VARCHAR(30),
	email VARCHAR(120),
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS appointments (
	id INT AUTO_INCREMENT PRIMARY KEY,
	patient_id INT NOT NULL,
	doctor_id INT NOT NULL,
	appointment_date DATETIME NOT NULL,
	notes TEXT,
	status ENUM('Scheduled','Completed','Cancelled') DEFAULT 'Scheduled',
	patient_location ENUM('Waiting','With Doctor') DEFAULT 'Waiting',
	created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
	FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
	FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE RESTRICT
);

INSERT INTO users (username, name, password_hash, account_type)
VALUES ('admin', 'Administrator', '$2y$10$0m9rO5iDk6lGqf7yTnqLoe9C9qkRk2Qm3Jr3Jm6m2Kk5v7m9bN6rK', 'Admin');
-- password for the above user is: Admin@123

-- Add default doctor account
-- Username: doctor, Password: Doctor@123
-- Run the setup script at /hms/public/setup/add_doctor.php to create this account
-- Or manually insert with: INSERT INTO users (username, name, password_hash, account_type) 
-- VALUES ('doctor', 'Dr. John Smith', [generated_hash], 'Doctor');



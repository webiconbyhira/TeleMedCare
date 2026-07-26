-- ================================================
-- TeleCare Database Setup
-- phpMyAdmin mein yeh poora SQL paste karein
-- ================================================

-- Step 1: Database banao
CREATE DATABASE IF NOT EXISTS telecare_db
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE telecare_db;

-- ================================================
-- TABLE: users (login/signup ke liye)
-- ================================================
CREATE TABLE IF NOT EXISTS users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(150) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  phone      VARCHAR(20)  NOT NULL,
  password   VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ================================================
-- TABLE: doctors
-- ================================================
CREATE TABLE IF NOT EXISTS doctors (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  name         VARCHAR(150) NOT NULL,
  specialty    VARCHAR(100) NOT NULL,
  hospital     VARCHAR(150) NOT NULL,
  city         VARCHAR(100) NOT NULL,
  address      TEXT,
  experience   VARCHAR(50),
  fee          VARCHAR(50),
  rating       DECIMAL(2,1) DEFAULT 5.0,
  reviews      INT          DEFAULT 0,
  about        TEXT,
  timing       VARCHAR(200),
  created_at   DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ================================================
-- TABLE: appointments
-- ================================================
CREATE TABLE IF NOT EXISTS appointments (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  patient_name     VARCHAR(150) NOT NULL,
  email            VARCHAR(150) NOT NULL,
  phone            VARCHAR(20)  NOT NULL,
  record           TEXT,
  appointment_date DATE         NOT NULL,
  appointment_time VARCHAR(20)  NOT NULL,
  doctor_id        INT          NOT NULL,
  status           ENUM('pending','confirmed','cancelled') DEFAULT 'pending',
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  UNIQUE KEY no_double_booking (doctor_id, appointment_date, appointment_time)
);

-- ================================================
-- SAMPLE DOCTORS DATA
-- ================================================
INSERT INTO doctors (name, specialty, hospital, city, address, experience, fee, rating, reviews, about, timing) VALUES
('Dr. Ahmed Ali',   'Cardiologist',  'City Care Hospital',      'Faisalabad', '123 Main Boulevard, Faisalabad', '10+ Years', 'Rs. 800',   4.8, 120, 'Highly experienced cardiologist specializing in heart diseases and interventional procedures.', 'Monday - Saturday | 04:00 PM - 09:00 PM'),
('Dr. Sara Khan',   'Dermatologist', 'Health Plus Hospital',    'Lahore',     '45 Garden Town, Lahore',         '8+ Years',  'Rs. 1,000', 4.7,  98, 'Specialist in skin diseases, cosmetic dermatology, and laser treatments.',                     'Monday - Friday | 10:00 AM - 04:00 PM'),
('Dr. Usman Farooq','Neurologist',   'Metro Hospital',          'Islamabad',  'Blue Area, Islamabad',           '12+ Years', 'Rs. 1,200', 4.9, 110, 'Senior neurologist with expertise in epilepsy, stroke management, and neurodegenerative disorders.', 'Tuesday - Sunday | 02:00 PM - 08:00 PM'),
('Dr. Ayesha Malik','Pediatrician',  'Children Care Hospital',  'Lahore',     'Model Town, Lahore',             '6+ Years',  'Rs. 700',   4.6,  85, 'Specialist in child health, growth disorders, and pediatric emergencies.',                     'Monday - Saturday | 09:00 AM - 03:00 PM'),
('Dr. Bilal Hussain','Orthopedic',   'Bone & Joint Clinic',     'Faisalabad', 'D-Ground, Faisalabad',           '9+ Years',  'Rs. 900',   4.8,  74, 'Specialist in bone fractures, joint replacements, and sports injuries.',                       'Tuesday - Sunday | 03:00 PM - 08:00 PM'),
('Dr. Nadia Rehman','Dermatologist', 'Skin Care Centre',        'Islamabad',  'F-7, Islamabad',                 '5+ Years',  'Rs. 800',   4.5,  60, 'Dermatologist specializing in acne, skin allergies, and cosmetic procedures.',                  'Monday - Friday | 11:00 AM - 05:00 PM');

-- ================================================
-- USEFUL QUERIES (admin use ke liye)
-- ================================================

-- Sari appointments dekhna:
-- SELECT a.*, d.name AS doctor FROM appointments a JOIN doctors d ON a.doctor_id = d.id ORDER BY appointment_date, appointment_time;

-- Aaj ki appointments:
-- SELECT * FROM appointments WHERE appointment_date = CURDATE();

-- Ek doctor ke booked slots:
-- SELECT appointment_time FROM appointments WHERE doctor_id = 1 AND appointment_date = '2024-05-24';

-- Sare users:
-- SELECT id, name, email, phone, created_at FROM users;

-- ================================================
-- TABLE: video_consultations (Video Consult feature)
-- ================================================
CREATE TABLE IF NOT EXISTS video_consultations (
  id               INT AUTO_INCREMENT PRIMARY KEY,
  patient_name     VARCHAR(150) NOT NULL,
  email            VARCHAR(150) NOT NULL,
  concern          TEXT,
  consult_date     DATE         NOT NULL,
  consult_time     VARCHAR(20)  NOT NULL,
  doctor_id        INT          NOT NULL,
  meet_link        VARCHAR(255),
  status           ENUM('confirmed','completed','cancelled') DEFAULT 'confirmed',
  created_at       DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE CASCADE,
  UNIQUE KEY no_double_vc (doctor_id, consult_date, consult_time)
);

-- ================================================
-- Useful video consult queries:
-- ================================================
-- Sari video consultations:
-- SELECT vc.*, d.name AS doctor FROM video_consultations vc JOIN doctors d ON vc.doctor_id=d.id ORDER BY consult_date, consult_time;

-- Aaj ki video calls:
-- SELECT * FROM video_consultations WHERE consult_date = CURDATE();

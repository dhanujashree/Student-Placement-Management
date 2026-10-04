CREATE DATABASE IF NOT EXISTS placement_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE placement_management;

SET FOREIGN_KEY_CHECKS=0;
DROP TABLE IF EXISTS results;
DROP TABLE IF EXISTS interviews;
DROP TABLE IF EXISTS applications;
DROP TABLE IF EXISTS placement_drives;
DROP TABLE IF EXISTS companies;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS admins;
SET FOREIGN_KEY_CHECKS=1;

CREATE TABLE admins (
  admin_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE students (
  student_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  register_no VARCHAR(30) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) DEFAULT '',
  password VARCHAR(255) NOT NULL,
  dob DATE NULL,
  gender VARCHAR(20) DEFAULT '',
  department VARCHAR(80) NOT NULL,
  year TINYINT UNSIGNED NOT NULL,
  cgpa DECIMAL(4,2) NULL,
  tenth_percentage DECIMAL(5,2) NULL,
  twelfth_percentage DECIMAL(5,2) NULL,
  skills TEXT,
  certifications TEXT,
  resume VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_student_department (department),
  INDEX idx_student_year (year)
) ENGINE=InnoDB;

CREATE TABLE companies (
  company_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_name VARCHAR(150) NOT NULL UNIQUE,
  industry VARCHAR(100) DEFAULT '',
  location VARCHAR(150) DEFAULT '',
  website VARCHAR(255) DEFAULT '',
  contact_person VARCHAR(120) DEFAULT '',
  email VARCHAR(150) DEFAULT '',
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_company_name (company_name)
) ENGINE=InnoDB;

CREATE TABLE placement_drives (
  drive_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  company_id INT UNSIGNED NOT NULL,
  job_role VARCHAR(150) NOT NULL,
  package DECIMAL(8,2) NOT NULL DEFAULT 0,
  location VARCHAR(150) DEFAULT '',
  drive_date DATETIME NOT NULL,
  deadline DATETIME NOT NULL,
  min_cgpa DECIMAL(4,2) NOT NULL DEFAULT 0,
  min_tenth DECIMAL(5,2) NOT NULL DEFAULT 0,
  min_twelfth DECIMAL(5,2) NOT NULL DEFAULT 0,
  eligible_departments VARCHAR(255) NOT NULL DEFAULT 'ALL',
  eligible_year TINYINT UNSIGNED NOT NULL DEFAULT 0,
  required_skills TEXT,
  job_description TEXT,
  openings INT UNSIGNED NOT NULL DEFAULT 1,
  selection_process TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_drive_company FOREIGN KEY (company_id) REFERENCES companies(company_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  INDEX idx_drive_dates (drive_date, deadline),
  INDEX idx_drive_company (company_id)
) ENGINE=InnoDB;

CREATE TABLE applications (
  application_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  drive_id INT UNSIGNED NOT NULL,
  applied_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  status ENUM('Applied','Shortlisted','Aptitude Cleared','Technical Cleared','HR Cleared','Selected','Rejected') NOT NULL DEFAULT 'Applied',
  CONSTRAINT fk_application_student FOREIGN KEY (student_id) REFERENCES students(student_id) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_application_drive FOREIGN KEY (drive_id) REFERENCES placement_drives(drive_id) ON UPDATE CASCADE ON DELETE RESTRICT,
  UNIQUE KEY uq_student_drive (student_id, drive_id),
  INDEX idx_application_status (status),
  INDEX idx_application_student (student_id)
) ENGINE=InnoDB;

CREATE TABLE interviews (
  interview_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL,
  round_name VARCHAR(100) NOT NULL,
  interview_date DATE NOT NULL,
  interview_time TIME NOT NULL,
  venue VARCHAR(200) DEFAULT '',
  mode ENUM('Offline','Online','Hybrid') NOT NULL DEFAULT 'Offline',
  meeting_link VARCHAR(500) DEFAULT '',
  CONSTRAINT fk_interview_application FOREIGN KEY (application_id) REFERENCES applications(application_id) ON UPDATE CASCADE ON DELETE CASCADE,
  INDEX idx_interview_date (interview_date, interview_time)
) ENGINE=InnoDB;

CREATE TABLE results (
  result_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  application_id INT UNSIGNED NOT NULL UNIQUE,
  result_status ENUM('Selected','Rejected','Pending') NOT NULL DEFAULT 'Pending',
  remarks VARCHAR(500) DEFAULT '',
  CONSTRAINT fk_result_application FOREIGN KEY (application_id) REFERENCES applications(application_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  notification_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(200) NOT NULL,
  message TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_notification_created (created_at)
) ENGINE=InnoDB;

INSERT INTO admins (name,email,password) VALUES
('Placement Administrator','admin@placement.com','$2y$12$US08gHuoqZl6ycdOM4haJu1j8BUAPIT.0JYFJOkvcGPx9pSwyKUcu');

INSERT INTO students (register_no,name,email,phone,password,dob,gender,department,year,cgpa,tenth_percentage,twelfth_percentage,skills,certifications) VALUES
('CSE001','Arun Kumar','arun@example.com','9876500001','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-02-14','Male','CSE',4,8.72,91.4,89.2,'Java, SQL, HTML, CSS','NPTEL Java Programming'),
('CSE002','Meena Ravi','meena@example.com','9876500002','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-06-21','Female','CSE',4,9.12,94.0,92.5,'JavaScript, PHP, MySQL','Responsive Web Design'),
('ECE001','Karthik S','karthik@example.com','9876500003','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-01-08','Male','ECE',4,7.84,88.0,86.7,'C, Embedded C, SQL','IoT Fundamentals'),
('EEE001','Priya N','priya@example.com','9876500004','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-08-11','Female','EEE',4,8.31,90.2,88.4,'Python, SQL, Excel','Data Analytics Basics'),
('IT001','Vishal P','vishal@example.com','9876500005','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-04-17','Male','IT',4,8.65,92.5,90.1,'PHP, MySQL, JavaScript','Web Development'),
('CSE003','Nila Devi','nila@example.com','9876500006','$2y$12$IrmwIR6mc6Xd4VwFiOWOquuBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-10-03','Female','CSE',4,7.42,85.6,83.1,'HTML, CSS, Java','Java Foundations'),
('MECH001','Rohit K','rohit@example.com','9876500007','$2y$12$IrmwIR6mc6Xd4VwFiOWOquBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-03-25','Male','MECH',4,7.98,87.7,84.8,'AutoCAD, Excel, SQL','CAD Basics'),
('AIML001','Ananya S','ananya@example.com','9876500008','$2y$12$IrmwIR6mc6Xd4VwFiOWOquBdCS1SxgA4gQ0nFclnfAGIv6vyWAkG','2005-07-29','Female','AIML',4,9.36,95.2,93.6,'Python, SQL, JavaScript','Machine Learning Basics');

INSERT INTO companies (company_name,industry,location,website,contact_person,email,description) VALUES
('TechNova Solutions','Software','Chennai','https://example.com/technova','Ravi Menon','hr@technova.example.com','Software products and enterprise application services.'),
('CloudBridge Systems','Cloud Computing','Bengaluru','https://example.com/cloudbridge','Sneha Iyer','careers@cloudbridge.example.com','Cloud infrastructure and managed services.'),
('FinEdge Labs','FinTech','Hyderabad','https://example.com/finedge','Vikram Shah','talent@finedge.example.com','Digital finance and analytics products.'),
('InnoWorks Digital','IT Services','Chennai','https://example.com/innoworks','Divya Rao','hr@innoworks.example.com','Web, mobile and business automation services.'),
('GreenGrid Engineering','Engineering','Coimbatore','https://example.com/greengrid','Sanjay Kumar','jobs@greengrid.example.com','Engineering systems and industrial technology.');

INSERT INTO placement_drives (company_id,job_role,package,location,drive_date,deadline,min_cgpa,min_tenth,min_twelfth,eligible_departments,eligible_year,required_skills,job_description,openings,selection_process) VALUES
(1,'Software Developer',6.50,'Chennai','2026-10-20 10:00:00','2026-10-15 23:59:59',7.50,75,75,'CSE,IT,AIML',4,'Java, SQL, JavaScript','Build and maintain web applications with a collaborative engineering team.',8,'Online Test → Technical Interview → HR Interview'),
(2,'Cloud Support Associate',5.80,'Bengaluru','2026-10-28 09:30:00','2026-10-23 23:59:59',7.00,70,70,'CSE,IT,ECE,AIML',4,'Linux, Networking, SQL','Support cloud workloads, monitor services and resolve technical tickets.',10,'Aptitude → Technical Round → HR'),
(3,'Data Analyst',7.20,'Hyderabad','2026-11-05 10:00:00','2026-10-30 23:59:59',8.00,80,80,'CSE,IT,AIML,EEE',4,'SQL, Excel, Python','Analyze business data and communicate actionable insights.',5,'Aptitude → Case Study → Interview'),
(4,'PHP Web Developer',5.20,'Chennai','2026-11-12 10:00:00','2026-11-07 23:59:59',7.00,70,70,'CSE,IT',4,'PHP, MySQL, JavaScript','Develop and test responsive PHP/MySQL web applications.',6,'Coding Test → Technical → HR'),
(5,'Graduate Engineer Trainee',4.80,'Coimbatore','2026-11-18 09:00:00','2026-11-13 23:59:59',7.00,70,70,'MECH,ECE,EEE',4,'CAD, Problem Solving','Support engineering projects and technical documentation.',12,'Aptitude → Technical → HR'),
(1,'Frontend Intern-to-Engineer',4.20,'Chennai','2026-12-03 10:00:00','2026-11-28 23:59:59',8.50,80,80,'CSE,IT,AIML',4,'HTML, CSS, JavaScript','Create accessible and responsive user interfaces.',4,'Portfolio Review → Practical Test → Interview');

INSERT INTO applications (student_id,drive_id,applied_date,status) VALUES
(1,1,'2026-10-02 10:15:00','Shortlisted'),
(2,1,'2026-10-02 11:20:00','Selected'),
(3,2,'2026-10-02 12:30:00','Applied'),
(4,3,'2026-10-02 13:40:00','Rejected'),
(5,4,'2026-10-02 14:10:00','Technical Cleared'),
(8,3,'2026-10-02 15:25:00','Shortlisted'),
(6,6,'2026-10-02 16:05:00','Applied');

INSERT INTO interviews (application_id,round_name,interview_date,interview_time,venue,mode,meeting_link) VALUES
(1,'Technical Interview','2026-10-14','11:00:00','Placement Cell - Room 2','Offline',''),
(5,'HR Interview','2026-10-25','15:00:00','Placement Cell - Room 1','Hybrid','https://meet.example.com/demo-hr'),
(6,'Case Study','2026-10-31','10:30:00','Online','Online','https://meet.example.com/demo-case');

INSERT INTO results (application_id,result_status,remarks) VALUES
(2,'Selected','Offer letter processing is in progress.'),
(4,'Rejected','Thank you for participating in the selection process.');

INSERT INTO notifications (title,message) VALUES
('New Placement Drive','TechNova Solutions has opened the Software Developer drive. Check eligibility and apply before the deadline.'),
('Interview Scheduled','A technical interview has been scheduled for shortlisted applicants. Check the Interviews section.'),
('Result Published','Placement results have been updated. Please check the Results section for details.');

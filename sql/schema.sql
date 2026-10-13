-- Employer Module schema (MySQL 8 / MariaDB 10.4+)
CREATE DATABASE IF NOT EXISTS employer_module CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE employer_module;

-- ---------- D1 EMPLOYERS ----------
CREATE TABLE employers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id VARCHAR(50) NULL UNIQUE,
  company_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  mobile VARCHAR(20) NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  mobile_verified TINYINT(1) NOT NULL DEFAULT 0,
  contact_person VARCHAR(120) NULL,
  designation VARCHAR(100) NULL,
  industry VARCHAR(100) NULL,
  company_size VARCHAR(30) NULL,
  website VARCHAR(200) NULL,
  address VARCHAR(255) NULL,
  city VARCHAR(80) NULL,
  state VARCHAR(80) NULL,
  country VARCHAR(80) NULL DEFAULT 'India',
  about TEXT NULL,
  logo VARCHAR(255) NULL,
  brand_color VARCHAR(7) NOT NULL DEFAULT '#0d6efd',
  tagline VARCHAR(200) NULL,
  status ENUM('Pending','Approved','Rejected','Suspended') NOT NULL DEFAULT 'Pending',
  source ENUM('Self','EmpAdmin','Bulk') NOT NULL DEFAULT 'Self',
  approved_by INT NULL,
  approved_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('SuperAdmin','EmpAdmin','CandAdmin','SkillsAdmin','DataAdmin','Employer') NOT NULL,
  employer_id INT NULL,
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  last_login DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE otp_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  target VARCHAR(150) NOT NULL,
  purpose ENUM('email','mobile') NOT NULL,
  otp_hash VARCHAR(255) NOT NULL,
  attempts TINYINT NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (target, purpose)
) ENGINE=InnoDB;

-- ---------- P2 INTERVIEWERS ----------
CREATE TABLE interviewers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employer_id INT NOT NULL,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL,
  phone VARCHAR(20) NULL,
  designation VARCHAR(100) NULL,
  role ENUM('Technical','HR','Group Discussion','Managerial','Other') NOT NULL DEFAULT 'Technical',
  status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_emp_int (employer_id, email),
  FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- D2 JOBS ----------
CREATE TABLE jobs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  employer_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  department VARCHAR(100) NULL,
  description TEXT NOT NULL,
  skills VARCHAR(500) NULL COMMENT 'comma separated',
  location VARCHAR(120) NULL,
  job_type ENUM('Full-time','Part-time','Contract','Internship') NOT NULL DEFAULT 'Full-time',
  exp_min DECIMAL(4,1) NOT NULL DEFAULT 0,
  exp_max DECIMAL(4,1) NULL,
  salary_min INT NULL,
  salary_max INT NULL,
  vacancies INT NOT NULL DEFAULT 1,
  last_date DATE NULL,
  status ENUM('Draft','Published','Closed') NOT NULL DEFAULT 'Draft',
  published_at DATETIME NULL,
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE job_rounds (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  round_no TINYINT NOT NULL,
  round_name VARCHAR(80) NOT NULL,
  round_type ENUM('Technical','GD','HR','Managerial','Aptitude','Other') NOT NULL DEFAULT 'Technical',
  interviewer_id INT NULL,
  UNIQUE KEY uq_job_round (job_id, round_no),
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (interviewer_id) REFERENCES interviewers(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE social_posts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  platform ENUM('LinkedIn','Facebook','Twitter/X','Instagram') NOT NULL,
  status ENUM('Queued','Posted','Failed') NOT NULL DEFAULT 'Queued',
  post_ref VARCHAR(255) NULL,
  posted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- D5 CANDIDATES ----------
CREATE TABLE candidates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(20) NULL,
  dob DATE NULL,
  gender ENUM('Male','Female','Other') NULL,
  location VARCHAR(120) NULL,
  education VARCHAR(200) NULL,
  experience_years DECIMAL(4,1) NOT NULL DEFAULT 0,
  current_company VARCHAR(150) NULL,
  skills VARCHAR(500) NULL,
  summary TEXT NULL,
  resume_path VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  job_id INT NOT NULL,
  candidate_id INT NOT NULL,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  screening_score TINYINT NULL,
  screening_notes VARCHAR(500) NULL,
  status ENUM('Applied','Shortlisted','In Process','Selected','On Hold','Rejected','Offered','Offer Accepted','Offer Declined') NOT NULL DEFAULT 'Applied',
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_app (job_id, candidate_id),
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- D3 INTERVIEWS ----------
CREATE TABLE interviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  round_id INT NOT NULL,
  interviewer_id INT NULL,
  mode ENUM('Physical','Virtual') NOT NULL DEFAULT 'Virtual',
  scheduled_at DATETIME NOT NULL,
  venue_or_link VARCHAR(255) NULL,
  schedule_status ENUM('Scheduled','Completed','Cancelled') NOT NULL DEFAULT 'Scheduled',
  result ENUM('Pending','Passed','Failed','On Hold') NOT NULL DEFAULT 'Pending',
  score TINYINT NULL,
  remarks VARCHAR(500) NULL,
  reminder_sent TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
  FOREIGN KEY (round_id) REFERENCES job_rounds(id) ON DELETE CASCADE,
  FOREIGN KEY (interviewer_id) REFERENCES interviewers(id) ON DELETE SET NULL,
  INDEX (scheduled_at)
) ENGINE=InnoDB;

CREATE TABLE hr_verifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  status ENUM('Pending','Verified','Failed') NOT NULL DEFAULT 'Pending',
  docs_verified TINYINT(1) NOT NULL DEFAULT 0,
  background_verified TINYINT(1) NOT NULL DEFAULT 0,
  remarks VARCHAR(500) NULL,
  verified_by INT NULL,
  verified_at DATETIME NULL,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- D4 OFFERS & FEEDBACK ----------
CREATE TABLE offers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL UNIQUE,
  offer_no VARCHAR(40) NOT NULL UNIQUE,
  designation VARCHAR(120) NOT NULL,
  ctc INT NOT NULL,
  joining_date DATE NOT NULL,
  valid_until DATE NULL,
  terms TEXT NULL,
  status ENUM('Generated','Sent','Accepted','Declined') NOT NULL DEFAULT 'Generated',
  created_by INT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE feedback (
  id INT AUTO_INCREMENT PRIMARY KEY,
  application_id INT NOT NULL,
  from_type ENUM('Candidate','Employer') NOT NULL,
  rating TINYINT NULL,
  comments TEXT NULL,
  token CHAR(40) NULL,
  submitted_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_fb (application_id, from_type),
  UNIQUE KEY uq_tok (token),
  FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  channel ENUM('Email','SMS') NOT NULL DEFAULT 'Email',
  recipient VARCHAR(150) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  related_type VARCHAR(30) NULL,
  related_id INT NULL,
  status ENUM('Queued','Sent','Failed') NOT NULL DEFAULT 'Queued',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------- SEED DATA (all demo passwords: Admin@123) ----------
INSERT INTO users (name,email,password_hash,role) VALUES
('Super Admin','superadmin@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','SuperAdmin'),
('Employer Admin','empadmin@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','EmpAdmin'),
('Candidate Admin','candadmin@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','CandAdmin'),
('Skills Admin','skillsadmin@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','SkillsAdmin'),
('Data Admin','dataadmin@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','DataAdmin');

INSERT INTO employers (company_id,company_name,email,mobile,email_verified,mobile_verified,contact_person,industry,city,state,about,status,source,approved_at)
VALUES ('CIN-DEMO-001','Acme Technologies','hr@acme.example','9876543210',1,1,'Priya Nair','Information Technology','Thiruvananthapuram','Kerala','Product engineering company.','Approved','EmpAdmin',NOW());
INSERT INTO users (name,email,password_hash,role,employer_id) VALUES
('Priya Nair','hr@acme.example','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','Employer',1);

INSERT INTO interviewers (employer_id,name,email,phone,designation,role) VALUES
(1,'Arun Menon','arun@acme.example','9000000001','Tech Lead','Technical'),
(1,'Neha Das','neha@acme.example','9000000002','HR Manager','HR');

INSERT INTO jobs (employer_id,title,department,description,skills,location,exp_min,exp_max,salary_min,salary_max,vacancies,last_date,status,published_at)
VALUES (1,'PHP Developer','Engineering','Build and maintain PHP/MySQL web applications.','PHP,MySQL,JavaScript,Git','Thiruvananthapuram',1,4,400000,800000,2,DATE_ADD(CURDATE(),INTERVAL 30 DAY),'Published',NOW());
INSERT INTO job_rounds (job_id,round_no,round_name,round_type,interviewer_id) VALUES
(1,1,'Technical Interview','Technical',1),(1,2,'HR Interview','HR',2);

INSERT INTO candidates (name,email,phone,location,education,experience_years,current_company,skills,summary) VALUES
('Anjali Pillai','anjali@example.com','9111111111','Kochi','B.Tech CSE',3,'WebWorks','PHP,MySQL,JavaScript,Laravel','Full-stack developer with 3 years experience.'),
('Rahul Kumar','rahul@example.com','9222222222','Chennai','MCA',1,'StartUpX','PHP,HTML,CSS','Junior developer.'),
('Meera Joseph','meera@example.com','9333333333','Thiruvananthapuram','B.Sc Computer Science',2,'DataSoft','Python,MySQL,Git','Backend developer.');
INSERT INTO applications (job_id,candidate_id) VALUES (1,1),(1,2),(1,3);

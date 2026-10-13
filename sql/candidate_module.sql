-- Candidate module + skill gap analysis. Run AFTER schema.sql.
USE employer_module;

ALTER TABLE users
  MODIFY role ENUM('SuperAdmin','EmpAdmin','CandAdmin','SkillsAdmin','DataAdmin','Employer','Candidate') NOT NULL,
  ADD COLUMN candidate_id INT NULL,
  ADD CONSTRAINT fk_user_cand FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE;

ALTER TABLE applications
  MODIFY status ENUM('Applied','Shortlisted','In Process','Selected','On Hold','Rejected','Offered','Offer Accepted','Offer Declined','Withdrawn') NOT NULL DEFAULT 'Applied';

ALTER TABLE candidates
  ADD COLUMN headline VARCHAR(150) NULL,
  ADD COLUMN expected_salary INT NULL,
  ADD COLUMN preferred_location VARCHAR(120) NULL,
  ADD COLUMN job_type_pref VARCHAR(30) NULL;

-- ---------- Skill taxonomy ----------
CREATE TABLE skills (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  category VARCHAR(60) NOT NULL DEFAULT 'Other',
  approved TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE skill_aliases (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skill_id INT NOT NULL,
  alias VARCHAR(80) NOT NULL UNIQUE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE job_skills (
  job_id INT NOT NULL,
  skill_id INT NOT NULL,
  weight TINYINT NOT NULL DEFAULT 1,            -- 1 (nice) .. 5 (critical)
  required_level TINYINT NOT NULL DEFAULT 3,    -- 1 beginner .. 5 expert
  mandatory TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (job_id, skill_id),
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE candidate_skills (
  candidate_id INT NOT NULL,
  skill_id INT NOT NULL,
  level TINYINT NOT NULL DEFAULT 3,
  years DECIMAL(4,1) NULL,
  source ENUM('Self','Resume','Test','Course','Certificate') NOT NULL DEFAULT 'Self',
  verified TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (candidate_id, skill_id),
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Learning ----------
CREATE TABLE courses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(160) NOT NULL,
  provider VARCHAR(100) NULL,
  url VARCHAR(255) NULL,
  hours SMALLINT NOT NULL DEFAULT 10,
  level_provided TINYINT NOT NULL DEFAULT 3,    -- skill level reached on completion
  cost_type ENUM('Free','Paid') NOT NULL DEFAULT 'Free',
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE skill_courses (
  skill_id INT NOT NULL,
  course_id INT NOT NULL,
  PRIMARY KEY (skill_id, course_id),
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE candidate_learning (
  candidate_id INT NOT NULL,
  course_id INT NOT NULL,
  status ENUM('In Progress','Completed') NOT NULL DEFAULT 'In Progress',
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (candidate_id, course_id),
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE saved_jobs (
  candidate_id INT NOT NULL,
  job_id INT NOT NULL,
  saved_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (candidate_id, job_id),
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE gap_reports (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  job_id INT NOT NULL,
  match_score TINYINT NOT NULL,
  details JSON NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gap (candidate_id, job_id),
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------- Seed: taxonomy ----------
INSERT INTO skills (name,category) VALUES
('PHP','Backend'),('MySQL','Database'),('SQL','Database'),('JavaScript','Frontend'),('TypeScript','Frontend'),('HTML','Frontend'),('CSS','Frontend'),
('React','Frontend'),('Node.js','Backend'),('Python','Backend'),('Java','Backend'),('Laravel','Backend'),('Django','Backend'),('REST APIs','Backend'),
('Git','Tools'),('Docker','DevOps'),('Linux','DevOps'),('AWS','Cloud'),('Communication','Soft skill'),('Problem solving','Soft skill');
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'JS' FROM skills WHERE name='JavaScript';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'ReactJS' FROM skills WHERE name='React';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'React.js' FROM skills WHERE name='React';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'NodeJS' FROM skills WHERE name='Node.js';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'My SQL' FROM skills WHERE name='MySQL';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'GitHub' FROM skills WHERE name='Git';
INSERT INTO skill_aliases (skill_id,alias) SELECT id,'REST' FROM skills WHERE name='REST APIs';

-- ---------- Seed: courses (placeholder providers/links; replace with real catalogue) ----------
INSERT INTO courses (title,provider,url,hours,level_provided,cost_type) VALUES
('PHP fundamentals','Sample Academy','#',20,3,'Free'),
('Advanced PHP and OOP','Sample Academy','#',30,4,'Paid'),
('MySQL essentials','Sample Academy','#',15,3,'Free'),
('Advanced SQL and indexing','Sample Academy','#',20,4,'Paid'),
('JavaScript foundations','Sample Academy','#',25,3,'Free'),
('Git and GitHub basics','Sample Academy','#',6,3,'Free'),
('React from scratch','Sample Academy','#',30,3,'Paid'),
('Docker basics','Sample Academy','#',10,3,'Free'),
('Python for backend','Sample Academy','#',30,3,'Free'),
('REST API design','Sample Academy','#',12,3,'Free');
INSERT INTO skill_courses (skill_id,course_id) SELECT s.id,c.id FROM skills s JOIN courses c ON
 (s.name='PHP' AND c.title IN ('PHP fundamentals','Advanced PHP and OOP')) OR (s.name='MySQL' AND c.title IN ('MySQL essentials','Advanced SQL and indexing'))
 OR (s.name='SQL' AND c.title IN ('MySQL essentials','Advanced SQL and indexing')) OR (s.name='JavaScript' AND c.title='JavaScript foundations')
 OR (s.name='Git' AND c.title='Git and GitHub basics') OR (s.name='React' AND c.title='React from scratch') OR (s.name='Docker' AND c.title='Docker basics')
 OR (s.name='Python' AND c.title='Python for backend') OR (s.name='REST APIs' AND c.title='REST API design');

-- ---------- Seed: demo job skills + candidate skills + candidate logins (password Admin@123) ----------
INSERT INTO job_skills (job_id,skill_id,weight,required_level,mandatory) SELECT 1,id,
  CASE name WHEN 'PHP' THEN 5 WHEN 'MySQL' THEN 3 WHEN 'JavaScript' THEN 3 ELSE 1 END,
  CASE name WHEN 'PHP' THEN 4 WHEN 'MySQL' THEN 3 WHEN 'JavaScript' THEN 3 ELSE 2 END,
  CASE name WHEN 'PHP' THEN 1 ELSE 0 END FROM skills WHERE name IN ('PHP','MySQL','JavaScript','Git');
INSERT INTO candidate_skills (candidate_id,skill_id,level) SELECT 1,id,CASE name WHEN 'PHP' THEN 4 WHEN 'MySQL' THEN 4 WHEN 'JavaScript' THEN 3 ELSE 4 END FROM skills WHERE name IN ('PHP','MySQL','JavaScript','Laravel');
INSERT INTO candidate_skills (candidate_id,skill_id,level) SELECT 2,id,CASE name WHEN 'PHP' THEN 2 ELSE 3 END FROM skills WHERE name IN ('PHP','HTML','CSS');
INSERT INTO candidate_skills (candidate_id,skill_id,level) SELECT 3,id,CASE name WHEN 'Python' THEN 4 ELSE 3 END FROM skills WHERE name IN ('Python','MySQL','Git');
INSERT INTO users (name,email,password_hash,role,candidate_id) VALUES
('Anjali Pillai','anjali@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','Candidate',1),
('Rahul Kumar','rahul@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','Candidate',2),
('Meera Joseph','meera@example.com','$2y$10$IsuzciwMjz204ZlwsjnBpOWmIHD.j0tWrcaEFXGAG21.lqVXTw4eS','Candidate',3);

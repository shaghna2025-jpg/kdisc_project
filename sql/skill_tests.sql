-- Skill tests. Run AFTER candidate_module.sql.
USE employer_module;

CREATE TABLE skill_tests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  skill_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  pass_percent TINYINT NOT NULL DEFAULT 60,
  time_limit_min SMALLINT NOT NULL DEFAULT 10,
  questions_per_attempt TINYINT NOT NULL DEFAULT 5,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (skill_id) REFERENCES skills(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE test_questions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  test_id INT NOT NULL,
  question TEXT NOT NULL,
  option_a VARCHAR(255) NOT NULL,
  option_b VARCHAR(255) NOT NULL,
  option_c VARCHAR(255) NOT NULL,
  option_d VARCHAR(255) NOT NULL,
  correct ENUM('A','B','C','D') NOT NULL,
  explanation VARCHAR(500) NULL,
  FOREIGN KEY (test_id) REFERENCES skill_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE test_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  candidate_id INT NOT NULL,
  test_id INT NOT NULL,
  question_ids JSON NOT NULL,
  answers JSON NULL,
  score_percent TINYINT NULL,
  passed TINYINT(1) NOT NULL DEFAULT 0,
  level_awarded TINYINT NOT NULL DEFAULT 0,
  status ENUM('In Progress','Completed','Expired') NOT NULL DEFAULT 'In Progress',
  started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  submitted_at DATETIME NULL,
  INDEX (candidate_id, test_id),
  FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
  FOREIGN KEY (test_id) REFERENCES skill_tests(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO skill_tests (skill_id,title) SELECT id,'PHP skill test' FROM skills WHERE name='PHP';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which function returns the number of elements in an array?','strlen()','count()','size()','length()','B','count() returns the number of elements in an array.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='PHP';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which operator compares both value and type?','==','===','=','!=','B','=== is the strict (identical) comparison operator.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='PHP';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which superglobal holds data from a form submitted with method="post"?','$_GET','$_REQUEST_DATA','$_POST','$_FORM','C','$_POST contains data sent in the HTTP POST body.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='PHP';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'What is the recommended way to prevent SQL injection?','addslashes()','Prepared statements with bound parameters','str_replace() on quotes','htmlspecialchars()','B','Prepared statements keep data separate from the SQL command.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='PHP';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which function creates a secure password hash?','md5()','sha1()','password_hash()','crypt_simple()','C','password_hash() uses a strong, salted algorithm such as bcrypt.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='PHP';
INSERT INTO skill_tests (skill_id,title) SELECT id,'MySQL skill test' FROM skills WHERE name='MySQL';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which clause filters groups after a GROUP BY?','WHERE','HAVING','LIMIT','ORDER BY','B','HAVING filters aggregated groups; WHERE filters rows before grouping.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='MySQL';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which join returns every row from the left table plus matching rows from the right?','INNER JOIN','CROSS JOIN','LEFT JOIN','SELF JOIN','C','LEFT JOIN keeps all left-table rows, with NULLs where nothing matches.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='MySQL';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which statement is used to speed up lookups on a column?','CREATE INDEX','CREATE VIEW','TRUNCATE','DROP TABLE','A','An index lets MySQL find rows without scanning the whole table.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='MySQL';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'What does COUNT(*) return?','The sum of a column','The number of rows','The number of columns','The largest value','B','COUNT(*) counts the rows in the result.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='MySQL';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which constraint uniquely identifies each row and cannot be NULL?','FOREIGN KEY','CHECK','PRIMARY KEY','DEFAULT','C','A primary key is unique and not null.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='MySQL';
INSERT INTO skill_tests (skill_id,title) SELECT id,'JavaScript skill test' FROM skills WHERE name='JavaScript';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which keyword declares a block-scoped variable that can be reassigned?','var','let','const','static','B','let is block scoped and reassignable; const cannot be reassigned.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='JavaScript';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'What does typeof null return?','"null"','"undefined"','"object"','"number"','C','This is a long-standing quirk of the language.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='JavaScript';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which array method adds an element to the end?','push()','pop()','shift()','unshift()','A','push() appends; unshift() adds to the start.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='JavaScript';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'What is the result of 5 === ''5''?','true','false','undefined','An error','B','=== does not convert types, so a number and a string differ.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='JavaScript';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which keyword pauses an async function until a promise settles?','yield','await','pause','wait','B','await suspends the async function until the promise resolves or rejects.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='JavaScript';
INSERT INTO skill_tests (skill_id,title) SELECT id,'Git skill test' FROM skills WHERE name='Git';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which command makes a local copy of a remote repository?','git copy','git clone','git fork','git init','B','git clone downloads the repository and its history.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='Git';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which command stages changes for the next commit?','git add','git push','git fetch','git stash','A','git add moves changes into the staging area.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='Git';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which command shows commit history?','git history','git status','git log','git diff','C','git log lists commits, newest first.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='Git';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'Which command creates and switches to a new branch named dev?','git branch dev','git checkout -b dev','git new dev','git merge dev','B','git checkout -b (or git switch -c) creates and switches in one step.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='Git';
INSERT INTO test_questions (test_id,question,option_a,option_b,option_c,option_d,correct,explanation) SELECT t.id,'git pull is roughly equivalent to:','fetch then merge','push then merge','clone then fetch','commit then push','A','git pull fetches remote changes and merges them into your branch.' FROM skill_tests t JOIN skills s ON s.id=t.skill_id WHERE s.name='Git';

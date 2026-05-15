-- ============================================================
-- TRUE OCCUPATION - Complete Database Schema
-- ============================================================
CREATE DATABASE IF NOT EXISTS true_occupation CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE true_occupation;

-- ADMINS
CREATE TABLE admins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    last_login TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- USERS
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(200) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('seeker','employer') NOT NULL,
    phone VARCHAR(20),
    location VARCHAR(150),
    profile_photo VARCHAR(255),
    is_premium TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    email_verified TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- EMPLOYERS
CREATE TABLE employers (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    company_name VARCHAR(200) NOT NULL,
    company_email VARCHAR(200),
    company_website VARCHAR(255),
    company_description TEXT,
    registration_id VARCHAR(100),
    industry VARCHAR(100),
    company_size VARCHAR(50),
    verification_doc VARCHAR(255),
    verification_status ENUM('none','pending','verified','rejected') DEFAULT 'none',
    verification_note TEXT,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- RESUMES
CREATE TABLE resumes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    resume_type ENUM('created','uploaded') DEFAULT 'created',
    full_name VARCHAR(150),
    email VARCHAR(200),
    phone VARCHAR(20),
    location VARCHAR(150),
    skills TEXT,
    education TEXT,
    experience TEXT,
    projects TEXT,
    summary TEXT,
    linkedin_url VARCHAR(255),
    github_url VARCHAR(255),
    uploaded_file VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- JOBS
CREATE TABLE jobs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employer_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    company VARCHAR(200) NOT NULL,
    location VARCHAR(150) NOT NULL,
    job_type ENUM('Full-time','Part-time','Contract','Internship','Remote') DEFAULT 'Full-time',
    salary_range VARCHAR(100),
    description TEXT NOT NULL,
    requirements TEXT,
    skills_required TEXT,
    experience_level ENUM('Entry','Mid','Senior','Lead') DEFAULT 'Entry',
    require_test TINYINT(1) DEFAULT 0,
    require_interview TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    applications_count INT DEFAULT 0,
    posted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- INTERVIEW QUESTIONS PER JOB
CREATE TABLE job_interview_questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    question TEXT NOT NULL,
    order_num INT DEFAULT 0,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- APPLICATIONS
CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    job_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    employer_id INT UNSIGNED NOT NULL,
    cover_letter TEXT,
    status ENUM('Applied','Shortlisted','Interview','Rejected','Hired') DEFAULT 'Applied',
    match_percentage TINYINT UNSIGNED DEFAULT 0,
    readiness_score DECIMAL(5,2) DEFAULT 0,
    employer_note TEXT,
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_app (job_id, user_id),
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (employer_id) REFERENCES employers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- APTITUDE QUESTIONS
CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category ENUM('Numerical','Logical','Verbal','Coding') NOT NULL,
    question TEXT NOT NULL,
    option_a VARCHAR(500) NOT NULL,
    option_b VARCHAR(500) NOT NULL,
    option_c VARCHAR(500) NOT NULL,
    option_d VARCHAR(500) NOT NULL,
    correct_answer ENUM('a','b','c','d') NOT NULL,
    difficulty ENUM('Easy','Medium','Hard') DEFAULT 'Medium',
    explanation TEXT,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB;

-- APTITUDE RESULTS
CREATE TABLE aptitude_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    category VARCHAR(50),
    total_questions INT NOT NULL,
    correct_answers INT NOT NULL,
    score_percentage DECIMAL(5,2) NOT NULL,
    time_taken INT DEFAULT 0,
    attempt_number INT DEFAULT 1,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- INTERVIEW RESULTS
CREATE TABLE interview_results (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED,
    question TEXT NOT NULL,
    user_answer TEXT,
    ai_rating ENUM('Strong','Good','Average','Weak','Poor') DEFAULT 'Average',
    ai_score TINYINT UNSIGNED DEFAULT 5,
    communication_score TINYINT UNSIGNED DEFAULT 5,
    confidence_score TINYINT UNSIGNED DEFAULT 5,
    ai_feedback TEXT,
    ai_suggestions TEXT,
    discipline_score TINYINT UNSIGNED DEFAULT 100,
    head_violations INT DEFAULT 0,
    tab_switches INT DEFAULT 0,
    interview_type ENUM('standard','proctored') DEFAULT 'standard',
    attempt_number INT DEFAULT 1,
    taken_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- JOB FIT SCORES
CREATE TABLE job_fit_scores (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL UNIQUE,
    aptitude_score DECIMAL(5,2) DEFAULT 0,
    interview_score DECIMAL(5,2) DEFAULT 0,
    skill_match_score DECIMAL(5,2) DEFAULT 0,
    total_fit_score DECIMAL(5,2) DEFAULT 0,
    readiness_score DECIMAL(5,2) DEFAULT NULL,
    readiness_breakdown TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- INTERVIEW SESSIONS (one row per mock interview attempt; links result rows via attempt_number)
CREATE TABLE interview_sessions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    job_id INT UNSIGNED NULL,
    attempt_number INT UNSIGNED NOT NULL,
    target_role VARCHAR(200) NULL,
    avg_ai_score DECIMAL(5,2) NULL,
    questions_count SMALLINT UNSIGNED NULL,
    summary_json TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE SET NULL,
    KEY idx_int_sess_user_open (user_id, completed_at)
) ENGINE=InnoDB;

-- NOTIFICATIONS
CREATE TABLE notifications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('job_match','application_update','system','interview','test','email') DEFAULT 'system',
    is_read TINYINT(1) DEFAULT 0,
    action_url VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- BOOKS
CREATE TABLE books (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    author VARCHAR(200),
    description TEXT,
    cover_image VARCHAR(255),
    file_path VARCHAR(500),
    external_url VARCHAR(500),
    category VARCHAR(100),
    skill_tags VARCHAR(500),
    is_premium TINYINT(1) DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- EMAIL LOG
CREATE TABLE email_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    from_email VARCHAR(200),
    to_email VARCHAR(200) NOT NULL,
    subject VARCHAR(500) NOT NULL,
    body TEXT,
    status ENUM('sent','failed') DEFAULT 'sent',
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- INDEXES
CREATE INDEX idx_jobs_active ON jobs(is_active, posted_at DESC);
CREATE INDEX idx_applications_user ON applications(user_id);
CREATE INDEX idx_applications_job ON applications(job_id);
CREATE INDEX idx_notifications_user ON notifications(user_id, is_read);
CREATE INDEX idx_questions_cat ON questions(category, is_active);

-- ============================================================
-- SEED DATA
-- ============================================================

-- Admin (password: Admin@1234)
INSERT INTO admins (name, email, password) VALUES
('Super Admin', 'admin@trueocc.com', '$2y$12$LcZ.kNe1.WUPvHfEHMFnTuXLd4n2I5Khy4T7b9WX5z.Jb6s6Q1RGm');

-- Questions - Numerical
INSERT INTO questions (category, question, option_a, option_b, option_c, option_d, correct_answer, difficulty, explanation) VALUES
('Numerical','If a = 5 and b = 3, what is a² - b²?','16','18','14','20','a','Easy','a²-b² = 25-9 = 16'),
('Numerical','What is 15% of 240?','34','36','38','32','b','Easy','240 × 0.15 = 36'),
('Numerical','A train travels 90 km/h for 2.5 hours. Distance?','225 km','200 km','250 km','180 km','a','Easy','90 × 2.5 = 225'),
('Numerical','If 20% of X = 80, then X = ?','300','350','400','450','c','Easy','X = 80/0.20 = 400'),
('Numerical','Sequence: 2, 6, 12, 20, 30... Next?','40','42','44','46','b','Easy','Differences: 4,6,8,10,12 → 30+12=42'),
('Numerical','LCM of 12 and 18?','24','36','48','72','b','Easy','LCM(12,18) = 36'),
('Numerical','A shopkeeper buys at ₹800, sells at ₹1000. Profit %?','20%','25%','22%','18%','b','Easy','200/800 × 100 = 25%'),
('Numerical','Simple interest on ₹5000 at 8% for 3 years?','₹1000','₹1200','₹1500','₹800','b','Medium','SI = 5000×8×3/100 = 1200'),
('Numerical','√(0.0081) = ?','0.09','0.9','0.009','0.0009','a','Medium','√0.0081 = 0.09'),
('Numerical','Average of 10, 20, 30, 40, 50?','25','30','35','40','b','Easy','Sum=150, Avg=30'),

-- Logical
('Logical','All roses are flowers. All flowers are plants. Conclusion?','All plants are roses','All roses are plants','Some roses are plants','None','b','Medium','Transitive syllogism'),
('Logical','Clock shows 3:15. Angle between hands?','0°','7.5°','15°','30°','b','Hard','Hour at 97.5°, Minute at 90°'),
('Logical','A is north of B. C is east of B. A is ___ of C?','North-East','North-West','South-East','South-West','b','Medium','Draw positions: A is NW of C'),
('Logical','4:16 :: 7:?','42','49','56','21','b','Easy','4²=16, 7²=49'),
('Logical','Odd one out: 13, 17, 23, 25, 29','25','13','17','29','a','Easy','25=5×5, not prime'),
('Logical','Complete: B2, D4, F6, H8, ?','J10','I9','K11','J9','a','Easy','Letters +2, numbers +2'),
('Logical','If + means ×, × means ÷, then 4 + 3 × 6 = ?','2','12','6','8','a','Hard','4×3÷6 = 2'),
('Logical','Mirror image of TIME is?','EMIT','EMIT','SMIT','OMIT','a','Easy','Letters reversed'),
('Logical','In a row, A is 7th from left, B is 11th from right, 4 between them. Total?','22','20','21','23','a','Hard','7+4+11=22'),
('Logical','Analogy: Doctor:Hospital :: Teacher:?','School','Student','Book','Office','a','Easy','Doctor works in Hospital, Teacher in School'),

-- Verbal
('Verbal','Synonym of ABUNDANT?','Scarce','Plentiful','Limited','Rare','b','Easy','Abundant = Plentiful'),
('Verbal','Antonym of TRANSPARENT?','Clear','Obvious','Opaque','Bright','c','Easy','Transparent ↔ Opaque'),
('Verbal','Fill: She _____ to market yesterday.','go','went','goes','going','b','Easy','Past tense = went'),
('Verbal','Correct spelling?','Accomodation','Accommodation','Acommodation','Accomodaton','b','Easy','Double c and double m'),
('Verbal','"Break the ice" means?','Cause harm','Start a fight','Initiate conversation','Destroy something','c','Easy','Common idiom'),
('Verbal','Passive voice: "He wrote the letter."','Letter wrote by him','Letter was written by him','Letter has been written','Letter is written','b','Medium','Correct passive voice'),
('Verbal','One word: One who repairs locks?','Carpenter','Blacksmith','Locksmith','Plumber','c','Easy','Locksmith'),
('Verbal','Author:Book :: Sculptor:?','Canvas','Museum','Statue','Chisel','c','Easy','Sculptor creates statue'),
('Verbal','Correct sentence?','Neither boys have done work','Neither boy has done his work','Neither boys has done work','Neither boy have done work','b','Hard','Neither takes singular'),
('Verbal','Antonym of EPHEMERAL?','Temporary','Transient','Permanent','Fleeting','c','Medium','Ephemeral = temporary, antonym = permanent'),

-- Coding
('Coding','Output of: print(2**3) in Python?','6','8','9','23','b','Easy','2**3 = 8'),
('Coding','Time complexity of binary search?','O(n)','O(n²)','O(log n)','O(1)','c','Easy','Halves each time'),
('Coding','Which uses LIFO?','Queue','Stack','Tree','Graph','b','Easy','Stack = Last In First Out'),
('Coding','SELECT all from students?','GET * FROM students','SELECT ALL FROM students','SELECT * FROM students','FETCH * FROM students','c','Easy','Standard SQL'),
('Coding','Java keyword to create object?','create','new','object','make','b','Easy','new keyword'),
('Coding','HTML stands for?','Hyper Text Markup Language','High Tech Modern Language','Hyper Text Modern Links','High Text Markup','a','Easy','HTML full form'),
('Coding','Best average case sort O(n log n)?','Bubble','Insertion','Quick','Selection','c','Medium','QuickSort'),
('Coding','typeof null in JavaScript?','null','undefined','object','string','c','Medium','JS quirk'),
('Coding','CSS stands for?','Cascading Style Sheets','Creative Style Sheets','Computed Style Syntax','Colorful Styles','a','Easy','CSS full form'),
('Coding','REST API update method?','GET','POST','PUT','DELETE','c','Medium','PUT updates resource');

-- Books
INSERT INTO books (title, author, description, category, skill_tags, is_premium, external_url) VALUES
('Clean Code','Robert C. Martin','Write maintainable, clean code — a must-read for every developer.','Programming','coding,software,best practices',0,'https://www.investigatii.md/uploads/resurse/Clean_Code.pdf'),
('The Pragmatic Programmer','David Thomas','From journeyman to master — pragmatic tips for software developers.','Programming','coding,career,productivity',0,'https://github.com/rajucs/Book-For-Programmers/blob/master/the-pragmatic-programmer.pdf'),
('You Don''t Know JS','Kyle Simpson','Deep dive into JavaScript mechanics and behavior.','JavaScript','javascript,web,frontend',0,'https://github.com/getify/You-Dont-Know-JS'),
('Python Crash Course','Eric Matthes','Fast-paced introduction to Python for beginners.','Python','python,beginner,programming',0,'https://ehmatthes.github.io/pcc/'),
('Cracking the Coding Interview','Gayle McDowell','189 interview questions with solutions for FAANG prep.','Interview','coding,interview,algorithms',1,'#'),
('System Design Interview','Alex Xu','Insider guide to cracking system design questions.','Interview','system design,architecture',1,'#'),
('Deep Learning','Ian Goodfellow','Comprehensive textbook on deep learning.','AI/ML','ai,machine learning',1,'#'),
('The Design of Everyday Things','Don Norman','User-centered design principles that changed the industry.','Design','ux,design,product',0,'https://ia902300.us.archive.org/4/items/thedesignofeverydaythingsbydonnorman/the-design-of-everyday-things-by-don-norman.pdf');

-- Demo employer user (password: password)
INSERT INTO users (name, email, password, role) VALUES
('TechCorp HR', 'hr@techcorp.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer'),
('Alice Johnson', 'alice@email.com', '$2y$12$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'seeker');

INSERT INTO employers (user_id, company_name, company_email, registration_id, industry, verification_status) VALUES
(1, 'TechCorp Inc.', 'hr@techcorp.com', 'TC2024001', 'Technology', 'verified');

INSERT INTO jobs (employer_id, user_id, title, company, location, job_type, salary_range, description, skills_required, experience_level, require_test, require_interview) VALUES
(1, 1, 'Frontend Developer', 'TechCorp Inc.', 'Bangalore', 'Full-time', '₹8-12 LPA', 'Build modern web applications using React and TypeScript. Work with cross-functional teams.', 'React,JavaScript,CSS,HTML,TypeScript', 'Mid', 1, 1),
(1, 1, 'Backend Engineer', 'TechCorp Inc.', 'Remote', 'Full-time', '₹10-15 LPA', 'Design scalable APIs and microservices using Node.js and Python.', 'Node.js,Python,MongoDB,REST API,Docker', 'Mid', 1, 0),
(1, 1, 'UI/UX Designer', 'TechCorp Inc.', 'Mumbai', 'Full-time', '₹6-9 LPA', 'Create beautiful, intuitive interfaces from wireframes to final pixels.', 'Figma,UI Design,Prototyping,User Research', 'Entry', 0, 1),
(1, 1, 'Data Analyst', 'TechCorp Inc.', 'Hyderabad', 'Part-time', '₹4-6 LPA', 'Analyze business data and generate insights for decision making.', 'Python,SQL,Excel,Power BI,Statistics', 'Entry', 1, 1);

-- Default interview questions for job 1
INSERT INTO job_interview_questions (job_id, question, order_num) VALUES
(1, 'Tell me about yourself and your experience with React.', 1),
(1, 'Describe a challenging frontend project you worked on.', 2),
(1, 'How do you handle state management in large React apps?', 3),
(1, 'What is your approach to responsive design?', 4),
(1, 'Where do you see yourself in 3 years?', 5);

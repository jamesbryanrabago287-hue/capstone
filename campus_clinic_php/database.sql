CREATE DATABASE IF NOT EXISTS campus_clinic CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE campus_clinic;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    status ENUM('Active','Inactive') NOT NULL DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE medicines (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    category VARCHAR(100) NOT NULL,
    unit VARCHAR(50) NOT NULL DEFAULT 'tablet',
    supplier VARCHAR(150),
    batch_number VARCHAR(100),
    reorder_group_id INT NULL,
    KEY idx_medicine_reorder_group (reorder_group_id),
    quantity INT NOT NULL DEFAULT 0,
    reorder_point INT NOT NULL DEFAULT 10,
    expiration_date DATE NOT NULL,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    medicine_id INT NOT NULL,
    type ENUM('Stock In','Stock Out','Disposal','Adjustment In','Adjustment Out') NOT NULL,
    balance_before INT NULL,
    balance_after INT NULL,
    request_token VARCHAR(64) NULL UNIQUE,
    quantity INT NOT NULL,
    reference_no VARCHAR(100),
    notes TEXT,
    user_id INT NOT NULL,
    transaction_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (medicine_id) REFERENCES medicines(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_no VARCHAR(50) NOT NULL UNIQUE,
    full_name VARCHAR(120) NOT NULL,
    college VARCHAR(120),
    course VARCHAR(120),
    year_level VARCHAR(30),
    contact_no VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE chatbot_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_no VARCHAR(50),
    student_name VARCHAR(120),
    symptoms TEXT NOT NULL,
    assessment TEXT NOT NULL,
    advice TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users(username,password,full_name,role) VALUES
('admin', '$2y$10$b9Hd4mjF7FB.UszRJ2cmxOZEwB0AZoqoK4AdYCwGomFYxJCqNHJui', 'Clinic Administrator', 'admin'),
('staff', '$2y$10$Msgu0S/Y.IxBytFSUS4R1Oq96Kmal1gkClam.8gXrC8DzjPw7K7Ii', 'Clinic Staff', 'staff');

INSERT INTO medicines(name,category,unit,supplier,batch_number,quantity,reorder_point,expiration_date,unit_cost) VALUES
('Paracetamol 500mg','Analgesic','tablet','Sample Supplier','PAR-001',100,20,'2027-12-31',2.50),
('Amoxicillin 500mg','Antibiotic','capsule','Sample Supplier','AMX-001',15,20,'2027-08-31',8.00),
('Mefenamic Acid 500mg','Analgesic','tablet','Sample Supplier','MEF-001',60,15,'2027-06-30',4.00),
('Oral Rehydration Salts','Rehydration','sachet','Sample Supplier','ORS-001',10,15,'2027-03-31',12.00);

-- Staff verified links are separate from self-reported submissions.
CREATE TABLE IF NOT EXISTS chatbot_reviews (session_id INT PRIMARY KEY, student_id INT NULL, reviewed_by INT NULL, reviewed_at DATETIME NULL, KEY idx_chat_student (student_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Basic staff consultations linked to pre-assessments.
CREATE TABLE IF NOT EXISTS clinic_consultations (
 id INT AUTO_INCREMENT PRIMARY KEY, assessment_id INT NULL UNIQUE, student_id INT NOT NULL,
 start_token VARCHAR(64) NULL UNIQUE,
 student_no_snapshot VARCHAR(50) NOT NULL, student_name_snapshot VARCHAR(120) NOT NULL,
 recorded_by INT NULL, recorded_by_name VARCHAR(120) NOT NULL,
 status ENUM('In progress','Completed') NOT NULL DEFAULT 'In progress', findings TEXT NULL, next_steps TEXT NULL,
 version INT NOT NULL DEFAULT 1, outcome VARCHAR(40) NOT NULL DEFAULT '', started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 completed_at DATETIME NULL, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 KEY idx_consultation_student (student_id), KEY idx_consultation_status (status),
 CONSTRAINT fk_clinic_consult_assessment FOREIGN KEY (assessment_id) REFERENCES chatbot_sessions(id) ON DELETE RESTRICT,
 CONSTRAINT fk_clinic_consult_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE RESTRICT,
 CONSTRAINT fk_clinic_consult_recorder FOREIGN KEY (recorded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Staff-recorded dispensing linked to a verified consultation and stock movement.
CREATE TABLE IF NOT EXISTS clinic_dispenses (
 id INT AUTO_INCREMENT PRIMARY KEY, consultation_id INT NOT NULL, transaction_id INT NOT NULL UNIQUE,
 medicine_name VARCHAR(150) NOT NULL, batch_number VARCHAR(100) NOT NULL, unit VARCHAR(50) NOT NULL,
 expiration_date DATE NOT NULL, issued_by_name VARCHAR(120) NOT NULL,
 KEY idx_dispense_visit (consultation_id),
 CONSTRAINT fk_dispense_visit FOREIGN KEY (consultation_id) REFERENCES clinic_consultations(id) ON DELETE RESTRICT,
 CONSTRAINT fk_dispense_transaction FOREIGN KEY (transaction_id) REFERENCES transactions(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Explicit planning groups: original batch stock and movement IDs are retained.
CREATE TABLE IF NOT EXISTS medicine_reorder_groups(id INT AUTO_INCREMENT PRIMARY KEY,reorder_point INT NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

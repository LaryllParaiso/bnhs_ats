# Product Requirements Document (PRD)
## QR Code-Based Attendance Management System

**Version:** 1.0  
**Last Updated:** January 28, 2026  
**Project Type:** School Attendance Tracking System  
**Development Stack:** XAMPP (Apache, MySQL, PHP), JavaScript, Python, HTML/CSS/Bootstrap

---

## 1. Overview

### 1.1 Product Vision
A web-based attendance management system that automates student attendance tracking using QR code technology. The system eliminates manual roll calls, reduces human error, and provides real-time attendance monitoring for educational institutions.

### 1.2 Problem Statement
Traditional attendance systems require manual entry, are time-consuming, prone to proxy attendance, and lack real-time reporting capabilities. Teachers spend valuable class time on roll calls, and attendance data is often delayed or inaccurate.

### 1.3 Solution
An intelligent QR code-based system where:
- Students register once and receive a universal QR code for all classes
- Teachers create class schedules and start attendance sessions
- Students scan their QR code, and the system automatically identifies the correct subject based on current day/time
- Real-time attendance tracking with automated Present/Late/Absent status
- Comprehensive reporting and data export capabilities

### 1.4 Target Users
- **Primary:** Teachers (full system access with CRUD operations)
- **Secondary:** Students (registration and QR generation only)
- **Tertiary:** School administrators (attendance reports and analytics)

### 1.5 Success Criteria
- 95%+ attendance accuracy rate
- <5 seconds average scan time per student
- 90%+ teacher adoption rate within first semester
- Zero paper-based attendance sheets
- Complete attendance data available in real-time

---

## 2. Core Features

### 2.1 Teacher Module (Authenticated Access)

#### 2.1.1 Authentication & Authorization
- **Registration:**
  - Teacher account creation with approval workflow
  - Fields: Full Name, Employee ID, Email, Password, Department
  - Password hashing using bcrypt (minimum cost factor: 12)
  - Email verification (optional)
  
- **Login:**
  - Secure session management with PHP sessions
  - Session timeout: 30 minutes of inactivity
  - Password strength requirements: 8+ chars, uppercase, lowercase, number, special char
  - Account lockout after 5 failed attempts (15-minute cooldown)
  
- **Role-Based Access Control (RBAC):**
  - Teacher role: CRUD on students, schedules, attendance
  - Admin role: All teacher permissions + user management
  - Principle of Least Privilege enforced

#### 2.1.2 Student Management (Full CRUD)

**Create (Registration):**
- Manual single student registration
- Bulk CSV import (up to 500 students per batch)
- Required fields: LRN (12 digits, unique), Full Name, Grade, Section
- Optional fields: Contact Number, Email
- Auto-enrollment to selected schedules during registration
- Immediate QR code generation option
- Input validation: LRN uniqueness check, name format validation
- Duplicate detection with merge suggestion

**Read (View/Search):**
- Paginated student list (25 per page)
- Multi-criteria search: LRN, Name, Grade, Section
- Advanced filters: QR status, Enrollment status, Attendance rate
- Student profile modal: Full details, enrolled subjects, QR code, attendance summary
- Export student list to CSV/Excel
- Sort by: Name (A-Z), LRN, Grade, Last Modified

**Update (Edit):**
- Edit student information: Name, Grade, Section, Contact
- Modify enrolled subjects (add/remove from schedules)
- Grade promotion workflow (bulk update grade levels)
- QR code regeneration on info change
- Change log/audit trail for all modifications
- Validation: Prevent duplicate LRNs, validate grade-section combinations

**Delete (Archive/Remove):**
- Soft delete (default): Mark as Inactive, preserve attendance history
- Hard delete: Permanent removal with confirmation (Admin only)
- Pre-deletion check: Display attendance record count
- Option to transfer attendance records before deletion
- Cascade handling: Remove from all schedules, invalidate QR codes
- Bulk archive for graduated students

**Additional Operations:**
- QR Code Management:
  - Generate individual QR codes
  - Bulk QR generation for entire class (ZIP download)
  - Print-ready PDF format (4-6 QR codes per page)
  - Regenerate QR (invalidates previous token)
  - QR security token rotation (semester-based)
- Attendance Override:
  - Manual mark Present/Late/Absent for specific date
  - Excuse absence with reason note
  - Attendance correction with audit trail

#### 2.1.3 Schedule Management (Full CRUD)

**Create (Add Schedule):**
- Input fields:
  - Subject Name (dropdown or custom entry)
  - Grade Level (7-12)
  - Section (A-Z or custom)
  - Day of Week (Monday-Friday, multi-select for recurring)
  - Time Slot: Start Time, End Time (15-min increments)
  - Room/Location (optional)
  - Semester (1st/2nd), School Year (2024-2025)
- Validation:
  - Conflict detection: Prevent overlapping schedules for same teacher
  - Logical time check: End time > Start time
  - Duration limits: 30 min minimum, 4 hours maximum
  - Duplicate prevention: Same subject/grade/section/time
- Auto-calculate duration
- Option to duplicate schedule for multiple sections

**Read (View Schedules):**
- List view: Table with all schedules, sortable columns
- Calendar view: Weekly grid showing all classes
- Filter by: Day, Grade, Section, Subject, Status (Active/Inactive)
- Schedule details: Enrolled students count, attendance record count
- Quick actions: Edit, Delete, Activate/Deactivate, View Students
- Color-coding: Active (green), Inactive (gray), Conflict (red)

**Update (Edit Schedule):**
- Modify all schedule fields
- Impact analysis before save:
  - Show enrolled students count
  - Show existing attendance records
  - Warn if time change affects historical data
- Validation: Re-run conflict detection
- Options for time changes:
  - Keep old attendance records as-is (recommended)
  - Create new schedule, archive old one
- Cannot edit if active attendance session running

**Delete (Remove Schedule):**
- Pre-deletion checks:
  - Enrolled students count (prompt to transfer)
  - Attendance records count (archive warning)
  - Active session check (must end first)
- Options:
  - Archive (soft delete): Preserve all data, mark inactive
  - Transfer students to another schedule
  - Hard delete with confirmation (removes all related data)
- Cascade delete: Remove from student_schedules, mark attendance records

**Additional Operations:**
- Activate/Deactivate toggle
- Bulk schedule creation (copy schedule for multiple sections)
- Import schedule from previous semester
- Schedule conflict resolution wizard

#### 2.1.4 Attendance Session Management

**Start Session:**
- Prerequisites:
  - Select active schedule from dropdown
  - Verify current day/time matches schedule (±15 min grace period)
  - Ensure no other active session running
- QR Scanner Interface:
  - Camera access with browser permission request
  - Real-time video feed with scan region highlight
  - Live student list (updates as students scan)
  - Running counts: Total enrolled, Present, Late, Absent
  - Manual attendance option (for camera failures)
- Scan validation process:
  1. Decode QR code
  2. Verify student exists (LRN lookup)
  3. Check security token validity
  4. Confirm student enrolled in this schedule
  5. Prevent duplicate scan (same day, same subject)
  6. Determine status: Present (0-15 min) or Late (15+ min)
  7. Record timestamp and save

**During Session:**
- Real-time attendance dashboard
- Student scan notifications (name, time, status)
- Duplicate scan alerts
- Invalid QR warnings
- Pause/Resume session option
- Manual attendance entry for absent camera

**End Session:**
- Auto-mark absent for non-scanned enrolled students
- Attendance summary review
- Option to manually adjust records before finalization
- Finalize and save to database
- Session report generation (PDF/Excel)

#### 2.1.5 Attendance Records & Reporting

**View Records:**
- Date range selector
- Filter by: Subject, Grade, Section, Status (Present/Late/Absent)
- Student-level view: Individual attendance history
- Class-level view: All students for specific session
- Attendance percentage calculation
- Export options: PDF, Excel, CSV

**Reports:**
- Daily Attendance Report: All classes for selected date
- Weekly Summary: 5-day attendance overview
- Monthly Report: Full month with statistics
- Individual Student Report: Complete attendance history
- Class Performance Report: Section-wise attendance trends
- Late Arrival Analysis: Students frequently late
- Absence Trends: Identify at-risk students

**Analytics:**
- Attendance rate by subject
- Best/Worst performing classes
- Peak late arrival times
- Day-of-week attendance patterns
- Comparative analysis across sections

### 2.2 Student Module (No Authentication)

#### 2.2.1 Student Registration
- One-time registration (no login required)
- Registration form:
  - LRN (12-digit validation)
  - Full Name
  - Grade Level (dropdown)
  - Section (dropdown)
  - Contact Number (optional, format validation)
  - Email (optional, format validation)
- Schedule selection:
  - Display available schedules for student's grade & section
  - Multi-select checkboxes
  - Show schedule details: Subject, Teacher, Day, Time
  - Visual indicators for conflicting times
- Submit and redirect to QR generation

#### 2.2.2 QR Code Generation
- Automatic generation after registration
- QR code contains:
  ```json
  {
    "lrn": "123456789012",
    "name": "Juan Dela Cruz",
    "grade": "11",
    "section": "ICT-A",
    "token": "unique_security_hash"
  }
  ```
- QR code display:
  - Student name and LRN below QR
  - Instructions for saving
  - Download button (PNG format, 512x512px)
  - Print option
- Universal QR: Works for all enrolled subjects
- No expiration (valid until student info changes)

#### 2.2.3 QR Code Regeneration
- Public page (no login): Enter LRN to regenerate
- Verification: LRN must exist in database
- New security token generated
- Old QR becomes invalid
- Download new QR code

### 2.3 System Intelligence (Automated Features)

#### 2.3.1 Smart Attendance Detection
- Student scans QR → System automatically:
  1. Extracts LRN from QR code
  2. Queries student record
  3. Gets current datetime (day, time)
  4. Finds student's schedules for current day
  5. Matches current time to schedule time range
  6. Identifies active subject
  7. Validates enrollment
  8. Checks for duplicate scan
  9. Determines Present/Late status
  10. Records attendance with timestamp

**Matching Algorithm:**
```sql
SELECT s.*, ss.enrollment_id 
FROM schedules s
JOIN student_schedules ss ON s.schedule_id = ss.schedule_id
WHERE ss.student_id = ?
  AND s.day_of_week = DAYNAME(NOW())
  AND CURRENT_TIME() BETWEEN s.start_time AND s.end_time
  AND s.status = 'Active'
  AND ss.status = 'Active'
LIMIT 1
```

#### 2.3.2 Status Determination Logic
- **Present:** Scan time <= (start_time + 15 minutes)
- **Late:** Scan time > (start_time + 15 minutes) AND <= end_time
- **Absent:** No scan by end of session OR scan after end_time
- Grace period configurable per institution (default: 15 min)

#### 2.3.3 Duplicate Prevention
- Daily attendance lock: One scan per subject per day
- Check query:
```sql
SELECT COUNT(*) FROM attendance 
WHERE student_id = ? 
  AND schedule_id = ? 
  AND DATE(date) = CURDATE()
```
- If count > 0: Display "Attendance already recorded at [timestamp]"
- Prevents proxy attendance and scanner errors

#### 2.3.4 Edge Case Handling
- **No active class found:** "You have no scheduled class at this time"
- **Multiple conflicting schedules:** Alert teacher to resolve conflict
- **QR token invalid:** "Invalid QR code. Please regenerate."
- **Student not enrolled:** "You are not enrolled in this subject"
- **Scan after class ends:** Configurable (mark Absent or allow Very Late)

---

## 3. Technical Stack

### 3.1 Development Environment
**XAMPP Components:**
- Apache Web Server 2.4.x (HTTP server)
- MySQL/MariaDB 10.4.x (Database)
- PHP 8.1.x (Server-side scripting)
- phpMyAdmin (Database management UI)

**Required Modules:**
- PHP Extensions: mysqli, PDO, mbstring, gd (for QR generation), openssl
- Apache Modules: mod_rewrite, mod_headers

### 3.2 Frontend Technologies

**HTML5:**
- Semantic markup
- Canvas API (for QR code rendering)
- Video API (for webcam access)
- Local Storage API (session management)

**CSS Framework:**
- Bootstrap 5.3.x
  - Documentation: https://getbootstrap.com/docs/5.3/
  - Responsive grid system
  - Pre-built components
  - Mobile-first design
- Custom CSS for branding

**JavaScript:**
- Vanilla JavaScript (ES6+)
- AJAX (Fetch API) for asynchronous requests
- No heavy framework dependencies (keep it lightweight)

**Key Libraries:**

1. **QR Code Generation (Client-side):**
   - **qrcode.js** (MIT License)
   - Documentation: https://davidshimjs.github.io/qrcodejs/
   - Usage:
   ```javascript
   new QRCode(document.getElementById("qrcode"), {
     text: JSON.stringify(studentData),
     width: 512,
     height: 512
   });
   ```

2. **QR Code Scanning:**
   - **html5-qrcode** (Apache 2.0)
   - Documentation: https://github.com/mebjas/html5-qrcode
   - Features: Webcam scanning, file upload scanning
   - Usage:
   ```javascript
   const scanner = new Html5QrcodeScanner("reader", { 
     fps: 10, 
     qrbox: 250 
   });
   scanner.render(onScanSuccess);
   ```

3. **Data Tables:**
   - **DataTables** (MIT License)
   - Documentation: https://datatables.net/
   - Features: Sorting, searching, pagination, AJAX loading
   - Usage:
   ```javascript
   $('#attendance-table').DataTable({
     ajax: 'fetch_records.php',
     columns: [
       { title: "Name", data: "name" },
       { title: "LRN", data: "lrn" }
     ]
   });
   ```

4. **Charts:**
   - **Chart.js** (MIT License)
   - Documentation: https://www.chartjs.org/
   - For attendance analytics and reports

5. **Admin Dashboard:**
   - **AdminLTE** (MIT License)
   - Documentation: https://adminlte.io/docs/3.0/
   - Bootstrap-based admin template

### 3.3 Backend Technologies

**PHP 8.1.x:**
- Object-Oriented Programming (SOLID Principles)
- MVC Architecture (Model-View-Controller)
- PDO for database access (prepared statements)
- Session management for authentication

**Python 3.10+ (Optional Utilities):**
- **chillerlan/php-qrcode** alternative
- Bulk operations scripting
- Data import/export automation

**Server-side Libraries:**

1. **PHP QR Code (Server-side generation):**
   - **chillerlan/php-qrcode** (MIT License)
   - Documentation: https://github.com/chillerlan/php-qrcode
   - Installation: `composer require chillerlan/php-qrcode`
   - Usage:
   ```php
   use chillerlan\QRCode\QRCode;
   echo (new QRCode())->render($data);
   ```

2. **PHP Authentication:**
   - **delight-im/PHP-Auth** (MIT License)
   - Documentation: https://github.com/delight-im/PHP-Auth
   - Features: Registration, login, sessions, roles, password reset
   - Installation: `composer require delight-im/auth`

3. **Excel Export:**
   - **PhpSpreadsheet** (MIT License)
   - Documentation: https://phpspreadsheet.readthedocs.io/
   - Installation: `composer require phpoffice/phpspreadsheet`

4. **PDF Generation:**
   - **TCPDF** (LGPL)
   - Documentation: https://tcpdf.org/
   - Installation: `composer require tecnickcom/tcpdf`

### 3.4 Database: MySQL 8.0.x

**Storage Engine:** InnoDB (supports ACID transactions, foreign keys)

**Character Set:** utf8mb4 (full Unicode support, including emojis)

**Collation:** utf8mb4_unicode_ci (case-insensitive, accent-sensitive)

**ACID Compliance:**
- **Atomicity:** Transactions are all-or-nothing
- **Consistency:** Database remains in valid state
- **Isolation:** Concurrent transactions don't interfere
- **Durability:** Committed data persists through crashes

**Transaction Example:**
```php
$pdo->beginTransaction();
try {
    // Insert student
    $stmt1 = $pdo->prepare("INSERT INTO students ...");
    $stmt1->execute([...]);
    
    // Enroll in schedules
    $stmt2 = $pdo->prepare("INSERT INTO student_schedules ...");
    $stmt2->execute([...]);
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### 3.5 Security Stack

**Frontend Security:**
- Input validation (client-side + server-side)
- XSS prevention: HTML encoding/escaping
- CSRF tokens for all forms
- Content Security Policy (CSP) headers
- Secure cookie flags: HttpOnly, Secure, SameSite

**Backend Security:**
- Password hashing: `password_hash()` with bcrypt (cost 12)
- Prepared statements (100% of queries)
- SQL injection prevention
- Session hijacking prevention: Regenerate session ID on login
- Rate limiting: 5 login attempts per 15 minutes
- HTTPS enforcement (for production)

**Database Security:**
- Least privilege principle: Separate DB users for read/write
- No root access from application
- Encrypted connections (SSL/TLS)
- Regular backups (daily automated)
- Audit logging for sensitive operations

**QR Code Security:**
- Unique security token per student (SHA-256 hash)
- Token rotation on regeneration
- Token expiration on student deactivation
- Server-side validation (never trust client data)

---

## 4. Data Requirements

### 4.1 Database Schema (3rd Normal Form - 3NF)

**3NF Compliance Rules:**
1. ✓ All attributes depend on the primary key (1NF)
2. ✓ No partial dependencies (2NF)
3. ✓ No transitive dependencies (3NF)

**Normalized Tables:**

#### 4.1.1 teachers
```sql
CREATE TABLE teachers (
    teacher_id INT AUTO_INCREMENT PRIMARY KEY,
    employee_id VARCHAR(20) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    department VARCHAR(50),
    status ENUM('Active', 'Inactive') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3NF Analysis:**
- teacher_id → all attributes (primary key dependency)
- No partial dependencies (single-column PK)
- No transitive dependencies (no attribute depends on non-key attribute)

#### 4.1.2 students
```sql
CREATE TABLE students (
    student_id INT AUTO_INCREMENT PRIMARY KEY,
    lrn VARCHAR(12) UNIQUE NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    grade_level TINYINT NOT NULL CHECK (grade_level BETWEEN 7 AND 12),
    section VARCHAR(20) NOT NULL,
    contact_number VARCHAR(15),
    email VARCHAR(100),
    qr_token VARCHAR(64) UNIQUE NOT NULL,
    status ENUM('Active', 'Inactive', 'Graduated') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_lrn (lrn),
    INDEX idx_grade_section (grade_level, section),
    INDEX idx_status (status),
    UNIQUE KEY unique_student (lrn)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3NF Analysis:**
- student_id → all attributes
- grade_level and section are independent (no grade → section dependency)
- qr_token is derived from student data but stored for performance (acceptable denormalization)

#### 4.1.3 schedules
```sql
CREATE TABLE schedules (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    teacher_id INT NOT NULL,
    subject_name VARCHAR(100) NOT NULL,
    grade_level TINYINT NOT NULL CHECK (grade_level BETWEEN 7 AND 12),
    section VARCHAR(20) NOT NULL,
    day_of_week ENUM('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday') NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    room VARCHAR(50),
    semester ENUM('1st', '2nd') NOT NULL,
    school_year VARCHAR(9) NOT NULL, -- Format: 2024-2025
    status ENUM('Active', 'Inactive', 'Archived') DEFAULT 'Active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE RESTRICT,
    INDEX idx_teacher (teacher_id),
    INDEX idx_grade_section (grade_level, section),
    INDEX idx_day_time (day_of_week, start_time),
    INDEX idx_status (status),
    CHECK (end_time > start_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3NF Analysis:**
- schedule_id → all attributes
- teacher_id is foreign key (relational integrity, not transitive dependency)
- All time/day attributes directly depend on schedule_id

#### 4.1.4 student_schedules (Enrollment Table)
```sql
CREATE TABLE student_schedules (
    enrollment_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    enrolled_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Active', 'Dropped') DEFAULT 'Active',
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE CASCADE,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE CASCADE,
    UNIQUE KEY unique_enrollment (student_id, schedule_id),
    INDEX idx_student (student_id),
    INDEX idx_schedule (schedule_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3NF Analysis:**
- Resolves many-to-many relationship between students and schedules
- enrollment_id → all attributes
- No transitive dependencies

#### 4.1.5 attendance
```sql
CREATE TABLE attendance (
    attendance_id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    schedule_id INT NOT NULL,
    teacher_id INT NOT NULL,
    date DATE NOT NULL,
    time_scanned TIME NOT NULL,
    status ENUM('Present', 'Late', 'Absent') NOT NULL,
    remarks TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(student_id) ON DELETE RESTRICT,
    FOREIGN KEY (schedule_id) REFERENCES schedules(schedule_id) ON DELETE RESTRICT,
    FOREIGN KEY (teacher_id) REFERENCES teachers(teacher_id) ON DELETE RESTRICT,
    UNIQUE KEY unique_daily_attendance (student_id, schedule_id, date),
    INDEX idx_student (student_id),
    INDEX idx_schedule (schedule_id),
    INDEX idx_date (date),
    INDEX idx_status (status),
    INDEX idx_student_date (student_id, date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**3NF Analysis:**
- attendance_id → all attributes
- Foreign keys maintain referential integrity (not transitive dependencies)
- date and time_scanned are independent facts about the attendance event

#### 4.1.6 audit_log (Optional, for compliance)
```sql
CREATE TABLE audit_log (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_type ENUM('Teacher', 'Admin') NOT NULL,
    user_id INT NOT NULL,
    action VARCHAR(50) NOT NULL, -- INSERT, UPDATE, DELETE
    table_name VARCHAR(50) NOT NULL,
    record_id INT,
    old_value TEXT,
    new_value TEXT,
    ip_address VARCHAR(45),
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_type, user_id),
    INDEX idx_table (table_name),
    INDEX idx_timestamp (timestamp)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### 4.2 Referential Integrity

**Foreign Key Constraints:**
- `ON DELETE RESTRICT`: Prevent deletion if referenced (schedules, attendance)
- `ON DELETE CASCADE`: Auto-delete related records (student_schedules)
- Ensures data consistency across tables

**Indexes:**
- Primary keys: Auto-indexed
- Foreign keys: Indexed for join performance
- Frequently queried columns: date, status, grade_level
- Composite indexes: (student_id, date), (grade_level, section)

### 4.3 Data Validation Rules

**Student Data:**
- LRN: Exactly 12 digits, numeric only, unique
- Full Name: 2-100 characters, letters/spaces/hyphens only
- Grade Level: Integer 7-12
- Section: Alphanumeric, 1-20 characters
- Contact: 10-15 digits (Philippines: 09XX-XXX-XXXX format)
- Email: Valid email format (RFC 5322)

**Schedule Data:**
- Subject Name: 3-100 characters
- Time: start_time < end_time
- Duration: >= 30 minutes, <= 4 hours
- Day: Monday-Friday only
- School Year: Format YYYY-YYYY (e.g., 2024-2025)

**Attendance Data:**
- Date: Cannot be future date
- Status: Must be Present/Late/Absent
- Unique constraint: One attendance per student per schedule per day

### 4.4 Sample Data Queries

**Find student's today's schedule:**
```sql
SELECT s.subject_name, s.start_time, s.end_time, t.full_name as teacher
FROM schedules s
JOIN student_schedules ss ON s.schedule_id = ss.schedule_id
JOIN teachers t ON s.teacher_id = t.teacher_id
WHERE ss.student_id = ?
  AND s.day_of_week = DAYNAME(CURDATE())
  AND s.status = 'Active'
ORDER BY s.start_time;
```

**Attendance percentage for student:**
```sql
SELECT 
    COUNT(CASE WHEN status IN ('Present', 'Late') THEN 1 END) * 100.0 / COUNT(*) as attendance_rate
FROM attendance
WHERE student_id = ?
  AND date BETWEEN ? AND ?;
```

**Teacher's schedule conflicts:**
```sql
SELECT s1.*, s2.*
FROM schedules s1
JOIN schedules s2 ON s1.teacher_id = s2.teacher_id
    AND s1.day_of_week = s2.day_of_week
    AND s1.schedule_id != s2.schedule_id
WHERE s1.teacher_id = ?
  AND (
    (s1.start_time < s2.end_time AND s1.end_time > s2.start_time)
  );
```

---

## 5. Technical Dependencies

### 5.1 Software Requirements

**XAMPP Installation:**
- Download: https://www.apachefriends.org/
- Version: 8.2.x (includes PHP 8.2, MySQL 8.0, Apache 2.4)
- Installation path: `C:\xampp` (Windows) or `/opt/lampp` (Linux)

**Composer (PHP Dependency Manager):**
- Download: https://getcomposer.org/
- Required for: PHP-Auth, PhpSpreadsheet, TCPDF, chillerlan/php-qrcode
- Installation:
```bash
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

**Node.js & NPM (Optional, for frontend build tools):**
- Download: https://nodejs.org/
- Version: 18.x LTS or higher
- Used for: Bootstrap customization, SASS compilation

### 5.2 PHP Libraries (Composer)

**Installation Commands:**
```bash
composer require chillerlan/php-qrcode
composer require delight-im/auth
composer require phpoffice/phpspreadsheet
composer require tecnickcom/tcpdf
```

**composer.json:**
```json
{
    "require": {
        "php": ">=8.1",
        "chillerlan/php-qrcode": "^5.0",
        "delight-im/auth": "^8.3",
        "phpoffice/phpspreadsheet": "^1.29",
        "tecnickcom/tcpdf": "^6.6"
    },
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

### 5.3 Frontend Libraries (CDN or Local)

**Bootstrap 5.3.x:**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
```

**QRCode.js:**
```html
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
```

**html5-qrcode:**
```html
<script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js"></script>
```

**DataTables:**
```html
<link rel="stylesheet" href="https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css">
<script src="https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js"></script>
```

**Chart.js:**
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.js"></script>
```

**AdminLTE:**
- Download: https://adminlte.io/themes/v3/
- Include: CSS, JS, and dependencies

### 5.4 Browser Requirements

**Minimum Versions:**
- Chrome 90+
- Firefox 88+
- Safari 14+ (iOS 14.1+ for camera access)
- Edge 90+

**Required APIs:**
- getUserMedia (for webcam access)
- Canvas API (for QR rendering)
- LocalStorage API
- Fetch API

**Fallbacks:**
- Manual attendance entry if camera unavailable
- File upload QR scanning (select image from gallery)

### 5.5 Server Requirements

**Production Server:**
- OS: Ubuntu 22.04 LTS or Windows Server 2019+
- RAM: Minimum 2GB, Recommended 4GB
- Storage: 20GB minimum (10GB for database, 10GB for backups)
- PHP: 8.1.x with required extensions
- MySQL: 8.0.x
- Apache: 2.4.x with mod_rewrite enabled

**SSL Certificate (HTTPS):**
- Let's Encrypt (free): https://letsencrypt.org/
- Required for production (camera access requires HTTPS)

### 5.6 Development Tools

**Code Editor:**
- VS Code: https://code.visualstudio.com/
- Extensions: PHP Intelephense, MySQL, Live Server

**Database Client:**
- phpMyAdmin (included in XAMPP)
- MySQL Workbench: https://www.mysql.com/products/workbench/
- HeidiSQL: https://www.heidisql.com/

**Version Control:**
- Git: https://git-scm.com/
- GitHub/GitLab for repository

**Testing Tools:**
- PHPUnit (unit testing): https://phpunit.de/
- Selenium (browser testing): https://www.selenium.dev/

---

## 6. SOLID Principles Implementation

### 6.1 Single Responsibility Principle (SRP)

**Each class has one responsibility:**

```php
// ✓ Good: StudentRepository handles ONLY database operations
class StudentRepository {
    private $pdo;
    
    public function findByLRN(string $lrn): ?Student {
        $stmt = $this->pdo->prepare("SELECT * FROM students WHERE lrn = ?");
        $stmt->execute([$lrn]);
        return $stmt->fetchObject(Student::class);
    }
    
    public function save(Student $student): bool {
        // INSERT/UPDATE logic
    }
}

// ✓ Good: StudentValidator handles ONLY validation
class StudentValidator {
    public function validate(array $data): array {
        $errors = [];
        if (!preg_match('/^\d{12}$/', $data['lrn'])) {
            $errors[] = 'LRN must be 12 digits';
        }
        return $errors;
    }
}

// ✓ Good: QRCodeService handles ONLY QR generation
class QRCodeService {
    public function generate(Student $student): string {
        $data = json_encode([
            'lrn' => $student->lrn,
            'name' => $student->full_name,
            'token' => $student->qr_token
        ]);
        return (new QRCode())->render($data);
    }
}
```

### 6.2 Open/Closed Principle (OCP)

**Open for extension, closed for modification:**

```php
// ✓ Good: Use interfaces for extensibility
interface AttendanceStatusStrategy {
    public function determine(DateTime $scanTime, Schedule $schedule): string;
}

class StandardAttendanceStatus implements AttendanceStatusStrategy {
    public function determine(DateTime $scanTime, Schedule $schedule): string {
        $gracePeriod = 15; // minutes
        $startTime = new DateTime($schedule->start_time);
        $startTime->modify("+{$gracePeriod} minutes");
        
        return $scanTime <= $startTime ? 'Present' : 'Late';
    }
}

class StrictAttendanceStatus implements AttendanceStatusStrategy {
    public function determine(DateTime $scanTime, Schedule $schedule): string {
        $startTime = new DateTime($schedule->start_time);
        return $scanTime <= $startTime ? 'Present' : 'Late';
    }
}

// Usage: Can easily add new strategies without modifying existing code
$strategy = new StandardAttendanceStatus(); // or StrictAttendanceStatus
$status = $strategy->determine($scanTime, $schedule);
```

### 6.3 Liskov Substitution Principle (LSP)

**Subclasses must be substitutable for base classes:**

```php
abstract class Report {
    abstract public function generate(array $data): string;
}

class PDFReport extends Report {
    public function generate(array $data): string {
        // Generate PDF
        return $pdfFilePath;
    }
}

class ExcelReport extends Report {
    public function generate(array $data): string {
        // Generate Excel
        return $excelFilePath;
    }
}

// ✓ Good: Both can be used interchangeably
function createReport(Report $report, array $data): string {
    return $report->generate($data);
}

$pdf = createReport(new PDFReport(), $attendanceData);
$excel = createReport(new ExcelReport(), $attendanceData);
```

### 6.4 Interface Segregation Principle (ISP)

**No client should depend on methods it doesn't use:**

```php
// ✗ Bad: Fat interface
interface UserOperations {
    public function create();
    public function read();
    public function update();
    public function delete();
    public function authenticate();
    public function sendEmail();
}

// ✓ Good: Segregated interfaces
interface Readable {
    public function read();
}

interface Writable {
    public function create();
    public function update();
}

interface Deletable {
    public function delete();
}

interface Authenticatable {
    public function authenticate();
}

// Classes implement only what they need
class StudentService implements Readable, Writable {
    public function read() { /* ... */ }
    public function create() { /* ... */ }
    public function update() { /* ... */ }
}

class TeacherService implements Readable, Writable, Deletable, Authenticatable {
    // Implements all methods
}
```

### 6.5 Dependency Inversion Principle (DIP)

**Depend on abstractions, not concretions:**

```php
// ✗ Bad: Direct dependency on MySQL
class AttendanceService {
    private $mysql;
    
    public function __construct() {
        $this->mysql = new MySQL();
    }
}

// ✓ Good: Depend on interface
interface DatabaseInterface {
    public function query(string $sql, array $params): array;
}

class MySQLDatabase implements DatabaseInterface {
    public function query(string $sql, array $params): array {
        // MySQL implementation
    }
}

class AttendanceService {
    private $db;
    
    public function __construct(DatabaseInterface $db) {
        $this->db = $db;
    }
    
    public function recordAttendance(int $studentId, int $scheduleId): bool {
        $this->db->query(
            "INSERT INTO attendance (student_id, schedule_id, date) VALUES (?, ?, NOW())",
            [$studentId, $scheduleId]
        );
    }
}

// Usage: Easy to swap database implementation
$service = new AttendanceService(new MySQLDatabase());
// or
$service = new AttendanceService(new PostgreSQLDatabase()); // if needed
```

### 6.6 MVC Architecture (Following SOLID)

```
project/
├── src/
│   ├── Models/          # Data entities
│   │   ├── Student.php
│   │   ├── Teacher.php
│   │   ├── Schedule.php
│   │   └── Attendance.php
│   ├── Repositories/    # Database operations (SRP, DIP)
│   │   ├── StudentRepository.php
│   │   ├── ScheduleRepository.php
│   │   └── AttendanceRepository.php
│   ├── Services/        # Business logic (SRP, OCP)
│   │   ├── AttendanceService.php
│   │   ├── QRCodeService.php
│   │   └── ReportService.php
│   ├── Controllers/     # Handle HTTP requests
│   │   ├── StudentController.php
│   │   ├── ScheduleController.php
│   │   └── AttendanceController.php
│   ├── Validators/      # Input validation (SRP)
│   │   ├── StudentValidator.php
│   │   └── ScheduleValidator.php
│   └── Interfaces/      # Abstractions (ISP, DIP)
│       ├── DatabaseInterface.php
│       ├── ValidatorInterface.php
│       └── ReportInterface.php
├── public/              # Web-accessible files
│   ├── index.php
│   ├── assets/
│   └── uploads/
└── vendor/              # Composer dependencies
```

---

## 7. UI/UX Design Guidelines

### 7.1 Design Principles

**1. Simplicity:**
- Minimal clicks to complete tasks
- Clear visual hierarchy
- No unnecessary features on screen

**2. Consistency:**
- Uniform button styles across all pages
- Standard color scheme (primary, secondary, success, danger)
- Consistent spacing and typography

**3. Responsiveness:**
- Mobile-first design (Bootstrap grid)
- Works on phones (students) and desktop (teachers)
- Touch-friendly buttons (44x44px minimum)

**4. Accessibility:**
- WCAG 2.1 AA compliance
- Sufficient color contrast (4.5:1 for text)
- Keyboard navigation support
- Screen reader friendly

### 7.2 Color Scheme

**Primary Colors:**
- Primary Blue: `#0d6efd` (Bootstrap primary)
- Success Green: `#198754` (for Present status)
- Warning Yellow: `#ffc107` (for Late status)
- Danger Red: `#dc3545` (for Absent status, delete actions)
- Light Gray: `#f8f9fa` (backgrounds)
- Dark Text: `#212529` (main text)

**Status Colors:**
- Present: Green (`#198754`)
- Late: Orange/Yellow (`#ffc107`)
- Absent: Red (`#dc3545`)
- Inactive: Gray (`#6c757d`)

### 7.3 Typography

**Font Family:**
- Primary: "Segoe UI", Roboto, Arial, sans-serif
- Monospace (for codes): "Courier New", monospace

**Font Sizes:**
- Headings: H1 (32px), H2 (28px), H3 (24px), H4 (20px)
- Body Text: 16px
- Small Text: 14px
- Buttons: 16px

**Font Weights:**
- Regular: 400
- Medium: 500
- Bold: 700

### 7.4 Layout Structure

**Teacher Dashboard Layout:**
```
┌─────────────────────────────────────────────────┐
│  Header (Navbar)                                 │
│  [Logo] | Dashboard | Students | Schedules |... │
└─────────────────────────────────────────────────┘
│                                                   │
│  ┌──────────┐  ┌──────────────────────────────┐ │
│  │          │  │                              │ │
│  │ Sidebar  │  │   Main Content Area          │ │
│  │          │  │                              │ │
│  │ - Home   │  │   Page Title                 │ │
│  │ - Stud.  │  │   ────────────               │ │
│  │ - Sched. │  │                              │ │
│  │ - Attend.│  │   Content here...            │ │
│  │ - Report │  │                              │ │
│  │          │  │                              │ │
│  └──────────┘  └──────────────────────────────┘ │
│                                                   │
└─────────────────────────────────────────────────┘
│  Footer: © 2026 School Name | Privacy Policy    │
└─────────────────────────────────────────────────┘
```

**Student Registration Page:**
```
┌─────────────────────────────────────────────────┐
│  Header (Simple)                                 │
│  [Logo] Student Registration                     │
└─────────────────────────────────────────────────┘
│                                                   │
│         ┌─────────────────────────────┐          │
│         │  Student Registration        │          │
│         │  ─────────────────────────  │          │
│         │                             │          │
│         │  LRN: [____________]        │          │
│         │  Name: [____________]       │          │
│         │  Grade: [11 ▼]             │          │
│         │  Section: [ICT-A ▼]        │          │
│         │                             │          │
│         │  Select Your Subjects:      │          │
│         │  ☐ Oral Communication       │          │
│         │  ☐ Mathematics              │          │
│         │  ☐ Physical Education       │          │
│         │                             │          │
│         │  [Register & Get QR Code]  │          │
│         └─────────────────────────────┘          │
│                                                   │
└─────────────────────────────────────────────────┘
```

### 7.5 Component Design

**Buttons:**
```html
<!-- Primary Action -->
<button class="btn btn-primary btn-lg">
  <i class="fas fa-save"></i> Save Student
</button>

<!-- Secondary Action -->
<button class="btn btn-outline-secondary">Cancel</button>

<!-- Danger Action (with confirmation) -->
<button class="btn btn-danger" onclick="confirmDelete()">
  <i class="fas fa-trash"></i> Delete
</button>

<!-- Icon-only Button -->
<button class="btn btn-sm btn-outline-primary" title="Edit">
  <i class="fas fa-edit"></i>
</button>
```

**Cards:**
```html
<div class="card shadow-sm">
  <div class="card-header bg-primary text-white">
    <h5 class="mb-0">Student Profile</h5>
  </div>
  <div class="card-body">
    <p class="card-text">Content here...</p>
  </div>
  <div class="card-footer">
    <button class="btn btn-primary">Action</button>
  </div>
</div>
```

**Tables:**
```html
<table id="students-table" class="table table-striped table-hover">
  <thead class="table-dark">
    <tr>
      <th>LRN</th>
      <th>Name</th>
      <th>Grade</th>
      <th>Actions</th>
    </tr>
  </thead>
  <tbody>
    <!-- Dynamic rows via DataTables -->
  </tbody>
</table>
```

**Forms:**
```html
<form class="needs-validation" novalidate>
  <div class="mb-3">
    <label for="lrn" class="form-label">LRN *</label>
    <input type="text" class="form-control" id="lrn" 
           pattern="\d{12}" required>
    <div class="invalid-feedback">
      LRN must be exactly 12 digits.
    </div>
  </div>
  <button type="submit" class="btn btn-primary">Submit</button>
</form>
```

**Modals:**
```html
<!-- Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white">
        <h5 class="modal-title">Confirm Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to delete this student?</p>
        <p class="text-danger"><strong>This action cannot be undone.</strong></p>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger" onclick="deleteStudent()">Delete</button>
      </div>
    </div>
  </div>
</div>
```

### 7.6 QR Scanner Interface

**Scanner Page:**
```html
<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-8">
      <div class="card">
        <div class="card-header bg-success text-white">
          <h4 class="mb-0">📹 Attendance Scanner</h4>
          <small>Oral Communication - Grade 11 ICT-A</small>
        </div>
        <div class="card-body">
          <!-- QR Scanner Video Feed -->
          <div id="reader" style="width: 100%; max-width: 500px; margin: 0 auto;"></div>
          
          <!-- Live Attendance Count -->
          <div class="alert alert-info mt-3">
            <strong>Live Count:</strong> 
            <span id="present-count">0</span> Present | 
            <span id="late-count">0</span> Late | 
            <span id="absent-count">0</span> Absent
            (Total: <span id="total-count">35</span>)
          </div>
          
          <!-- Recent Scans List -->
          <div class="mt-3">
            <h6>Recent Scans:</h6>
            <ul id="recent-scans" class="list-group">
              <!-- Dynamically populated -->
            </ul>
          </div>
        </div>
        <div class="card-footer">
          <button class="btn btn-danger" onclick="endSession()">
            <i class="fas fa-stop"></i> End Session
          </button>
          <button class="btn btn-secondary" onclick="pauseScanner()">
            <i class="fas fa-pause"></i> Pause
          </button>
        </div>
      </div>
    </div>
  </div>
</div>
```

### 7.7 Responsive Breakpoints

**Bootstrap Breakpoints:**
- xs: <576px (phones)
- sm: ≥576px (phones landscape)
- md: ≥768px (tablets)
- lg: ≥992px (desktops)
- xl: ≥1200px (large desktops)
- xxl: ≥1400px (extra large)

**Mobile Optimizations:**
- Stack cards vertically on mobile
- Full-width buttons on small screens
- Collapsible sidebar menu (hamburger icon)
- Touch-friendly tap targets (min 44x44px)
- Larger font sizes for readability

### 7.8 Loading States & Feedback

**Loading Spinner:**
```html
<div class="text-center">
  <div class="spinner-border text-primary" role="status">
    <span class="visually-hidden">Loading...</span>
  </div>
  <p class="mt-2">Loading students...</p>
</div>
```

**Toast Notifications:**
```javascript
// Success notification
showToast('success', 'Student registered successfully!');

// Error notification
showToast('error', 'Failed to save. Please try again.');

// Info notification
showToast('info', 'QR code sent to email.');
```

**Progress Indicators:**
```html
<!-- For bulk operations -->
<div class="progress">
  <div class="progress-bar progress-bar-striped progress-bar-animated" 
       style="width: 45%">
    45% Complete (45/100 students)
  </div>
</div>
```

### 7.9 Error Handling UI

**Inline Validation:**
```html
<input type="text" class="form-control is-invalid" id="lrn">
<div class="invalid-feedback">
  LRN must be exactly 12 digits
</div>
```

**Error Alert:**
```html
<div class="alert alert-danger alert-dismissible fade show" role="alert">
  <strong>Error!</strong> Unable to connect to database.
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

**Empty States:**
```html
<div class="text-center py-5">
  <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
  <h5>No students found</h5>
  <p class="text-muted">Click "Add Student" to get started</p>
  <button class="btn btn-primary">
    <i class="fas fa-plus"></i> Add Student
  </button>
</div>
```

### 7.10 Iconography

**Font Awesome Icons:**
- Students: `fa-users`
- QR Code: `fa-qrcode`
- Schedule: `fa-calendar-alt`
- Attendance: `fa-clipboard-check`
- Reports: `fa-chart-bar`
- Edit: `fa-edit`
- Delete: `fa-trash`
- Download: `fa-download`
- Print: `fa-print`
- Save: `fa-save`
- Camera: `fa-camera`

**Usage:**
```html
<button class="btn btn-primary">
  <i class="fas fa-qrcode me-2"></i> Generate QR
</button>
```

---

## 8. Implementation Phases

### Phase 1: Foundation (Weeks 1-2)

**Objectives:**
- Set up development environment
- Create database schema
- Implement core authentication

**Deliverables:**
1. XAMPP installation and configuration
2. MySQL database creation with all tables (3NF)
3. Composer dependencies installed
4. Teacher registration and login system
5. Basic dashboard layout (AdminLTE template)
6. Session management with security

**Tasks:**
- [ ] Install XAMPP and verify PHP/MySQL versions
- [ ] Create database: `attendance_system`
- [ ] Execute SQL scripts for table creation
- [ ] Install Composer packages (PHP-Auth, etc.)
- [ ] Create `Teacher` model and repository
- [ ] Implement registration form with validation
- [ ] Implement login with password hashing
- [ ] Set up session management
- [ ] Create basic dashboard layout
- [ ] Test authentication flow

**Testing:**
- Unit tests for password hashing
- Integration tests for login/registration
- Security tests: SQL injection, XSS attempts

### Phase 2: Student & Schedule Management (Weeks 3-4)

**Objectives:**
- Implement full CRUD for students
- Implement full CRUD for schedules
- Build UI for management pages

**Deliverables:**
1. Student management module (CRUD)
2. Schedule management module (CRUD)
3. CSV import functionality
4. Conflict detection system
5. Validation rules implementation

**Tasks:**
- [ ] Create `Student` and `Schedule` models
- [ ] Implement StudentRepository with CRUD methods
- [ ] Implement ScheduleRepository with CRUD methods
- [ ] Build student list page with DataTables
- [ ] Build student create/edit forms
- [ ] Build schedule list and calendar views
- [ ] Implement CSV import parser
- [ ] Create conflict detection algorithm
- [ ] Add validation for all inputs
- [ ] Implement soft delete for students
- [ ] Create enrollment system (student_schedules)

**Testing:**
- Test all CRUD operations
- Test CSV import with various formats
- Test conflict detection accuracy
- Test validation rules
- Test cascade delete behavior

### Phase 3: QR Code System (Weeks 5-6)

**Objectives:**
- Implement QR code generation
- Implement QR code scanning
- Build student registration page

**Deliverables:**
1. Student registration page (public, no login)
2. QR code generation (client & server)
3. QR code scanner with webcam
4. Bulk QR generation feature
5. QR security token system

**Tasks:**
- [ ] Create public student registration page
- [ ] Implement schedule selection UI
- [ ] Integrate qrcode.js for client-side generation
- [ ] Implement server-side QR generation (chillerlan/php-qrcode)
- [ ] Generate unique security tokens (SHA-256)
- [ ] Build QR code display page
- [ ] Implement QR download functionality
- [ ] Create bulk QR generation feature
- [ ] Integrate html5-qrcode library
- [ ] Build scanner interface
- [ ] Implement webcam permission handling
- [ ] Test QR scanning accuracy

**Testing:**
- Test QR generation with various data
- Test scanner with different QR sizes
- Test scanner on multiple browsers
- Test security token uniqueness
- Test QR regeneration flow

### Phase 4: Attendance System (Weeks 7-8)

**Objectives:**
- Implement attendance session management
- Build smart schedule detection
- Create attendance recording logic

**Deliverables:**
1. Attendance session start/stop functionality
2. Smart schedule matching algorithm
3. Attendance recording with status determination
4. Real-time attendance dashboard
5. Duplicate prevention system

**Tasks:**
- [ ] Create `Attendance` model and repository
- [ ] Implement session management
- [ ] Build "Start Session" page
- [ ] Integrate QR scanner into attendance session
- [ ] Implement schedule matching query
- [ ] Create status determination logic (Present/Late)
- [ ] Build real-time attendance dashboard
- [ ] Implement duplicate scan prevention
- [ ] Create manual attendance entry option
- [ ] Build "End Session" workflow
- [ ] Implement auto-mark absent feature

**Testing:**
- Test schedule matching accuracy
- Test status determination (Present/Late)
- Test duplicate prevention
- Test edge cases (no active class, wrong day)
- Performance testing with 50+ students scanning

### Phase 5: Reporting & Analytics (Weeks 9-10)

**Objectives:**
- Implement attendance reports
- Create analytics dashboard
- Build export functionality

**Deliverables:**
1. Attendance record viewer
2. Daily/Weekly/Monthly reports
3. Student attendance history
4. PDF and Excel export
5. Analytics charts and graphs

**Tasks:**
- [ ] Create attendance records page with filters
- [ ] Implement date range selector
- [ ] Build report generation system
- [ ] Integrate PhpSpreadsheet for Excel export
- [ ] Integrate TCPDF for PDF reports
- [ ] Create attendance summary queries
- [ ] Build analytics dashboard
- [ ] Integrate Chart.js for visualizations
- [ ] Create individual student report
- [ ] Implement class performance report

**Testing:**
- Test all report types
- Test export to PDF and Excel
- Test date range filtering
- Verify calculation accuracy
- Performance testing with large datasets

### Phase 6: UI/UX Polish (Weeks 11-12)

**Objectives:**
- Refine user interface
- Improve user experience
- Add animations and feedback

**Deliverables:**
1. Polished UI design
2. Smooth animations
3. Toast notifications
4. Loading states
5. Error handling improvements

**Tasks:**
- [ ] Apply consistent styling across all pages
- [ ] Add loading spinners for AJAX requests
- [ ] Implement toast notifications
- [ ] Add form validation feedback
- [ ] Create empty states for lists
- [ ] Improve mobile responsiveness
- [ ] Add icons to all buttons
- [ ] Create confirmation modals
- [ ] Implement smooth transitions
- [ ] Optimize page load times

**Testing:**
- Cross-browser testing
- Mobile device testing
- Accessibility audit (WCAG 2.1)
- Performance testing (Lighthouse)
- Usability testing with teachers

### Phase 7: Security Hardening (Week 13)

**Objectives:**
- Implement all security measures
- Conduct security audit
- Fix vulnerabilities

**Deliverables:**
1. Complete security implementation
2. Security audit report
3. Penetration testing results
4. Security documentation

**Tasks:**
- [ ] Implement CSRF token protection on all forms
- [ ] Add Content Security Policy headers
- [ ] Enable HTTPS (SSL certificate)
- [ ] Implement rate limiting
- [ ] Add input sanitization
- [ ] Create audit log system
- [ ] Implement session timeout
- [ ] Add IP-based access controls (optional)
- [ ] Conduct SQL injection testing
- [ ] Conduct XSS testing
- [ ] Test authentication bypass attempts

**Testing:**
- OWASP Top 10 vulnerability testing
- SQL injection attempts
- XSS attempts
- CSRF testing
- Session hijacking testing
- Brute force attack testing

### Phase 8: Testing & Deployment (Week 14)

**Objectives:**
- Comprehensive system testing
- User acceptance testing
- Production deployment

**Deliverables:**
1. Complete test suite
2. User manual
3. Admin guide
4. Production-ready system

**Tasks:**
- [ ] Create comprehensive test plan
- [ ] Execute all test cases
- [ ] Conduct user acceptance testing (UAT)
- [ ] Fix all identified bugs
- [ ] Create user documentation
- [ ] Create admin guide
- [ ] Set up production server
- [ ] Configure production database
- [ ] Deploy application to production
- [ ] Configure SSL certificate
- [ ] Set up automated backups
- [ ] Create rollback plan

**Testing:**
- Full system integration testing
- Load testing (simulate 500+ students)
- Failover testing
- Backup and restore testing
- UAT with actual teachers and students

### Phase 9: Training & Launch (Week 15)

**Objectives:**
- Train users
- Launch system
- Monitor initial usage

**Deliverables:**
1. Teacher training sessions
2. Student onboarding materials
3. Live system launch
4. Post-launch support

**Tasks:**
- [ ] Conduct teacher training sessions
- [ ] Create student registration guides
- [ ] Distribute QR codes to students
- [ ] Launch system for pilot class
- [ ] Monitor system performance
- [ ] Collect user feedback
- [ ] Address immediate issues
- [ ] Expand to all classes

**Success Metrics:**
- 100% teachers trained
- 90% students registered within 1 week
- <5 support tickets per day
- 95%+ attendance accuracy
- <5 seconds average scan time

### Phase 10: Maintenance & Iteration (Ongoing)

**Objectives:**
- Maintain system stability
- Add improvements based on feedback
- Regular updates

**Deliverables:**
1. Monthly maintenance reports
2. Feature updates
3. Bug fixes
4. Performance optimizations

**Tasks:**
- [ ] Monitor system health daily
- [ ] Review error logs weekly
- [ ] Backup database daily
- [ ] Update dependencies monthly
- [ ] Collect user feedback continuously
- [ ] Prioritize feature requests
- [ ] Implement high-priority features
- [ ] Conduct quarterly security audits

**Continuous Improvement:**
- A/B test UI improvements
- Optimize database queries
- Reduce page load times
- Enhance mobile experience
- Add new report types based on requests

---

## 9. Constraints

### 9.1 Technical Constraints

**Platform Limitations:**
- XAMPP local development only (not cloud-based initially)
- MySQL storage limits (dependent on server disk space)
- Apache concurrent connections limit (~150 default)
- PHP memory limit (128MB default, increase to 256MB for bulk operations)

**Browser Constraints:**
- Camera access requires HTTPS in production
- Safari iOS <15.1 does not support getUserMedia
- Internet Explorer not supported
- Requires modern browser (Chrome 90+, Firefox 88+)

**Performance Constraints:**
- QR scanning requires good lighting
- Camera resolution affects scan speed
- Bulk QR generation limited to 100 per batch
- DataTables pagination for >1000 records

**Security Constraints:**
- Session timeout: 30 minutes
- Password reset via email requires SMTP server
- File upload size limit: 5MB (CSV import)
- Rate limiting: 5 login attempts per 15 minutes

### 9.2 Business Constraints

**Budget:**
- Zero-cost solution (all open-source libraries)
- Server costs: Depends on deployment (cloud vs. on-premise)
- SSL certificate: Free via Let's Encrypt

**Timeline:**
- 15-week development cycle
- Must launch before semester start
- Training must complete 1 week before launch

**Resources:**
- 1-2 developers
- 1 QA tester
- School IT support for deployment

### 9.3 Operational Constraints

**School Environment:**
- Internet connectivity required for camera access (HTTPS)
- Local network sufficient for on-premise deployment
- IT support availability for troubleshooting
- Teacher training time limited to 2 hours per session

**User Constraints:**
- Teachers may have limited tech skills
- Students need smartphones with cameras
- Not all students may have smartphones (fallback: manual entry)
- School network firewall restrictions

### 9.4 Data Constraints

**Privacy & Compliance:**
- Student data privacy (Data Privacy Act 2012, Philippines)
- Parental consent for minor students
- Data retention policy: 5 years (configurable)
- Right to erasure: Students can request data deletion

**Data Volume:**
- Estimated 500-2000 students per school
- 10-50 teachers
- 100+ schedules
- 50,000+ attendance records per school year
- Database backup size: ~500MB per year

### 9.5 Scalability Constraints

**Current Design:**
- Supports up to 5,000 students
- Up to 100 concurrent users
- Single-server architecture

**Future Scaling:**
- Multi-school deployment requires:
  - School ID column in all tables
  - Centralized authentication
  - Load balancing
  - Database sharding

---

## 10. Success Metrics

### 10.1 Adoption Metrics

**Teacher Adoption:**
- Target: 90% of teachers using system by Week 4 post-launch
- Measurement: Active teacher accounts / Total teachers
- Milestone: 50% by Week 2, 75% by Week 3, 90% by Week 4

**Student Registration:**
- Target: 95% of students registered by Week 2 post-launch
- Measurement: Registered students / Total enrolled students
- Milestone: 50% by Week 1, 80% by Week 2, 95% by Week 3

### 10.2 Performance Metrics

**Scan Speed:**
- Target: <5 seconds average scan time
- Measurement: Time from QR presentation to attendance recorded
- Acceptable: 3-7 seconds
- Excellent: <3 seconds

**Attendance Accuracy:**
- Target: 95%+ accuracy rate
- Measurement: Correct attendance records / Total records
- Calculated: Monthly audit of random sample (100 records)

**System Uptime:**
- Target: 99.5% uptime during school hours
- Measurement: Uptime monitoring tool
- Acceptable downtime: <5 minutes per week

**Page Load Time:**
- Target: <2 seconds for all pages
- Measurement: Google Lighthouse performance score
- Acceptable: 2-3 seconds
- Excellent: <1 second

### 10.3 Efficiency Metrics

**Time Savings:**
- Target: 80% reduction in attendance time
- Baseline: Manual roll call = 5-10 minutes
- Goal: QR attendance = <2 minutes
- Measurement: Stopwatch timing during sessions

**Paper Reduction:**
- Target: 100% elimination of paper attendance sheets
- Measurement: Zero paper sheets printed for attendance
- Environmental impact: Calculate trees saved

### 10.4 User Satisfaction Metrics

**Teacher Satisfaction:**
- Target: 85%+ satisfaction rating
- Measurement: Post-semester survey (1-5 scale)
- Questions:
  - Ease of use
  - Time saved
  - Reliability
  - Would recommend to others

**Student Feedback:**
- Target: 80%+ positive feedback
- Measurement: Anonymous survey
- Questions:
  - Registration ease
  - QR scanning experience
  - Overall satisfaction

### 10.5 Data Quality Metrics

**Data Completeness:**
- Target: 99%+ complete records
- Measurement: Records with all required fields / Total records
- Monitor: Missing LRNs, null values

**Data Accuracy:**
- Target: 95%+ accurate attendance status
- Measurement: Manual audit vs. system records
- Process: Random sample audit monthly

**Duplicate Prevention:**
- Target: Zero duplicate attendance records
- Measurement: Query for duplicate (student_id, schedule_id, date)
- Alert: Automated check daily

### 10.6 Support Metrics

**Support Tickets:**
- Target: <5 tickets per day
- Measurement: Help desk ticketing system
- Categories: Bug reports, feature requests, how-to questions

**Bug Resolution Time:**
- Target: <24 hours for critical bugs
- Target: <1 week for minor bugs
- Measurement: Ticket creation to resolution time

**Training Effectiveness:**
- Target: 90%+ teachers can use system independently after training
- Measurement: Post-training assessment
- Metric: % who complete tasks without assistance

### 10.7 Business Impact Metrics

**Cost Savings:**
- Calculate: Paper costs eliminated
- Calculate: Administrative time saved (hours × hourly rate)
- Calculate: Total ROI after 1 year

**Attendance Trends:**
- Monitor: Overall attendance rate before vs. after system
- Target: No decrease in attendance rate
- Insight: Identify patterns (e.g., Mondays have lower attendance)

**Report Generation Efficiency:**
- Target: Reports generated in <30 seconds
- Measurement: Time from request to download
- Baseline: Manual report creation = 1-2 hours

---

## 11. References & Documentation

### 11.1 Official Documentation

**Bootstrap 5:**
- Docs: https://getbootstrap.com/docs/5.3/
- Components: https://getbootstrap.com/docs/5.3/components/
- Examples: https://getbootstrap.com/docs/5.3/examples/

**DataTables:**
- Docs: https://datatables.net/manual/
- Examples: https://datatables.net/examples/
- API: https://datatables.net/reference/api/

**Chart.js:**
- Docs: https://www.chartjs.org/docs/latest/
- Samples: https://www.chartjs.org/docs/latest/samples/

**QRCode.js:**
- GitHub: https://github.com/davidshimjs/qrcodejs
- Demo: https://davidshimjs.github.io/qrcodejs/

**html5-qrcode:**
- GitHub: https://github.com/mebjas/html5-qrcode
- Docs: https://scanapp.org/html5-qrcode-docs

**AdminLTE:**
- Docs: https://adminlte.io/docs/3.0/
- Demo: https://adminlte.io/themes/v3/

### 11.2 PHP Libraries

**PHP-Auth:**
- GitHub: https://github.com/delight-im/PHP-Auth
- Docs: https://github.com/delight-im/PHP-Auth/blob/master/README.md

**chillerlan/php-qrcode:**
- GitHub: https://github.com/chillerlan/php-qrcode
- Docs: https://chillerlan-php-qrcode.readthedocs.io/

**PhpSpreadsheet:**
- GitHub: https://github.com/PHPOffice/PhpSpreadsheet
- Docs: https://phpspreadsheet.readthedocs.io/

**TCPDF:**
- GitHub: https://github.com/tecnickcom/TCPDF
- Docs: https://tcpdf.org/docs/

### 11.3 Database & SQL

**MySQL 8.0 Reference:**
- Manual: https://dev.mysql.com/doc/refman/8.0/en/
- Data Types: https://dev.mysql.com/doc/refman/8.0/en/data-types.html
- SQL Syntax: https://dev.mysql.com/doc/refman/8.0/en/sql-syntax.html

**Database Normalization:**
- 3NF Guide: https://www.studytonight.com/dbms/third-normal-form.php
- Normal Forms: https://www.guru99.com/database-normalization.html

### 11.4 Security Resources

**OWASP:**
- Top 10: https://owasp.org/www-project-top-ten/
- SQL Injection: https://owasp.org/www-community/attacks/SQL_Injection
- XSS: https://owasp.org/www-community/attacks/xss/

**PHP Security:**
- Best Practices: https://www.php.net/manual/en/security.php
- Password Hashing: https://www.php.net/manual/en/function.password-hash.php

### 11.5 Design Resources

**UI/UX Guidelines:**
- Material Design: https://material.io/design
- WCAG 2.1: https://www.w3.org/WAI/WCAG21/quickref/
- Color Contrast Checker: https://webaim.org/resources/contrastchecker/

**Icons:**
- Font Awesome: https://fontawesome.com/icons
- Bootstrap Icons: https://icons.getbootstrap.com/

### 11.6 Development Tools

**XAMPP:**
- Download: https://www.apachefriends.org/
- Docs: https://www.apachefriends.org/faq_windows.html

**Composer:**
- Download: https://getcomposer.org/download/
- Docs: https://getcomposer.org/doc/

**Git:**
- Docs: https://git-scm.com/doc
- Tutorial: https://www.atlassian.com/git/tutorials

### 11.7 Testing Resources

**PHPUnit:**
- Docs: https://phpunit.de/documentation.html
- Getting Started: https://phpunit.de/getting-started/phpunit-9.html

**Selenium:**
- Docs: https://www.selenium.dev/documentation/
- WebDriver: https://www.selenium.dev/documentation/webdriver/

### 11.8 Deployment

**Let's Encrypt (SSL):**
- Getting Started: https://letsencrypt.org/getting-started/
- Certbot: https://certbot.eff.org/

**Apache Configuration:**
- Docs: https://httpd.apache.org/docs/2.4/

---

## 12. Appendix

### 12.1 Glossary

**Terms:**
- **LRN:** Learner Reference Number (12-digit unique student ID in Philippines)
- **CRUD:** Create, Read, Update, Delete operations
- **QR Code:** Quick Response code (2D barcode)
- **SOLID:** Single responsibility, Open/closed, Liskov substitution, Interface segregation, Dependency inversion
- **ACID:** Atomicity, Consistency, Isolation, Durability
- **3NF:** Third Normal Form (database normalization)
- **XSS:** Cross-Site Scripting
- **CSRF:** Cross-Site Request Forgery
- **PDO:** PHP Data Objects (database abstraction)
- **OOP:** Object-Oriented Programming

### 12.2 Sample Data

**Sample Student:**
```json
{
  "lrn": "123456789012",
  "full_name": "Juan Dela Cruz",
  "grade_level": 11,
  "section": "ICT-A",
  "contact_number": "09123456789",
  "email": "juan@example.com"
}
```

**Sample Schedule:**
```json
{
  "subject_name": "Oral Communication",
  "grade_level": 11,
  "section": "ICT-A",
  "day_of_week": "Monday",
  "start_time": "08:00",
  "end_time": "09:00",
  "room": "Room 101",
  "semester": "1st",
  "school_year": "2024-2025"
}
```

**Sample QR Data:**
```json
{
  "lrn": "123456789012",
  "name": "Juan Dela Cruz",
  "grade": "11",
  "section": "ICT-A",
  "token": "a1b2c3d4e5f6g7h8i9j0"
}
```

### 12.3 Environment Variables (.env)

```env
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=attendance_system
DB_USER=root
DB_PASS=

# Application
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/attendance

# Security
SESSION_TIMEOUT=1800
LOGIN_ATTEMPTS=5
LOCKOUT_DURATION=900

# QR Settings
QR_SIZE=512
QR_TOKEN_LENGTH=64

# File Upload
MAX_UPLOAD_SIZE=5242880
ALLOWED_EXTENSIONS=csv,xlsx

# Email (optional)
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your_email@gmail.com
SMTP_PASS=your_password
```

### 12.4 Quick Start Guide

**For Developers:**

1. **Clone Repository:**
   ```bash
   git clone https://github.com/your-repo/attendance-system.git
   cd attendance-system
   ```

2. **Install Dependencies:**
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment:**
   ```bash
   cp .env.example .env
   # Edit .env with your database credentials
   ```

4. **Create Database:**
   ```bash
   mysql -u root -p
   CREATE DATABASE attendance_system;
   exit
   ```

5. **Run Migrations:**
   ```bash
   php migrations/migrate.php
   ```

6. **Start XAMPP:**
   - Start Apache
   - Start MySQL
   - Visit: http://localhost/attendance

7. **Create Admin Account:**
   ```bash
   php scripts/create_admin.php
   ```

---

## End of PRD

**Document Control:**
- **Version:** 1.0
- **Author:** System Architect
- **Date:** January 28, 2026
- **Status:** Final Draft
- **Next Review:** Before Phase 1 Implementation

**Approval:**
- [ ] School Administrator
- [ ] IT Department Head
- [ ] Lead Developer
- [ ] QA Manager

**Change Log:**
| Date | Version | Changes | Author |
|------|---------|---------|--------|
| 2026-01-28 | 1.0 | Initial PRD creation | System Architect |

---

**For AI Implementation:**

This PRD provides complete specifications for building the QR Code-Based Attendance System. All technical details, database schemas, UI/UX guidelines, implementation phases, and references are included. Follow the SOLID principles, ACID compliance, and 3NF database design as specified. Use the provided library documentation links for implementation guidance.

**Key Implementation Notes:**
1. Follow the 15-week implementation timeline
2. Prioritize security (see Section 3.5)
3. Adhere to 3NF database design (Section 4.1)
4. Implement SOLID principles (Section 6)
5. Use all referenced libraries and frameworks
6. Follow UI/UX guidelines (Section 7)
7. Test according to success metrics (Section 10)

**Ready for AI-assisted development.**

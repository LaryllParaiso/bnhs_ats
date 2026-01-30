# BNH ATS — Development + QA Tracker

**Source docs used:**
- `QR_Attendance_System_PRD.md`
- `enhanced_system_flow.md`

## 1) How to use this tracker

### 1.1 Status legend
- **[ ]** Not started
- **[~]** In progress (manually change)
- **[x]** Done / Passed

### 1.2 QA execution notes
- For every feature, run the **Happy Path** tests first, then **Validation** tests, then **Edge Cases**.
- Record evidence:
  - Screenshot(s)
  - DB row proof (phpMyAdmin query screenshot)
  - Timestamp and tester name

### 1.3 Recommended build order (dependency-driven)
Follow the phases in **Section 2** in order:
- Phase 1 must exist before any CRUD.
- CRUD must exist before QR.
- QR must exist before Attendance.
- Attendance must exist before Reporting.

---

## 2) Development Order (Phases) — what to build first

> This order is designed to minimize rework and unblock later modules early.

### Phase 1 — Foundation (Environment + Database + Auth)
- [ ] P1-01 XAMPP configured (Apache + PHP + MySQL) and project loads in browser
- [ ] P1-02 Database created: `attendance_system`
- [ ] P1-03 Tables created:
  - [ ] `teachers`
  - [ ] `students`
  - [ ] `schedules`
  - [ ] `student_schedules`
  - [ ] `attendance`
  - [ ] `audit_log` (optional)
- [ ] P1-04 DB constraints/indexes created (unique keys + FK + unique_daily_attendance)
- [ ] P1-05 Teacher authentication:
  - [ ] Teacher registration form
  - [ ] Password hashing (bcrypt)
  - [ ] Login/logout
  - [ ] Session timeout (30 minutes inactivity)
  - [ ] Lockout after 5 failed logins (15 minutes)
- [ ] P1-06 RBAC skeleton (Teacher/Admin roles) OR at least teacher-only access control
- [ ] P1-07 Base layout/template (AdminLTE or Bootstrap) + navigation shell

**Definition of Done (Phase 1)**
- [ ] Teacher can register + login + logout
- [ ] Unauthorized users cannot access teacher pages
- [ ] DB schema exists and core tables are queryable

### Phase 2 — Teacher: Students + Schedules (CRUD + Enrollment)
- [ ] P2-01 Student Management UI:
  - [ ] List + search + filter (grade/section/QR status)
  - [ ] Create student
  - [ ] Edit student
  - [ ] Soft delete / archive student
  - [ ] View student profile (enrolled schedules + QR status)
- [ ] P2-02 Schedule Management UI:
  - [ ] List + filters
  - [ ] Create schedule
  - [ ] Edit schedule
  - [ ] Activate/deactivate schedule
  - [ ] Archive schedule (recommended) / delete (restricted)
  - [ ] Conflict detection (same teacher overlapping time)
- [ ] P2-03 Enrollment:
  - [ ] Enroll student into schedules (creates `student_schedules` rows)
  - [ ] Update enrollments on edit
  - [ ] Prevent duplicate enrollments (unique constraint)
- [ ] P2-04 Bulk import students (CSV) + optional auto-enroll + optional bulk QR generation hook

**Definition of Done (Phase 2)**
- [ ] Teacher can CRUD students and schedules reliably
- [ ] Enrollment works and respects grade/section rules

### Phase 3 — Student (Public): Registration + Universal QR
- [x] P3-01 Public student registration page (no login)
- [x] P3-02 Schedule selection filtered by grade & section
- [x] P3-03 QR generation (universal QR)
  - [x] QR includes `lrn`, `name`, `grade`, `section`, `token`
  - [x] QR token stored server-side and validated server-side
- [x] P3-04 QR download (PNG) + print option
- [x] P3-05 Public QR regeneration page (enter LRN → new token → old invalid)
- [x] P3-06 Bulk QR generation for teacher

**Definition of Done (Phase 3)**
- [x] Student can register once, select schedules, and get one QR usable for all subjects

### Phase 4 — Attendance Session + Smart Matching
- [x] P4-01 Start attendance session (teacher selects schedule)
- [x] P4-02 Session prechecks:
  - [x] schedule must be Active
  - [x] day/time must match schedule (±15 min configurable grace)
  - [x] one active session per teacher
- [x] P4-03 Scanner UI (webcam) + file upload fallback (optional)
- [x] P4-04 Scan pipeline:
  - [x] decode QR
  - [x] lookup student by LRN
  - [x] validate token
  - [x] validate enrollment
  - [x] prevent duplicate scan (same student + schedule + date)
  - [x] determine Present/Late
  - [x] write attendance row
- [x] P4-05 Real-time list updates (present/late counts)
- [x] P4-06 End session:
  - [x] auto-mark absent for enrolled but not scanned
  - [x] allow manual override before finalizing

**Definition of Done (Phase 4)**
- [x] Attendance is recorded accurately with correct status + duplicate prevention + edge-case messaging

### Phase 5 — Records, Reports, Export, Analytics
- [x] P5-01 Attendance records viewer (filters: date range, subject, grade, section, status)
- [x] P5-02 Student-level history view
- [~] P5-03 Exports:
  - [x] PDF export
  - [x] Excel export
  - [x] CSV export (optional)
- [x] P5-04 Analytics dashboard (Chart.js):
  - [x] attendance rate by class/subject
  - [x] late arrival analysis
  - [x] absence trends

### Phase 6 — UI/UX Polish
- [ ] P6-01 Consistent styling, icons, empty states
- [ ] P6-02 Loading states + toast notifications
- [ ] P6-03 Mobile responsiveness (student pages especially)

### Phase 7 — Security Hardening
- [ ] P7-01 CSRF tokens on all POST forms
- [ ] P7-02 XSS protections (escape output)
- [ ] P7-03 CSP headers (baseline)
- [ ] P7-04 Rate limiting + brute force protections
- [ ] P7-05 Session hardening (regen session ID on login, secure cookie flags)
- [ ] P7-06 Audit log for sensitive operations (optional but recommended)

### Phase 8 — System Testing + Deployment
- [ ] P8-01 Full regression test (use Section 4)
- [ ] P8-02 Load test (simulate 500+ students; verify DB indexes)
- [ ] P8-03 Backup/restore test
- [ ] P8-04 Production readiness (HTTPS required for camera access)

---

## 3) AI-assisted development workflow (how to build effectively)

Use this loop per feature:
1. **Define scope**
   - Input(s), output(s), DB tables touched, permissions
2. **Implement backend first**
   - Routes/handlers → repository queries → validation
3. **Implement UI second**
   - Forms, lists, toasts, validation messages
4. **Add QA tests immediately**
   - Run the relevant test cases in Section 4
5. **Lock with constraints**
   - Unique keys, FK constraints, server-side checks
6. **Repeat**

Prompting strategy (copy/paste when using AI):
- “Implement only Phase X, item Y. Do not modify unrelated code. Include validation + error messages + DB constraints. Provide test steps.”

---

## 4) QA Checklist (Functional Test Cases)

> Format: **TC-ID** — Title
> - **Preconditions**
> - **Steps**
> - **Expected**
> - **Notes/Evidence**

### 4.1 Authentication (Teacher)

#### TC-AUTH-01 — Teacher registration (valid)
- **Preconditions**
  - Database reachable
- **Steps**
  1. Open teacher registration page
  2. Enter valid Full Name, Employee ID, Email, strong Password, Department
  3. Submit
- **Expected**
  - Teacher account created
  - Password stored hashed (not plaintext)
  - User redirected to login or dashboard
- **Notes/Evidence**
  - [ ] Screenshot
  - [ ] DB check (`teachers` row)

#### TC-AUTH-02 — Teacher login (valid)
- **Steps**
  1. Enter correct credentials
  2. Login
- **Expected**
  - Session created
  - Redirect to teacher dashboard

#### TC-AUTH-03 — Teacher login lockout after failures
- **Steps**
  1. Enter wrong password 5 times
  2. Try again immediately
- **Expected**
  - Account locked for 15 minutes (or configured duration)
  - Clear error message

#### TC-AUTH-04 — Session timeout
- **Steps**
  1. Login
  2. Stay idle for 30 minutes
  3. Navigate to protected page
- **Expected**
  - Forced logout or re-login

### 4.2 Schedule Management (Teacher)

#### TC-SCH-01 — Create schedule (valid)
- **Steps**
  1. Create schedule with Subject, Grade, Section, Day, Start, End, Semester, School Year
- **Expected**
  - Schedule saved
  - Appears in schedule list

#### TC-SCH-02 — Validate time range
- **Steps**
  1. Create schedule with `end_time <= start_time`
- **Expected**
  - Validation error
  - No DB insert

#### TC-SCH-03 — Conflict detection (same teacher overlap)
- **Steps**
  1. Create schedule Monday 08:00-09:00
  2. Create another Monday 08:30-09:30 (same teacher)
- **Expected**
  - System blocks and shows conflict message

#### TC-SCH-04 — Activate/deactivate
- **Steps**
  1. Toggle schedule to Inactive
- **Expected**
  - Inactive schedule cannot be used to start session

### 4.3 Student Management (Teacher)

#### TC-STU-01 — Create student (valid)
- **Steps**
  1. Register student with valid 12-digit LRN
  2. Select grade/section
  3. Enroll into one or more schedules
- **Expected**
  - Student saved
  - `student_schedules` rows created

#### TC-STU-02 — LRN validation (not 12 digits)
- **Steps**
  1. Enter LRN with 11 digits
- **Expected**
  - Validation error

#### TC-STU-03 — LRN uniqueness
- **Steps**
  1. Create student with LRN X
  2. Create another student with same LRN X
- **Expected**
  - Duplicate error “LRN already exists”

#### TC-STU-04 — Soft delete (archive)
- **Steps**
  1. Archive a student
- **Expected**
  - Student status becomes Inactive
  - QR token becomes invalid (cannot scan)

### 4.4 Public Student Registration + QR

#### TC-REG-01 — Public registration (valid) + schedule selection
- **Steps**
  1. Open public student registration page
  2. Enter name, LRN, grade, section
  3. Select schedules listed for that grade/section
  4. Submit
- **Expected**
  - Student + enrollment created
  - Redirected to QR page

#### TC-QR-01 — QR generation contains required fields
- **Expected**
  - QR payload includes `lrn`, `name`, `grade`, `section`, `token`

#### TC-QR-02 — QR regeneration invalidates old QR
- **Steps**
  1. Regenerate QR by entering LRN
  2. Attempt scan using old QR
  3. Attempt scan using new QR
- **Expected**
  - Old QR rejected (token mismatch)
  - New QR accepted

### 4.5 Attendance Session (Teacher)

#### TC-ATT-01 — Start session (valid time)
- **Steps**
  1. Login as teacher
  2. Choose schedule whose day/time matches now
  3. Start session
- **Expected**
  - Session starts
  - Scanner loads and requests camera permissions

#### TC-ATT-02 — Start session (invalid day/time)
- **Steps**
  1. Try starting Monday 8AM schedule on Tuesday
- **Expected**
  - Blocked with message (supports ±15 min grace only)

#### TC-ATT-03 — Scan valid QR (Present)
- **Preconditions**
  - Session running
  - Student enrolled in schedule
- **Steps**
  1. Scan within grace period (0–15 min from start)
- **Expected**
  - Attendance row created
  - Status = Present

#### TC-ATT-04 — Scan valid QR (Late)
- **Steps**
  1. Scan after grace period but before end_time
- **Expected**
  - Status = Late

#### TC-ATT-05 — Duplicate scan prevention
- **Steps**
  1. Scan student QR successfully
  2. Scan again same session/day
- **Expected**
  - “Already recorded” shown
  - No second attendance row created

#### TC-ATT-06 — Invalid token
- **Steps**
  1. Tamper QR payload or use old QR after regeneration
- **Expected**
  - Rejected with “Invalid QR code. Please regenerate.”

#### TC-ATT-07 — Student not enrolled in schedule
- **Steps**
  1. Scan QR of a student not enrolled in this schedule
- **Expected**
  - Rejected with “You are not enrolled in this subject”

#### TC-ATT-08 — End session auto-mark absent
- **Steps**
  1. Start session
  2. Let only some students scan
  3. End session
- **Expected**
  - Non-scanned enrolled students marked Absent

#### TC-ATT-09 — Scan outside schedule time (no active class)
- **Steps**
  1. Scan when there is no matching active schedule time
- **Expected**
  - Message: “No active class found for your schedule”
  - No attendance row

### 4.6 Reporting & Export

#### TC-REP-01 — Attendance records filter
- **Steps**
  1. Filter by date range and schedule
- **Expected**
  - Records match filters accurately

#### TC-REP-02 — PDF export
- **Expected**
  - PDF generated and downloads
  - Totals (Present/Late/Absent) correct

#### TC-REP-03 — Excel export
- **Expected**
  - Excel generated
  - Columns correct and data complete

---

## 5) Edge Cases Checklist (must not break)

### Enrollment / Schedule
- [ ] EC-01 Student enrolled in overlapping schedules (same time)
  - Expected: conflict warning + admin/teacher action required
- [ ] EC-02 Teacher tries editing schedule while session active
  - Expected: blocked
- [ ] EC-03 Deleting schedule with attendance records
  - Expected: archive recommended; hard delete restricted

### QR / Security
- [ ] EC-04 Student archived/inactive then tries scanning
  - Expected: rejected
- [ ] EC-05 QR payload edited client-side
  - Expected: rejected server-side

### Attendance
- [ ] EC-06 Scan after end_time
  - Expected: rejected or flagged as Very Late based on chosen policy
- [ ] EC-07 Poor camera / permission denied
  - Expected: manual attendance option available

---

## 6) QA Run Log (fill this in while testing)

| Date | Tester | Module | Test Cases Run | Pass | Fail | Notes / Bug IDs |
|------|--------|--------|----------------|------|------|------------------|
|      |        |        |                |      |      |                  |

## 7) Bug Report Template

- **Bug ID:**
- **Title:**
- **Environment:** (browser, OS, URL)
- **Steps to Reproduce:**
- **Expected Result:**
- **Actual Result:**
- **Severity:** (Critical/High/Medium/Low)
- **Screenshots/Logs:**
- **Notes:**

---

## 8) Notes / Decisions (freeze key rules here)

- **Grace period:** 15 minutes (configurable)
- **Attendance uniqueness:** one row per (`student_id`, `schedule_id`, `date`)
- **QR token behavior:** regenerate → old token invalid
- **Preferred deletions:** soft delete/archive to preserve history

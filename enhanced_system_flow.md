# Enhanced QR Code-Based Attendance System
## Complete System Flow & Features

---

## System Overview

A web-based attendance system where:
- **Teachers** create schedules and manage attendance sessions
- **Students** register once, generate a universal QR code, and scan it for automatic attendance
- **System** intelligently determines the correct schedule and marks attendance based on time and day

---

## 1. Teacher Module

### 1.1 Teacher Registration & Authentication

**Registration Process:**
- Teacher visits the registration page
- Inputs:
  - Full Name
  - Employee ID
  - Email
  - Password (hashed storage)
  - Department/Subject Specialization
- System validates and creates teacher account
- Account status: Pending/Approved (optional admin approval)

**Login Process:**
- Teacher enters credentials
- System authenticates using secure session management
- Redirects to Teacher Dashboard

### 1.2 Schedule Creation (Teacher)

**Purpose:** Teachers create their class schedules once at the start of the semester

**Process:**
1. Teacher navigates to "Create Schedule" page
2. Inputs schedule details:
   - **Subject Name** (e.g., Oral Communication)
   - **Grade Level** (e.g., Grade 11)
   - **Section** (e.g., ICT-A)
   - **Day of Week** (Monday-Friday)
   - **Time Slot:**
     - Start Time (e.g., 8:00 AM)
     - End Time (e.g., 9:00 AM)
   - **Room/Location** (optional)
   - **Semester/School Year**

3. Teacher can create multiple schedules for different classes
4. System saves all schedules to database

**Schedule Database Structure:**
```
schedules
├── schedule_id
├── teacher_id
├── subject_name
├── grade_level
├── section
├── day_of_week (Monday-Friday)
├── start_time
├── end_time
├── room
├── semester
├── school_year
├── status (Active/Inactive)
```

**Example Schedule Entry:**
```
Teacher: John Doe
Subject: Oral Communication
Grade: 11
Section: ICT-A
Day: Monday
Time: 8:00 AM - 9:00 AM
```

### 1.3 Teacher Dashboard Features

After login, teachers can:

1. **Student Management (Full CRUD)**
   
   **Create/Register Student:**
   - Teacher can manually register students
   - Input fields:
     - LRN
     - Full Name
     - Grade Level
     - Section
     - Contact Number
     - Email
   - Auto-enroll student to teacher's selected schedules
   - Option to generate QR immediately after registration
   
   **View Students:**
   - List all students (filterable by Grade/Section)
   - Search by LRN or Name
   - View student details and enrolled subjects
   - See QR code status (Generated/Not Generated)
   
   **Edit Student:**
   - Update student information:
     - Name corrections
     - Grade/Section changes
     - Contact information
   - Modify enrolled subjects
   - When info changes, option to regenerate QR code
   
   **Delete Student:**
   - Remove student from system
   - Confirmation prompt before deletion
   - Note: Consider "Deactivate" instead of hard delete for record keeping
   - Can archive instead of permanent deletion
   
   **Generate/Regenerate QR Code:**
   - Teacher can generate QR code for any student
   - Regenerate if student loses QR or info changed
   - Download QR directly from teacher dashboard
   - Option to print multiple student QR codes at once
   - Bulk QR generation for entire class

2. **Schedule Management (Full CRUD)**
   
   **Create Schedule:**
   - Add new class schedule
   - Input fields:
     - Subject Name
     - Grade Level
     - Section
     - Day of Week
     - Start Time
     - End Time
     - Room/Location
     - Semester/School Year
   - System validates for schedule conflicts
   
   **View Schedules:**
   - List all schedules (table view)
   - Filter by:
     - Day of week
     - Grade/Section
     - Subject
     - Status (Active/Inactive)
   - View enrolled students per schedule
   - Calendar view option (weekly grid)
   
   **Edit Schedule:**
   - Modify schedule details:
     - Change time slots
     - Update room assignment
     - Adjust day of week
     - Rename subject
   - System warns if changes affect enrolled students
   - Prevents editing if attendance session is active
   
   **Delete Schedule:**
   - Remove schedule from system
   - Confirmation with warning:
     - Shows number of enrolled students
     - Option to transfer students to another schedule
   - Can only delete if no attendance records exist
   - Or option to "Archive" instead
   
   **Activate/Deactivate Schedule:**
   - Toggle schedule status
   - Inactive schedules don't appear in attendance sessions
   - Useful for temporary schedule changes

3. **Start Attendance Session**
   - Select from their active schedules
   - Click "Start Session"
   - QR Scanner opens
   - System begins accepting student QR scans
   - Real-time student list updates as they scan

4. **View Attendance Records**
   - Filter by:
     - Date
     - Subject
     - Grade & Section
   - View:
     - Present students
     - Absent students
     - Late students (with time)
   - Export options (PDF/Excel)
   - Edit individual attendance records (mark manual attendance if needed)

5. **Attendance Summary/Reports**
   - Weekly/Monthly attendance statistics
   - Student attendance percentage
   - Class attendance trends
   - Individual student attendance history
   - Export comprehensive reports

6. **Bulk Operations**
   - **Bulk Student Registration:**
     - Upload CSV file with student list
     - Auto-create accounts for multiple students
     - Mass enroll to schedules
   
   - **Bulk QR Generation:**
     - Generate QR codes for entire class
     - Download as ZIP file
     - Print-ready PDF format (multiple QRs per page)
   
   - **Bulk Schedule Creation:**
     - Duplicate schedule for multiple sections
     - Create recurring schedules quickly

---

## 2. Student Module

### 2.1 Student Registration (One-Time, No Login Required)

**Registration Page:**
- Student visits "Student Registration" page
- Inputs:
  - **LRN** (Learner Reference Number)
  - **Full Name**
  - **Grade Level**
  - **Section**
  - **Contact Number** (optional)
  - **Email** (optional)

**Schedule Selection:**
- After entering basic info, system shows:
  - **Available Schedules** for their Grade & Section
  - Created by teachers
  - Example display:

```
Available Schedules for Grade 11 - ICT-A:

☐ Oral Communication (Mon 8:00-9:00 AM) - Teacher: John Doe
☐ Mathematics (Tue 9:00-10:00 AM) - Teacher: Jane Smith
☐ Physical Education (Wed 2:00-3:00 PM) - Teacher: Mark Lee
...
```

- Student **selects all their subjects** (checkbox selection)
- System creates student-schedule enrollment records

**After Registration:**
- System validates all information
- Saves student data to database
- Student is redirected to QR Generation page

### 2.2 QR Code Generation (Student)

**Universal QR Code:**
- Student clicks "Generate QR Code"
- System generates a **single, universal QR code** containing:
  - LRN (primary identifier)
  - Student Name
  - Grade & Section
  - Unique Hash/Token (for security)

**QR Code Format (Embedded Data):**
```json
{
  "lrn": "123456789012",
  "name": "Juan Dela Cruz",
  "grade": "11",
  "section": "ICT-A",
  "token": "abc123xyz789"
}
```

**Download QR:**
- "Save QR Code" button appears
- Student downloads QR as PNG/JPG
- Student saves to phone gallery
- **This QR works for ALL enrolled subjects**

**Important Notes:**
- No login required for students
- QR code is reusable for the entire semester
- QR code works across all enrolled subjects
- If student info changes, they can regenerate QR

---

## 3. Smart Attendance System Logic

### 3.1 How the System Automatically Determines Attendance

**When a student scans their QR code:**

1. **QR Scanner Reads Student Data**
   - Extracts LRN, Name, Grade, Section, Token
   - Validates QR authenticity

2. **System Checks Current Time & Day**
   - Gets current:
     - Day of week (e.g., Monday)
     - Current time (e.g., 8:15 AM)
     - Current date

3. **System Queries Student's Schedule**
   - Finds all schedules for this student (via LRN)
   - Filters schedules by:
     - Current day of week
     - Current time falls within start_time and end_time range

4. **System Identifies the Active Schedule**
   - Example logic:
   ```
   Current: Monday, 8:15 AM
   
   Student Schedule (Monday):
   - Oral Communication: 8:00 AM - 9:00 AM ✓ MATCH
   - (No other Monday classes at this time)
   
   Result: Mark attendance for "Oral Communication"
   ```

5. **System Determines Attendance Status**
   - **Present:** Scanned within first 10-15 minutes of class
   - **Late:** Scanned after grace period but before class ends
   - **Absent:** Not scanned at all

6. **System Records Attendance**
   - Saves to database:
     - Student LRN
     - Student Name
     - Subject
     - Grade & Section
     - Teacher
     - Date
     - Time Scanned
     - Status (Present/Late)

7. **System Prevents Duplicate Scans**
   - If student already scanned for this schedule today:
     - Display: "Attendance already recorded"
     - No duplicate entry

### 3.2 Edge Cases Handled

**Multiple Classes at Same Time:**
- If student is enrolled in overlapping schedules (error in registration)
- System alerts teacher/admin to fix schedule conflict

**Scan Outside Schedule Time:**
- Student scans QR at 3:00 PM, but has no class at that time
- System displays: "No active class found for your schedule"

**Wrong Day:**
- Student scans on Wednesday, but class is Monday
- System: "You have no scheduled class today at this time"

**After Class Ended:**
- Student tries to scan after end_time
- System can either:
  - Mark as "Absent" (scan too late)
  - Or allow late submission with "Very Late" status

---

## 4. Validation Rules & Business Logic

### 4.1 Student CRUD Validation

**When Creating/Editing Students:**

✓ **LRN Validation:**
- Must be exactly 12 digits
- Must be unique (no duplicates)
- System checks existing database before saving
- Error: "LRN already exists" if duplicate found

✓ **Name Validation:**
- Required field
- Minimum 2 characters
- Letters, spaces, and hyphens only
- Example valid: "Juan Dela Cruz", "Maria-Santos"

✓ **Grade & Section:**
- Must be selected from dropdown (prevents typos)
- System validates grade-section combination exists
- Cannot assign student to non-existent class

✓ **Schedule Enrollment:**
- Student can only enroll in schedules matching their Grade & Section
- System filters available schedules automatically
- Warning if enrolling in conflicting time slots

✓ **QR Code Generation:**
- Can only generate if student has valid LRN and Name
- Regenerating creates new security token
- Old QR becomes invalid when regenerated

**When Deleting Students:**

⚠️ **Soft Delete (Recommended):**
- Student record marked as "Inactive"
- Attendance history preserved
- Can be reactivated later
- Shown in "Archived Students" section

⚠️ **Hard Delete (Permanent):**
- Confirmation required: "Are you sure? This action cannot be undone."
- Only allowed if:
  - Student has zero attendance records, OR
  - Teacher confirms deletion of attendance data
- System shows impact: "This will delete X attendance records"

### 4.2 Schedule CRUD Validation

**When Creating Schedules:**

✓ **Time Conflict Detection:**
- System checks for overlapping schedules for same teacher
- Example conflict:
  ```
  Existing: Monday 8:00-9:00 AM - Math (Grade 11-A)
  New:      Monday 8:30-9:30 AM - Science (Grade 11-B)
  
  Error: "You have a conflicting schedule at this time"
  ```
- Prevents double-booking

✓ **Logical Time Validation:**
- End time must be after start time
- Minimum duration: 30 minutes
- Maximum duration: 4 hours (configurable)
- Error: "Invalid time range"

✓ **Duplicate Prevention:**
- Cannot create identical schedule (same subject, grade, section, day, time)
- Warning: "Similar schedule already exists. Continue?"

**When Editing Schedules:**

⚠️ **Impact Analysis:**
- System shows number of enrolled students
- Shows existing attendance records count
- Warning if editing affects:
  - Students currently enrolled
  - Historical attendance data
  
⚠️ **Time Change Validation:**
- If changing time/day with existing attendance:
  - Option 1: Keep old attendance records as-is
  - Option 2: Move attendance to new time (admin only)
  - Recommended: Create new schedule instead

**When Deleting Schedules:**

⚠️ **Cascading Effects:**
- Check 1: Enrolled students count
  - "35 students are enrolled. Proceed?"
  
- Check 2: Attendance records exist
  - "This schedule has 120 attendance records"
  - Options:
    - Archive instead (recommended)
    - Transfer students to another schedule
    - Delete everything (requires admin confirmation)

⚠️ **Active Session Check:**
- Cannot delete if attendance session is currently running
- Error: "End the active session first"

### 4.3 QR Code Management Rules

**Generation Rules:**

✓ **One QR per Student:**
- Each student has only one active QR code
- Contains: LRN, Name, Grade, Section, Security Token
- QR is universal for all enrolled subjects

✓ **Regeneration Scenarios:**
- Student lost their QR code
- Student information changed (name, grade, section)
- Security concern (QR compromised)
- Action: Old QR token becomes invalid

✓ **Bulk Generation:**
- Maximum 100 students per batch (performance)
- Generated asynchronously for large batches
- Download as ZIP file or print-ready PDF

**Security Token:**
- Unique hash generated per student
- Stored in database alongside student record
- QR validation checks token match
- Token expires if student is deactivated

### 4.4 Attendance Session Rules

**Starting Session:**

✓ **Prerequisites:**
- Teacher must be logged in
- Schedule must be Active status
- Current day/time must match schedule
  - Example: Cannot start "Monday 8AM" session on Tuesday
  - Grace period: ±15 minutes (configurable)

✓ **One Session at a Time:**
- Teacher can only run one active session
- Must end current session before starting another
- System prevents accidental double sessions

**During Session:**

✓ **QR Scan Validation:**
1. Decode QR data
2. Validate student exists (LRN lookup)
3. Check security token match
4. Verify student enrolled in this schedule
5. Check not already scanned today for this subject
6. Determine Present/Late based on current time
7. Record attendance

✓ **Duplicate Prevention:**
- Student scans QR twice → "Already recorded"
- Same-day, same-subject attendance only once
- Shows previous scan time

✓ **Late Threshold:**
- Configurable grace period (default: 15 minutes)
- Within 0-15 min of start: "Present"
- After 15 min: "Late" (with timestamp)
- After class ends: "Absent" or "Very Late" (teacher decides)

**Ending Session:**

✓ **Auto-Marking Absent:**
- When teacher ends session
- All enrolled students not scanned = marked "Absent"
- Teacher can manually override before finalizing

---

## 5. Database Structure

### 5.1 Core Tables

**teachers**
```
teacher_id (PK)
full_name
employee_id
email
password_hash
department
created_at
status (Active/Inactive)
```

**students**
```
student_id (PK)
lrn (Unique)
full_name
grade_level
section
contact_number
email
qr_token (for validation)
created_at
```

**schedules**
```
schedule_id (PK)
teacher_id (FK)
subject_name
grade_level
section
day_of_week
start_time
end_time
room
semester
school_year
status (Active/Inactive)
created_at
```

**student_schedules** (Enrollment Table)
```
enrollment_id (PK)
student_id (FK)
schedule_id (FK)
enrolled_at
status (Active/Dropped)
```

**attendance**
```
attendance_id (PK)
student_id (FK)
schedule_id (FK)
teacher_id (FK)
date
time_scanned
status (Present/Late/Absent)
remarks
created_at
```

### 5.2 Relational Logic

```
Students ←→ Student_Schedules ←→ Schedules ←→ Teachers
                                      ↓
                                  Attendance
```

---

## 6. Complete System Flow (Step-by-Step)

### Phase 1: Setup (Start of Semester)

1. **Teacher Registration**
   - Teachers register accounts
   - Admin approves (optional)

2. **Teacher Creates Schedules**
   - Teacher logs in
   - Creates all class schedules
   - Schedules become available in database

3. **Student Registration**
   - Students register their information
   - Students select their enrolled subjects from available schedules
   - Student enrollment records created

4. **Student Generates QR**
   - Student generates universal QR code
   - Downloads and saves to phone

### Phase 2: Daily Attendance

**Morning/Before Class:**

1. **Teacher Starts Session**
   - Teacher logs in
   - Goes to "Start Attendance Session"
   - Selects schedule (e.g., "Monday 8:00 AM - Oral Communication - Grade 11 ICT-A")
   - QR Scanner activates

2. **Students Arrive & Scan**
   - Student opens saved QR code on phone
   - Presents to scanner/webcam
   - System:
     - Reads QR
     - Identifies student
     - Checks current day/time
     - Matches to student's schedule
     - Determines correct subject automatically
     - Marks Present/Late
     - Shows confirmation: "Juan Dela Cruz - Present - Oral Communication"

3. **Real-Time Feedback**
   - Teacher sees live attendance list on screen
   - Running count of Present/Late/Absent

4. **Teacher Ends Session**
   - Teacher clicks "End Session"
   - Final attendance is saved
   - Absent students are automatically marked

### Phase 3: Reporting

1. **Teacher Views Records**
   - Teacher navigates to "Attendance Records"
   - Filters by date, subject, class
   - Views detailed attendance list

2. **Export Reports**
   - Teacher exports to PDF/Excel
   - Sends to admin/principal
   - Generates weekly/monthly summaries

---

## 7. Key Features Summary

### For Teachers:
✓ Secure login/registration
✓ Create and manage class schedules
✓ Start/stop attendance sessions
✓ Real-time attendance monitoring
✓ View historical records
✓ Export reports
✓ Dashboard with statistics

### For Students:
✓ Simple one-time registration (no login)
✓ Select enrolled subjects from teacher-created schedules
✓ Generate universal QR code
✓ One QR for all subjects
✓ Automatic attendance tracking
✓ No manual subject selection needed

### System Intelligence:
✓ Auto-detects correct subject based on time/day
✓ Prevents duplicate scans
✓ Validates QR authenticity
✓ Determines Present/Late status automatically
✓ Handles schedule conflicts
✓ Organized by date → grade → section → subject
✓ Auto-creates daily attendance records

---

## 8. User Experience Flow

### Student Journey:
```
1. Register once (5 min)
   ↓
2. Select subjects from available schedules
   ↓
3. Generate QR code (30 sec)
   ↓
4. Save to phone
   ↓
5. Scan QR every class (3 sec)
   ↓
6. Attendance automatically recorded
```

### Teacher Journey:
```
1. Register account (5 min)
   ↓
2. Create class schedules (10 min one-time)
   ↓
3. Start attendance session (click button)
   ↓
4. Students scan QR codes
   ↓
5. Monitor real-time attendance
   ↓
6. End session
   ↓
7. View/export reports anytime
```

---

## 9. Technical Implementation Notes

**QR Code Contains:**
- Student LRN (primary key)
- Name, Grade, Section
- Security token

**QR Scanner Logic:**
```javascript
1. Scan QR → Extract LRN
2. Query database for student by LRN
3. Get current datetime
4. Find student's schedules for today
5. Match current time to schedule time range
6. Identify active subject
7. Check if already scanned today for this subject
8. If not → Mark attendance (Present/Late)
9. If yes → Display "Already recorded"
```

**Schedule Matching Algorithm:**
```sql
SELECT s.* FROM schedules s
JOIN student_schedules ss ON s.schedule_id = ss.schedule_id
WHERE ss.student_id = ?
  AND s.day_of_week = DAYNAME(NOW())
  AND CURRENT_TIME() BETWEEN s.start_time AND s.end_time
  AND s.status = 'Active'
LIMIT 1
```

---

## 10. Benefits of This Enhanced Flow

1. **For Students:**
   - No need to remember passwords
   - One QR for everything
   - No manual subject selection
   - Fast attendance (3 seconds)
   - Can't mark wrong subject by mistake

2. **For Teachers:**
   - Complete control over schedules
   - Easy session management
   - Real-time monitoring
   - Accurate data collection
   - Less manual work

3. **For School:**
   - Automated system
   - Accurate records
   - Reduced paper usage
   - Easy reporting
   - Scalable to entire school

---

## 11. Security Features

- Teacher passwords hashed (bcrypt)
- QR codes contain unique security tokens
- Session-based authentication
- SQL injection prevention (prepared statements)
- Duplicate scan prevention
- Schedule validation
- Access control (teachers can only see their classes)

---

## 13. Complete CRUD Operations Summary

### Main Navigation Menu:
```
📊 Dashboard
👥 Student Management
   ├── View All Students
   ├── Register New Student
   └── Bulk Import Students
📅 Schedule Management
   ├── View All Schedules
   ├── Create New Schedule
   └── Calendar View
📝 Attendance
   ├── Start Session
   ├── View Records
   └── Reports
🔧 Settings
🚪 Logout
```

### 11.1 Student Management Interface

**View All Students Page:**
```
┌─────────────────────────────────────────────────────────┐
│  Student Management                                      │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  [+ Register New Student]  [📤 Import CSV]  [🖨️ Bulk QR]│
│                                                           │
│  Search: [___________]  Grade: [All ▼]  Section: [All ▼]│
│                                                           │
│  ┌─────┬──────────┬─────────────────┬───────┬─────────┐ │
│  │ LRN │   Name   │  Grade-Section  │  QR   │ Actions │ │
│  ├─────┼──────────┼─────────────────┼───────┼─────────┤ │
│  │ 123 │ Juan DC  │   11 - ICT-A    │  ✓    │ [📝][🗑️]│ │
│  │ 456 │ Maria S  │   11 - ICT-A    │  ✓    │ [📝][🗑️]│ │
│  │ 789 │ Pedro R  │   12 - HUMSS    │  ✗    │ [📝][🗑️]│ │
│  └─────┴──────────┴─────────────────┴───────┴─────────┘ │
│                                                           │
│  Showing 1-10 of 150 students           [1][2][3][Next] │
└─────────────────────────────────────────────────────────┘

Actions per row:
📝 = Edit student details
🗑️ = Delete/Archive student
Click on row = View full student profile
```

**Register/Edit Student Form:**
```
┌─────────────────────────────────────────────────┐
│  Register New Student / Edit Student             │
├─────────────────────────────────────────────────┤
│                                                   │
│  Student Information                              │
│  ────────────────────────                         │
│  LRN:              [____________]                 │
│  Full Name:        [____________]                 │
│  Grade Level:      [11 ▼]                        │
│  Section:          [ICT-A ▼]                     │
│  Contact Number:   [____________] (optional)      │
│  Email:            [____________] (optional)      │
│                                                   │
│  Enroll to Schedules                              │
│  ────────────────────                             │
│  ☑ Oral Communication (Mon 8:00-9:00 AM)         │
│  ☑ Mathematics (Tue 9:00-10:00 AM)               │
│  ☐ Physical Education (Wed 2:00-3:00 PM)         │
│  ☑ Filipino (Thu 10:00-11:00 AM)                 │
│                                                   │
│  QR Code Options                                  │
│  ────────────────────                             │
│  ☑ Generate QR Code immediately                  │
│  ☐ Send QR to student email                      │
│                                                   │
│  [Cancel]  [Save Student]  [Save & Generate QR]  │
└─────────────────────────────────────────────────┘
```

**Student Details/Profile Modal:**
```
┌─────────────────────────────────────────────────┐
│  Student Profile                         [✕]     │
├─────────────────────────────────────────────────┤
│                                                   │
│  ┌─────────────┐                                 │
│  │             │  Juan Dela Cruz                 │
│  │  [QR CODE]  │  LRN: 123456789012              │
│  │             │  Grade 11 - ICT-A               │
│  └─────────────┘                                 │
│  [Download QR]  [Print QR]  [Regenerate QR]      │
│                                                   │
│  Enrolled Subjects (4)                            │
│  ────────────────────                             │
│  • Oral Communication (Mon 8:00 AM)              │
│  • Mathematics (Tue 9:00 AM)                     │
│  • Filipino (Thu 10:00 AM)                       │
│  • Physical Education (Fri 2:00 PM)              │
│                                                   │
│  Attendance Summary                               │
│  ────────────────────                             │
│  Total Days: 45                                   │
│  Present: 42 (93.3%)                             │
│  Late: 2 (4.4%)                                  │
│  Absent: 1 (2.2%)                                │
│                                                   │
│  [Edit Info]  [View Full Attendance]  [Delete]   │
└─────────────────────────────────────────────────┘
```

### 11.2 Schedule Management Interface

**View All Schedules Page:**
```
┌─────────────────────────────────────────────────────────┐
│  Schedule Management                                     │
├─────────────────────────────────────────────────────────┤
│                                                           │
│  [+ Create New Schedule]  [📅 Calendar View]             │
│                                                           │
│  Filter: Day: [All ▼]  Grade: [All ▼]  Status: [All ▼] │
│                                                           │
│  ┌────────┬──────────┬───────┬─────────┬────────┬──────┐│
│  │Subject │Grd-Sec   │Day    │Time     │Students│Action││
│  ├────────┼──────────┼───────┼─────────┼────────┼──────┤│
│  │Oral Com│11-ICT-A  │Monday │8:00-9:00│  35    │[📝][🗑️]││
│  │Math    │11-ICT-A  │Tuesday│9:00-10  │  35    │[📝][🗑️]││
│  │PE      │11-ICT-B  │Wed    │2:00-3:00│  30    │[📝][🗑️]││
│  │Filipino│12-HUMSS  │Thu    │10:00-11 │  40    │[📝][🗑️]││
│  └────────┴──────────┴───────┴─────────┴────────┴──────┘│
│                                                           │
│  🟢 Active: 15 schedules  🔴 Inactive: 2 schedules      │
└─────────────────────────────────────────────────────────┘
```

**Create/Edit Schedule Form:**
```
┌─────────────────────────────────────────────────┐
│  Create New Schedule / Edit Schedule             │
├─────────────────────────────────────────────────┤
│                                                   │
│  Schedule Details                                 │
│  ────────────────────                             │
│  Subject Name:     [_____________________]        │
│  Grade Level:      [11 ▼]                        │
│  Section:          [ICT-A ▼]                     │
│                                                   │
│  Time & Day                                       │
│  ────────────────────                             │
│  Day of Week:      [Monday ▼]                    │
│  Start Time:       [08:00 ▼]                     │
│  End Time:         [09:00 ▼]                     │
│  Duration:         1 hour (auto-calculated)       │
│                                                   │
│  Additional Info                                  │
│  ────────────────────                             │
│  Room/Location:    [Room 101]                    │
│  Semester:         [1st Semester ▼]              │
│  School Year:      [2025-2026 ▼]                │
│                                                   │
│  Status:           ○ Active  ○ Inactive           │
│                                                   │
│  ⚠️ Warning: 35 students are enrolled             │
│     Editing may affect their attendance records   │
│                                                   │
│  [Cancel]  [Save Schedule]                        │
└─────────────────────────────────────────────────┘
```

**Calendar View (Weekly Schedule Overview):**
```
┌─────────────────────────────────────────────────────────┐
│  Weekly Schedule - Teacher: John Doe                     │
├─────────────────────────────────────────────────────────┤
│         │  Mon  │  Tue  │  Wed  │  Thu  │  Fri  │      │
│  ───────┼───────┼───────┼───────┼───────┼───────┤      │
│  8:00   │ Oral  │       │       │       │       │      │
│  9:00   │ Com   │ Math  │       │       │       │      │
│  10:00  │ 11-A  │ 11-A  │       │Fili-  │       │      │
│  11:00  │       │       │       │pino   │       │      │
│  12:00  │       │       │       │12-H   │       │      │
│  1:00   │       │       │       │       │       │      │
│  2:00   │       │       │  PE   │       │       │      │
│  3:00   │       │       │ 11-B  │       │       │      │
│  ───────┴───────┴───────┴───────┴───────┴───────┘      │
│                                                           │
│  Click on any slot to create/edit schedule              │
│  Color Legend: 🟢 Active  🔴 Inactive  ⚫ Conflict       │
└─────────────────────────────────────────────────────────┘
```

### 11.3 Bulk Operations Interface

**Bulk Import Students (CSV Upload):**
```
┌─────────────────────────────────────────────────┐
│  Bulk Import Students                             │
├─────────────────────────────────────────────────┤
│                                                   │
│  Step 1: Download Template                        │
│  [📥 Download CSV Template]                       │
│                                                   │
│  Step 2: Fill Template with Student Data          │
│  Expected format:                                 │
│  LRN, Full Name, Grade, Section, Contact, Email  │
│                                                   │
│  Step 3: Upload Completed File                    │
│  ┌─────────────────────────────────────────┐     │
│  │  Drag & drop CSV file here              │     │
│  │  or click to browse                     │     │
│  └─────────────────────────────────────────┘     │
│  [Browse Files]                                   │
│                                                   │
│  Step 4: Select Default Schedules (Optional)      │
│  Auto-enroll all students to:                    │
│  ☐ Oral Communication (Mon 8:00 AM)              │
│  ☐ Mathematics (Tue 9:00 AM)                     │
│                                                   │
│  Options                                          │
│  ☑ Generate QR codes for all students            │
│  ☐ Send QR codes to student emails               │
│                                                   │
│  [Cancel]  [Import Students]                      │
└─────────────────────────────────────────────────┘
```

**Bulk QR Code Generation:**
```
┌─────────────────────────────────────────────────┐
│  Bulk QR Code Generation                          │
├─────────────────────────────────────────────────┤
│                                                   │
│  Select Students                                  │
│  ────────────────────                             │
│  Grade: [11 ▼]  Section: [ICT-A ▼]              │
│                                                   │
│  ☑ Select All (35 students)                      │
│  ☐ Juan Dela Cruz (LRN: 123...)                 │
│  ☐ Maria Santos (LRN: 456...)                   │
│  ☐ Pedro Reyes (LRN: 789...)                    │
│  ... (show all students with checkboxes)          │
│                                                   │
│  Output Format                                    │
│  ────────────────────                             │
│  ○ Individual PNG files (ZIP download)            │
│  ● Print-ready PDF (4 QRs per page)              │
│  ○ Individual PDF files (ZIP download)            │
│                                                   │
│  QR Code Size                                     │
│  ────────────────────                             │
│  ○ Small (128x128)                               │
│  ● Medium (256x256)                              │
│  ○ Large (512x512)                               │
│                                                   │
│  Include in QR                                    │
│  ────────────────────                             │
│  ☑ Student Name                                  │
│  ☑ LRN                                           │
│  ☑ Grade & Section                               │
│                                                   │
│  [Cancel]  [Generate QR Codes]                    │
└─────────────────────────────────────────────────┘
```

---

## 13. Complete CRUD Operations Summary

### Student Management CRUD:

| Operation | Teacher Can Do | Details |
|-----------|----------------|---------|
| **Create** | Register new students manually | Single or bulk import via CSV |
| **Read** | View all students, search, filter | See student profiles, QR status, attendance |
| **Update** | Edit student information | Name, grade, section, contact, enrolled subjects |
| **Delete** | Remove or archive students | Soft delete (deactivate) or hard delete |
| **Extra** | Generate/regenerate QR codes | Single or bulk generation, download, print |

### Schedule Management CRUD:

| Operation | Teacher Can Do | Details |
|-----------|----------------|---------|
| **Create** | Add new class schedules | Define subject, time, day, grade, section |
| **Read** | View all schedules | List view or calendar view, filter options |
| **Update** | Edit schedule details | Change time, room, day, subject name |
| **Delete** | Remove schedules | With validation for enrolled students |
| **Extra** | Activate/deactivate schedules | Toggle status without deleting |
| **Extra** | Duplicate schedules | Create similar schedules quickly |

### Additional Teacher Powers:

✓ **Full control over student data** - Register, edit, delete students
✓ **Complete schedule management** - Create, modify, organize class schedules  
✓ **QR code management** - Generate, regenerate, download QR codes
✓ **Bulk operations** - Import students, generate multiple QRs at once
✓ **Attendance override** - Manually mark students present/absent if needed
✓ **Report generation** - Export data in PDF/Excel formats
✓ **Student enrollment** - Add/remove students from schedules

---

## End of Enhanced System Flow

This flow ensures:
- **Simplicity** for students
- **Control** for teachers
- **Intelligence** from the system
- **Accuracy** in attendance tracking
- **Scalability** for school-wide deployment

Ready for implementation! 🚀

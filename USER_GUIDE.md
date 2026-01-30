# BNH QR Attendance System (BNH_ATS) — User Guide

## 1) Login

### Admin (pre-created)

- Email: `admin@gmail.com`
- Password: `Bicos@123`

### Teacher

- Teachers login using the account created for them.
- If teacher registration/approval is enabled in your deployment, teachers may need Admin approval before they can use the system.

## 2) Recommended setup order (important)

Follow this order to avoid missing schedules/enrollments:

1. Admin checks school settings (grade/section setup, approvals)
2. Teachers create schedules for the whole week
3. Students register and enroll into schedules
4. Admin starts Day Scanning (daily)
5. Teachers scan QR codes (Day Scanner / Session Scanner)
6. View attendance records and reports

## 3) Admin workflow

### A. First login checks

- Go to **Settings** and verify the Grade Levels / Sections are correct.
- If the system uses teacher approvals, go to **Teacher Approvals** and approve pending teacher accounts.

### B. Manage schedules (Admin)

- Admin can view and manage schedules from **Schedules**.
- Admin can create/edit schedules and assign them to a teacher (if enabled).
- Admin can activate/deactivate/archive schedules.

### C. Day Scanning control (daily)

- Go to **Day Control**.
- Set the scanning status to **ACTIVE** for the day.
- Configure late/grace rules if your school uses them.

## 4) Teacher workflow

### A. Create schedules for the whole week (required before student enrollment)

1. Login as Teacher
2. Go to **Schedules**
3. Create schedules for each day you teach (e.g., Monday to Friday)
4. Ensure schedules are **Active**

Tips:

- Create schedules before students register so students can immediately see available schedules for their Grade/Section.
- If you handle multiple Grade/Sections, create schedules for each Grade/Section you teach.

### B. Scan attendance

#### Day Scanner

- Open **Day Scanner**
- Tap **Start Camera**
- Scan student QR codes

#### Attendance Session (if used by your school)

- Open the session scanner and start the camera.
- Scan students for that session.

## 5) Student workflow

### A. Student registration

1. Go to the **Student Registration** page
2. Enter student details (LRN, name, Grade, Section)
3. Select available schedules for the chosen Grade/Section
4. Submit registration

### B. Getting the QR code

- After registration, the student QR code can be viewed/downloaded from the student QR page/profile.
- Students should present the QR code during scanning.

## 6) Viewing attendance records

- Use **Attendance Records** to filter by date, grade, section, schedule/session (if applicable), and status.
- Use the built-in filters to review totals and details.

## 7) Mobile scanning notes (Android / iOS)

- Many mobile browsers require **HTTPS** to allow camera access when opening the site via an IP address (e.g., `http://192.168.x.x`).
- If you plan to scan using phones over hotspot/Wi‑Fi, set up HTTPS for your site.

# BNH_ATS (BNH QR Attendance System)

BNH_ATS is a PHP + MySQL (XAMPP) web app for QR-based attendance.

## Requirements

- XAMPP (Apache + MySQL) or any PHP 8+ web stack
- MySQL / MariaDB
- Browser with camera support (Chrome/Edge recommended)

## Quick Setup (Windows / XAMPP)

1) **Install XAMPP**

- Install XAMPP and start:
  - Apache
  - MySQL

2) **Clone the project into `htdocs`**

- Put the folder here:
  - `C:\xampp\htdocs\BNH_ATS`

3) **Create database + import schema**

- Open phpMyAdmin:
  - `http://localhost/phpmyadmin`

- Create DB:
  - `attendance_system`

- Import schema (choose ONE):
  - `schema.db` (**recommended**; this is a MySQL schema script even though the filename ends in `.db`)
  - `database/attendance_system.sql` (may be older; the app can auto-add some missing tables/columns at runtime)

4) **Configure base URL + DB credentials**

- `config/config.php`
  - `APP_BASE_URL` should match your folder name (default is `/BNH_ATS`)

- `config/config.php`
  - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS`

5) **(Optional) Install PHP dependencies**

If your repo does not include `vendor/`:

- Run in the project root:
  - `composer install`

## Run the app

- Visit:
  - `http://localhost/BNH_ATS/login.php`

## Create the first Admin account

There is no default admin user.

Recommended approach:

1) Register a teacher account:
- `http://localhost/BNH_ATS/register.php`

2) In phpMyAdmin, open table `teachers` and update that user:
- `role` = `Admin`
- `status` = `Active`
- `approval_status` = `Approved`

Then you can login normally.

## Notes / Troubleshooting

- **Camera access**: allow camera permission in the browser.
- **Mobile camera**: some devices/browsers require HTTPS for camera access. `localhost` usually works without HTTPS.
- If you changed the project folder name, update `APP_BASE_URL` accordingly.

<?php

declare(strict_types=1);

require_once __DIR__ . '/lib/helpers.php';

if (isset($_SESSION['teacher_id'])) {
    redirect('attendance_records.php');
}

$pdo = db();

$studentsEnrolled = 0;
$attendanceRate = 0;
$scansToday = 0;

try {
    $studentsEnrolled = (int)($pdo->query("SELECT COUNT(*) FROM students WHERE status = 'Active'")->fetchColumn() ?: 0);

    $today = new DateTimeImmutable('today');
    $from = $today->sub(new DateInterval('P30D'))->format('Y-m-d');
    $to = $today->format('Y-m-d');

    $stmt = $pdo->prepare(
        'SELECT
            SUM(CASE WHEN status IN ("Present","Late") THEN 1 ELSE 0 END) AS present_late,
            COUNT(*) AS total
         FROM attendance
         WHERE date BETWEEN :from AND :to'
    );
    $stmt->execute([':from' => $from, ':to' => $to]);
    $row = $stmt->fetch();
    $presentLate = (int)($row['present_late'] ?? 0);
    $total = (int)($row['total'] ?? 0);
    $attendanceRate = $total > 0 ? (int)round(($presentLate / $total) * 100) : 0;

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM attendance WHERE date = :d AND status IN ("Present","Late")');
    $stmt->execute([':d' => $to]);
    $scansToday = (int)($stmt->fetchColumn() ?: 0);
} catch (Throwable $e) {
    $studentsEnrolled = 0;
    $attendanceRate = 0;
    $scansToday = 0;
}

$title = 'BNHS Attendance';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= h($title) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;600;700;800&family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="<?= h(url('assets/bicos.css')) ?>" rel="stylesheet">
</head>
<body class="bnhs-landing">

<header class="bnhs-landing-nav">
  <div class="bnhs-landing-nav-inner">
    <a class="bnhs-landing-brand" href="<?= h(url('index.php')) ?>">
      <img class="bnhs-brand-logo" src="<?= h(url('ui_ux_design/bicos%20logo.png')) ?>" alt="Bicos NHS Logo">
      <div>
        <div class="bnhs-landing-brand-title">BNHS Attendance</div>
        <div class="bnhs-landing-brand-sub">QR Code System</div>
      </div>
    </a>

    <nav class="d-flex align-items-center gap-2">
      <a class="btn btn-outline-light btn-sm" href="#home">Home</a>
      <a class="btn btn-outline-light btn-sm" href="<?= h(url('student_register.php')) ?>">Generate QR</a>
      <a class="btn btn-light btn-sm" href="<?= h(url('login.php')) ?>">Teacher Login</a>
    </nav>
  </div>
</header>

<main id="home">
  <section class="bnhs-landing-hero">
    <div class="bnhs-landing-hero-inner">
      <div class="bnhs-hero-grid">
        <div class="bnhs-hero-copy">
          <div class="bnhs-pill">Bicos National High School</div>
          <h1 class="bnhs-hero-title">QR Code-Based<br><span>Attendance System</span></h1>
          <p class="bnhs-hero-sub">
            A modern, efficient way to track student attendance using QR codes.
            Fast scanning, real-time tracking, and comprehensive reports.
          </p>

          <div class="bnhs-hero-actions justify-content-start">
            <a class="bnhs-btn-hero bnhs-btn-red" href="<?= h(url('student_register.php')) ?>">Generate QR Code</a>
            <a class="bnhs-btn-hero bnhs-btn-outline" href="<?= h(url('login.php')) ?>">Teacher Login</a>
          </div>
        </div>

        <div class="bnhs-hero-gallery" aria-hidden="true">
          <img class="bnhs-hero-img bnhs-lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?= h(url('ui_ux_design/bicos.jpg')) ?>" alt="" loading="lazy" decoding="async">
          <img class="bnhs-hero-img bnhs-lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?= h(url('ui_ux_design/bicos2.jpg')) ?>" alt="" loading="lazy" decoding="async">
          <img class="bnhs-hero-img bnhs-lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?= h(url('ui_ux_design/bicos3.jpg')) ?>" alt="" loading="lazy" decoding="async">
          <img class="bnhs-hero-img bnhs-lazy" src="data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==" data-src="<?= h(url('ui_ux_design/bicos4.jpg')) ?>" alt="" loading="lazy" decoding="async">
        </div>
      </div>
    </div>
  </section>

  <section class="bnhs-landing-stats">
    <div class="bnhs-landing-stats-inner">
      <div class="bnhs-stat">
        <div class="bnhs-stat-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M22 21v-2a4 4 0 0 0-3-3.87" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M16 3.13a4 4 0 0 1 0 7.75" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="bnhs-stat-val"><?= number_format($studentsEnrolled) ?></div>
        <div class="bnhs-stat-label">Students Enrolled</div>
      </div>
      <div class="bnhs-stat">
        <div class="bnhs-stat-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="bnhs-stat-val"><?= (int)$attendanceRate ?>%</div>
        <div class="bnhs-stat-label">Attendance Rate (Last 30 Days)</div>
      </div>
      <div class="bnhs-stat">
        <div class="bnhs-stat-icon" aria-hidden="true">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 6v6l4 2" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M12 22a10 10 0 1 0 0-20 10 10 0 0 0 0 20Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
        </div>
        <div class="bnhs-stat-val"><?= number_format($scansToday) ?></div>
        <div class="bnhs-stat-label">Scans Today</div>
      </div>
    </div>
  </section>

  <section class="bnhs-section">
    <div class="bnhs-section-inner">
      <h2 class="bnhs-section-title">How It Works</h2>
      <p class="bnhs-section-sub">
        Our attendance system is designed to be simple, fast, and reliable for both students and teachers.
      </p>

      <div class="bnhs-feature-grid">
        <div class="bnhs-feature">
          <div class="bnhs-feature-icon blue">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M7 3H5a2 2 0 0 0-2 2v2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M17 3h2a2 2 0 0 1 2 2v2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 21H5a2 2 0 0 1-2-2v-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M17 21h2a2 2 0 0 0 2-2v-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M8 8h3v3H8V8Zm5 0h3v3h-3V8ZM8 13h3v3H8v-3Zm5 5h3" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </div>
          <h3 class="bnhs-feature-title">QR Code Generation</h3>
          <p class="bnhs-feature-text">Students generate unique QR codes containing their LRN and class information.</p>
        </div>

        <div class="bnhs-feature">
          <div class="bnhs-feature-icon red">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 7V5a2 2 0 0 1 2-2h2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M20 7V5a2 2 0 0 0-2-2h-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M4 17v2a2 2 0 0 0 2 2h2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M20 17v2a2 2 0 0 1-2 2h-2" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M7 12h10" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <h3 class="bnhs-feature-title">Quick Scanning</h3>
          <p class="bnhs-feature-text">Teachers scan student QR codes to instantly record attendance.</p>
        </div>

        <div class="bnhs-feature">
          <div class="bnhs-feature-icon green">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M4 19V5" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M8 19V11" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M12 19V7" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M16 19v-5" stroke="white" stroke-width="2" stroke-linecap="round"/>
              <path d="M20 19V9" stroke="white" stroke-width="2" stroke-linecap="round"/>
            </svg>
          </div>
          <h3 class="bnhs-feature-title">Real-time Dashboard</h3>
          <p class="bnhs-feature-text">View attendance statistics, generate reports, and track patterns.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="bnhs-cta">
    <div class="bnhs-cta-inner">
      <h2 class="bnhs-section-title">Ready to Get Started?</h2>
      <p class="bnhs-section-sub">Choose your role to begin using the attendance system.</p>
      <div class="bnhs-hero-actions">
        <a class="bnhs-btn-hero bnhs-btn-navy" href="<?= h(url('student_register.php')) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M4 7V5a2 2 0 0 1 2-2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M20 7V5a2 2 0 0 0-2-2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M4 17v2a2 2 0 0 0 2 2h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M20 17v2a2 2 0 0 1-2 2h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M7 12h10" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          I'm a Student
        </a>
        <a class="bnhs-btn-hero bnhs-btn-red" href="<?= h(url('login.php')) ?>">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M10 17l5-5-5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
            <path d="M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M21 21V3" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
          I'm a Teacher
        </a>
      </div>
    </div>
  </section>
</main>

<footer class="bnhs-footer">
  <span style="color: var(--accent-red); font-weight: 700;">#BICOS</span> we LOVE, we EDUCATE, ONE
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
  (function () {
    const imgs = Array.prototype.slice.call(document.querySelectorAll('img.bnhs-lazy[data-src]'));
    if (!imgs.length) return;

    function loadImg(img) {
      const src = img.getAttribute('data-src');
      if (!src) return;
      img.addEventListener('load', function () {
        img.classList.add('is-loaded');
      }, { once: true });
      img.src = src;
      img.removeAttribute('data-src');
      if (img.complete) {
        img.classList.add('is-loaded');
      }
    }

    if ('IntersectionObserver' in window) {
      const io = new IntersectionObserver(function (entries) {
        entries.forEach(function (e) {
          if (e.isIntersecting) {
            loadImg(e.target);
            io.unobserve(e.target);
          }
        });
      }, { rootMargin: '150px 0px' });

      imgs.forEach(function (img) {
        io.observe(img);
      });
      return;
    }

    imgs.forEach(loadImg);
  })();
</script>
</body>
</html>

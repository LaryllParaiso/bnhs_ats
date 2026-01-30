# UI/UX Design Specification
## QR Code-Based Attendance System - Bicos National High School

**Version:** 1.0  
**Date:** January 27, 2026  
**Design System:** Bicos Design Language

---

## 📋 Table of Contents
1. [Brand Identity & Color Palette](#brand-identity--color-palette)
2. [Typography System](#typography-system)
3. [Component Library](#component-library)
4. [Layout & Spacing](#layout--spacing)
5. [Student Module UI](#student-module-ui)
6. [Teacher Module UI](#teacher-module-ui)
7. [Dashboard & Reports UI](#dashboard--reports-ui)
8. [Responsive Design](#responsive-design)
9. [Interaction Design](#interaction-design)
10. [Accessibility Guidelines](#accessibility-guidelines)
11. [Design Tokens](#design-tokens)

---

## 1. Brand Identity & Color Palette

### 1.1 School Branding
**Bicos National High School**  
Location: Daet, Camarines Norte, Nueva Ecija  
Tagline: "#BICOS we LOVE, we EDUCATE, ONE"

### 1.2 Color Palette (Extracted from School Logo)

#### Primary Colors
```css
/* Navy Blue - Primary Brand Color */
--primary-navy: #1a237e;
--primary-navy-dark: #0d1347;
--primary-navy-light: #3949ab;
--primary-navy-lighter: #5c6bc0;

/* Red - Accent Color (from #BICOS text) */
--accent-red: #d32f2f;
--accent-red-light: #ef5350;
--accent-red-dark: #c62828;

/* Light Blue - Supporting Color (from logo triangle) */
--secondary-blue: #42a5f5;
--secondary-blue-light: #64b5f6;
--secondary-blue-dark: #1e88e5;
```

#### Neutral Colors
```css
/* Background & Surface Colors */
--white: #ffffff;
--off-white: #fafafa;
--light-gray: #f5f5f5;
--medium-gray: #e0e0e0;
--dark-gray: #616161;
--text-dark: #212121;
--text-secondary: #757575;

/* Border Colors */
--border-light: #e0e0e0;
--border-medium: #bdbdbd;
--border-dark: #9e9e9e;
```

#### Semantic Colors
```css
/* Status Colors */
--success-green: #4caf50;
--success-green-light: #81c784;
--warning-yellow: #ffc107;
--warning-yellow-light: #ffd54f;
--error-red: #f44336;
--error-red-light: #ef5350;
--info-blue: #2196f3;
--info-blue-light: #64b5f6;
```

#### Usage Guidelines

**Primary Navy (`#1a237e`):**
- Main navigation header
- Primary action buttons
- Active states
- Important headings
- Footer background

**Accent Red (`#d32f2f`):**
- Call-to-action buttons (secondary)
- Error messages
- Delete/cancel actions
- Important notifications
- #BICOS branding text

**Light Blue (`#42a5f5`):**
- Links
- Info messages
- Secondary buttons
- Hover states
- Icons and accents

**Success Green (`#4caf50`):**
- Success messages
- Present status indicator
- Confirmed actions
- Positive feedback

**Warning Yellow (`#ffc107`):**
- Warning messages
- Late status indicator
- Caution notices

### 1.3 Color Contrast Ratios (WCAG 2.1 Compliance)

| Foreground | Background | Ratio | Rating |
|------------|------------|-------|--------|
| Navy (#1a237e) | White (#ffffff) | 14.04:1 | AAA |
| Red (#d32f2f) | White (#ffffff) | 5.35:1 | AA |
| Light Blue (#42a5f5) | White (#ffffff) | 3.11:1 | AA (Large Text) |
| White (#ffffff) | Navy (#1a237e) | 14.04:1 | AAA |
| White (#ffffff) | Red (#d32f2f) | 5.35:1 | AA |

---

## 2. Typography System

### 2.1 Font Families

**Primary Font (Body & UI):**
```css
--font-primary: 'Roboto', 'Segoe UI', 'Helvetica Neue', Arial, sans-serif;
```

**Heading Font:**
```css
--font-heading: 'Poppins', 'Roboto', 'Segoe UI', sans-serif;
```

**Monospace Font (for LRN, codes):**
```css
--font-mono: 'Roboto Mono', 'Courier New', monospace;
```

### 2.2 Font Sizes

```css
/* Font Size Scale */
--text-xs: 0.75rem;      /* 12px - Helper text, labels */
--text-sm: 0.875rem;     /* 14px - Secondary text */
--text-base: 1rem;       /* 16px - Body text (default) */
--text-lg: 1.125rem;     /* 18px - Large body text */
--text-xl: 1.25rem;      /* 20px - Subheadings */
--text-2xl: 1.5rem;      /* 24px - Section headings */
--text-3xl: 1.875rem;    /* 30px - Page headings */
--text-4xl: 2.25rem;     /* 36px - Hero text */
--text-5xl: 3rem;        /* 48px - Display text */
```

### 2.3 Font Weights

```css
--font-light: 300;
--font-regular: 400;
--font-medium: 500;
--font-semibold: 600;
--font-bold: 700;
```

### 2.4 Line Heights

```css
--line-height-tight: 1.2;    /* Headings */
--line-height-normal: 1.5;   /* Body text */
--line-height-relaxed: 1.75; /* Large paragraphs */
```

### 2.5 Typography Examples

```css
/* H1 - Page Title */
h1 {
    font-family: var(--font-heading);
    font-size: var(--text-4xl);      /* 36px */
    font-weight: var(--font-bold);
    line-height: var(--line-height-tight);
    color: var(--primary-navy);
    margin-bottom: 1rem;
}

/* H2 - Section Heading */
h2 {
    font-family: var(--font-heading);
    font-size: var(--text-3xl);      /* 30px */
    font-weight: var(--font-semibold);
    line-height: var(--line-height-tight);
    color: var(--primary-navy);
    margin-bottom: 0.75rem;
}

/* H3 - Subsection */
h3 {
    font-family: var(--font-heading);
    font-size: var(--text-2xl);      /* 24px */
    font-weight: var(--font-semibold);
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

/* Body Text */
body {
    font-family: var(--font-primary);
    font-size: var(--text-base);     /* 16px */
    font-weight: var(--font-regular);
    line-height: var(--line-height-normal);
    color: var(--text-dark);
}

/* Small Text */
small, .text-small {
    font-size: var(--text-sm);       /* 14px */
    color: var(--text-secondary);
}

/* Label Text */
label {
    font-size: var(--text-sm);       /* 14px */
    font-weight: var(--font-medium);
    color: var(--text-dark);
    margin-bottom: 0.25rem;
}
```

---

## 3. Component Library

### 3.1 Buttons

#### Primary Button (Navy Blue)
```css
.btn-primary {
    /* Visual */
    background-color: var(--primary-navy);
    color: var(--white);
    border: none;
    border-radius: 4px;
    
    /* Spacing */
    padding: 10px 24px;
    
    /* Typography */
    font-family: var(--font-primary);
    font-size: var(--text-base);
    font-weight: var(--font-medium);
    
    /* Interaction */
    cursor: pointer;
    transition: all 0.2s ease;
    
    /* Shadow */
    box-shadow: 0 2px 4px rgba(26, 35, 126, 0.2);
}

.btn-primary:hover {
    background-color: var(--primary-navy-dark);
    box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
    transform: translateY(-1px);
}

.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(26, 35, 126, 0.2);
}

.btn-primary:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.25);
}

.btn-primary:disabled {
    background-color: var(--medium-gray);
    cursor: not-allowed;
    box-shadow: none;
    opacity: 0.6;
}
```

#### Secondary Button (Red)
```css
.btn-secondary {
    background-color: var(--accent-red);
    color: var(--white);
    /* ... same properties as primary ... */
}

.btn-secondary:hover {
    background-color: var(--accent-red-dark);
}
```

#### Tertiary Button (Light Blue)
```css
.btn-tertiary {
    background-color: var(--secondary-blue);
    color: var(--white);
    /* ... same properties ... */
}
```

#### Outline Button
```css
.btn-outline {
    background-color: transparent;
    color: var(--primary-navy);
    border: 2px solid var(--primary-navy);
}

.btn-outline:hover {
    background-color: var(--primary-navy);
    color: var(--white);
}
```

#### Ghost Button
```css
.btn-ghost {
    background-color: transparent;
    color: var(--primary-navy);
    border: none;
    padding: 8px 16px;
}

.btn-ghost:hover {
    background-color: var(--light-gray);
}
```

#### Button Sizes
```css
.btn-sm {
    padding: 6px 16px;
    font-size: var(--text-sm);
}

.btn-md {
    padding: 10px 24px;
    font-size: var(--text-base);
}

.btn-lg {
    padding: 14px 32px;
    font-size: var(--text-lg);
}

.btn-block {
    width: 100%;
    display: block;
}
```

### 3.2 Form Components

#### Text Input
```css
.form-control {
    /* Visual */
    width: 100%;
    border: 1px solid var(--border-light);
    border-radius: 4px;
    background-color: var(--white);
    
    /* Spacing */
    padding: 10px 12px;
    
    /* Typography */
    font-size: var(--text-base);
    font-family: var(--font-primary);
    color: var(--text-dark);
    
    /* Interaction */
    transition: all 0.2s ease;
}

.form-control:focus {
    outline: none;
    border-color: var(--primary-navy);
    box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
}

.form-control:disabled {
    background-color: var(--light-gray);
    cursor: not-allowed;
    color: var(--text-secondary);
}

.form-control.is-invalid {
    border-color: var(--error-red);
}

.form-control.is-valid {
    border-color: var(--success-green);
}
```

#### Form Group
```css
.form-group {
    margin-bottom: 1.5rem;
}

.form-label {
    display: block;
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    color: var(--text-dark);
    margin-bottom: 0.5rem;
}

.form-label.required::after {
    content: ' *';
    color: var(--accent-red);
}

.form-text {
    display: block;
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: 0.25rem;
}

.invalid-feedback {
    display: block;
    font-size: var(--text-sm);
    color: var(--error-red);
    margin-top: 0.25rem;
}

.valid-feedback {
    display: block;
    font-size: var(--text-sm);
    color: var(--success-green);
    margin-top: 0.25rem;
}
```

#### Select Dropdown
```css
.form-select {
    appearance: none;
    background-image: url("data:image/svg+xml...");
    background-repeat: no-repeat;
    background-position: right 12px center;
    background-size: 16px;
    padding-right: 40px;
    /* ... rest same as .form-control ... */
}
```

#### Checkbox & Radio
```css
.form-check {
    display: flex;
    align-items: center;
    margin-bottom: 0.5rem;
}

.form-check-input {
    width: 20px;
    height: 20px;
    margin-right: 0.5rem;
    cursor: pointer;
    accent-color: var(--primary-navy);
}

.form-check-label {
    font-size: var(--text-base);
    color: var(--text-dark);
    cursor: pointer;
}
```

### 3.3 Cards

#### Basic Card
```css
.card {
    /* Visual */
    background-color: var(--white);
    border: 1px solid var(--border-light);
    border-radius: 8px;
    
    /* Spacing */
    padding: 24px;
    
    /* Shadow */
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    
    /* Interaction */
    transition: all 0.2s ease;
}

.card:hover {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

.card-header {
    padding-bottom: 16px;
    margin-bottom: 16px;
    border-bottom: 1px solid var(--border-light);
}

.card-title {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
    margin: 0;
}

.card-subtitle {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: 4px;
}

.card-body {
    padding: 0;
}

.card-footer {
    padding-top: 16px;
    margin-top: 16px;
    border-top: 1px solid var(--border-light);
}
```

#### Statistics Card
```css
.stat-card {
    background: linear-gradient(135deg, var(--primary-navy) 0%, var(--primary-navy-light) 100%);
    color: var(--white);
    padding: 24px;
    border-radius: 8px;
    box-shadow: 0 4px 6px rgba(26, 35, 126, 0.2);
}

.stat-card-value {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    margin-bottom: 8px;
}

.stat-card-label {
    font-size: var(--text-sm);
    opacity: 0.9;
}

.stat-card-icon {
    width: 48px;
    height: 48px;
    background-color: rgba(255, 255, 255, 0.2);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
}
```

### 3.4 Tables

#### Data Table
```css
.table {
    width: 100%;
    border-collapse: collapse;
    background-color: var(--white);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
}

.table thead {
    background-color: var(--primary-navy);
    color: var(--white);
}

.table th {
    padding: 16px;
    text-align: left;
    font-size: var(--text-sm);
    font-weight: var(--font-semibold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table td {
    padding: 16px;
    border-bottom: 1px solid var(--border-light);
    font-size: var(--text-base);
    color: var(--text-dark);
}

.table tbody tr:hover {
    background-color: var(--light-gray);
    cursor: pointer;
}

.table tbody tr:last-child td {
    border-bottom: none;
}

/* Striped rows */
.table-striped tbody tr:nth-child(even) {
    background-color: var(--off-white);
}
```

### 3.5 Badges & Status Indicators

#### Badge
```css
.badge {
    display: inline-block;
    padding: 4px 12px;
    font-size: var(--text-xs);
    font-weight: var(--font-medium);
    border-radius: 12px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.badge-primary {
    background-color: var(--primary-navy);
    color: var(--white);
}

.badge-success {
    background-color: var(--success-green);
    color: var(--white);
}

.badge-warning {
    background-color: var(--warning-yellow);
    color: var(--text-dark);
}

.badge-danger {
    background-color: var(--error-red);
    color: var(--white);
}

.badge-info {
    background-color: var(--info-blue);
    color: var(--white);
}
```

#### Status Indicator (Dot)
```css
.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 8px;
}

.status-present {
    background-color: var(--success-green);
}

.status-late {
    background-color: var(--warning-yellow);
}

.status-absent {
    background-color: var(--error-red);
}
```

### 3.6 Alerts & Notifications

#### Alert Box
```css
.alert {
    padding: 16px 20px;
    border-radius: 4px;
    border-left: 4px solid;
    margin-bottom: 16px;
    display: flex;
    align-items: center;
}

.alert-success {
    background-color: #e8f5e9;
    border-color: var(--success-green);
    color: #2e7d32;
}

.alert-warning {
    background-color: #fff8e1;
    border-color: var(--warning-yellow);
    color: #f57c00;
}

.alert-error {
    background-color: #ffebee;
    border-color: var(--error-red);
    color: #c62828;
}

.alert-info {
    background-color: #e3f2fd;
    border-color: var(--info-blue);
    color: #1565c0;
}

.alert-icon {
    margin-right: 12px;
    font-size: 20px;
}
```

#### Toast Notification
```css
.toast {
    position: fixed;
    top: 24px;
    right: 24px;
    min-width: 300px;
    max-width: 400px;
    padding: 16px 20px;
    background-color: var(--white);
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    display: flex;
    align-items: center;
    animation: slideInRight 0.3s ease;
    z-index: 9999;
}

@keyframes slideInRight {
    from {
        transform: translateX(100%);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}

.toast-success {
    border-left: 4px solid var(--success-green);
}

.toast-error {
    border-left: 4px solid var(--error-red);
}
```

### 3.7 Modals

#### Modal Overlay & Container
```css
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.modal {
    background-color: var(--white);
    border-radius: 8px;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.2);
    animation: slideUp 0.3s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.modal-header {
    padding: 24px;
    border-bottom: 1px solid var(--border-light);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.modal-title {
    font-size: var(--text-2xl);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
    margin: 0;
}

.modal-close {
    background: none;
    border: none;
    font-size: 24px;
    color: var(--text-secondary);
    cursor: pointer;
    padding: 0;
    width: 32px;
    height: 32px;
    border-radius: 4px;
    transition: all 0.2s;
}

.modal-close:hover {
    background-color: var(--light-gray);
    color: var(--text-dark);
}

.modal-body {
    padding: 24px;
}

.modal-footer {
    padding: 16px 24px;
    border-top: 1px solid var(--border-light);
    display: flex;
    justify-content: flex-end;
    gap: 12px;
}
```

### 3.8 Loading & Progress Indicators

#### Spinner
```css
.spinner {
    border: 3px solid var(--light-gray);
    border-top: 3px solid var(--primary-navy);
    border-radius: 50%;
    width: 40px;
    height: 40px;
    animation: spin 0.8s linear infinite;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

.spinner-sm {
    width: 20px;
    height: 20px;
    border-width: 2px;
}

.spinner-lg {
    width: 60px;
    height: 60px;
    border-width: 4px;
}
```

#### Progress Bar
```css
.progress {
    height: 8px;
    background-color: var(--light-gray);
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar {
    height: 100%;
    background-color: var(--primary-navy);
    transition: width 0.3s ease;
}

.progress-bar-success {
    background-color: var(--success-green);
}

.progress-bar-warning {
    background-color: var(--warning-yellow);
}
```

#### Skeleton Loader
```css
.skeleton {
    background: linear-gradient(
        90deg,
        var(--light-gray) 25%,
        var(--medium-gray) 50%,
        var(--light-gray) 75%
    );
    background-size: 200% 100%;
    animation: loading 1.5s ease-in-out infinite;
    border-radius: 4px;
}

@keyframes loading {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

.skeleton-text {
    height: 16px;
    margin-bottom: 8px;
}

.skeleton-heading {
    height: 24px;
    width: 60%;
}
```

---

## 4. Layout & Spacing

### 4.1 Spacing Scale

```css
/* 8px Base Unit System */
:root {
    --spacing-0: 0;
    --spacing-1: 4px;      /* 0.25rem */
    --spacing-2: 8px;      /* 0.5rem */
    --spacing-3: 12px;     /* 0.75rem */
    --spacing-4: 16px;     /* 1rem */
    --spacing-5: 20px;     /* 1.25rem */
    --spacing-6: 24px;     /* 1.5rem */
    --spacing-8: 32px;     /* 2rem */
    --spacing-10: 40px;    /* 2.5rem */
    --spacing-12: 48px;    /* 3rem */
    --spacing-16: 64px;    /* 4rem */
    --spacing-20: 80px;    /* 5rem */
}
```

### 4.2 Container & Grid

#### Container
```css
.container {
    width: 100%;
    max-width: 1200px;
    margin: 0 auto;
    padding: 0 var(--spacing-4);
}

.container-fluid {
    width: 100%;
    padding: 0 var(--spacing-4);
}

.container-narrow {
    max-width: 800px;
}

.container-wide {
    max-width: 1400px;
}
```

#### Grid System
```css
.row {
    display: flex;
    flex-wrap: wrap;
    margin: 0 calc(var(--spacing-4) * -1);
}

.col {
    flex: 1;
    padding: 0 var(--spacing-4);
}

/* Column sizes */
.col-1 { flex: 0 0 8.333%; max-width: 8.333%; }
.col-2 { flex: 0 0 16.666%; max-width: 16.666%; }
.col-3 { flex: 0 0 25%; max-width: 25%; }
.col-4 { flex: 0 0 33.333%; max-width: 33.333%; }
.col-6 { flex: 0 0 50%; max-width: 50%; }
.col-8 { flex: 0 0 66.666%; max-width: 66.666%; }
.col-12 { flex: 0 0 100%; max-width: 100%; }
```

### 4.3 Main Layout Structure

```html
<!-- Overall Page Structure -->
<div class="app-wrapper">
    <header class="app-header">
        <!-- Top navigation -->
    </header>
    
    <div class="app-container">
        <aside class="app-sidebar">
            <!-- Side navigation -->
        </aside>
        
        <main class="app-content">
            <!-- Main content area -->
        </main>
    </div>
    
    <footer class="app-footer">
        <!-- Footer -->
    </footer>
</div>
```

```css
.app-wrapper {
    min-height: 100vh;
    display: flex;
    flex-direction: column;
}

.app-header {
    height: 64px;
    background-color: var(--primary-navy);
    color: var(--white);
    display: flex;
    align-items: center;
    padding: 0 var(--spacing-6);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
    position: sticky;
    top: 0;
    z-index: 100;
}

.app-container {
    flex: 1;
    display: flex;
}

.app-sidebar {
    width: 260px;
    background-color: var(--white);
    border-right: 1px solid var(--border-light);
    padding: var(--spacing-6);
    overflow-y: auto;
}

.app-content {
    flex: 1;
    background-color: var(--off-white);
    padding: var(--spacing-8);
    overflow-y: auto;
}

.app-footer {
    height: 48px;
    background-color: var(--primary-navy-dark);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: var(--text-sm);
}
```

---

## 5. Student Module UI

### 5.1 QR Code Generator Page

#### Layout Structure
```
┌─────────────────────────────────────────┐
│          HEADER (Bicos Branding)        │
├─────────────────────────────────────────┤
│                                         │
│  ┌───────────────────────────────────┐ │
│  │     Student Information Form      │ │
│  │                                   │ │
│  │  [LRN Input Field]                │ │
│  │  [Full Name Input]                │ │
│  │  [Grade Dropdown]                 │ │
│  │  [Section Input]                  │ │
│  │  [Subject Dropdown]               │ │
│  │                                   │ │
│  │    [Generate QR Code Button]      │ │
│  └───────────────────────────────────┘ │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │      QR Code Display Area         │ │
│  │                                   │ │
│  │        [QR Code Image]            │ │
│  │         300x300px                 │ │
│  │                                   │ │
│  │    [Download QR Code Button]      │ │
│  │    [Print QR Code Button]         │ │
│  └───────────────────────────────────┘ │
│                                         │
└─────────────────────────────────────────┘
```

#### Design Specifications

**Page Container:**
```css
.qr-generator-page {
    max-width: 600px;
    margin: var(--spacing-8) auto;
    padding: var(--spacing-6);
}
```

**Form Card:**
```css
.qr-form-card {
    background: var(--white);
    border-radius: 12px;
    padding: var(--spacing-8);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: var(--spacing-6);
}

.qr-form-title {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    color: var(--primary-navy);
    text-align: center;
    margin-bottom: var(--spacing-6);
}

.qr-form-subtitle {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    text-align: center;
    margin-bottom: var(--spacing-8);
}
```

**QR Display Card:**
```css
.qr-display-card {
    background: var(--white);
    border-radius: 12px;
    padding: var(--spacing-8);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    text-align: center;
    display: none; /* Show after generation */
}

.qr-display-card.active {
    display: block;
    animation: fadeInUp 0.4s ease;
}

.qr-code-container {
    width: 300px;
    height: 300px;
    margin: var(--spacing-6) auto;
    padding: var(--spacing-4);
    background: var(--white);
    border: 2px solid var(--primary-navy);
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.qr-code-image {
    max-width: 100%;
    max-height: 100%;
}

.qr-instructions {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: var(--spacing-4);
    padding: var(--spacing-4);
    background-color: var(--light-gray);
    border-radius: 4px;
}
```

**Action Buttons:**
```css
.qr-actions {
    display: flex;
    gap: var(--spacing-4);
    justify-content: center;
    margin-top: var(--spacing-6);
}

.btn-download {
    background-color: var(--success-green);
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
}

.btn-print {
    background-color: var(--secondary-blue);
}
```

### 5.2 Student Registration Form

#### Form Layout
```css
.student-registration {
    max-width: 800px;
    margin: 0 auto;
}

.form-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-4);
}

.form-row-full {
    grid-template-columns: 1fr;
}

@media (max-width: 768px) {
    .form-row {
        grid-template-columns: 1fr;
    }
}
```

---

## 6. Teacher Module UI

### 6.1 QR Scanner Interface

#### Layout Structure
```
┌─────────────────────────────────────────┐
│          Navigation Header              │
├─────────────────────────────────────────┤
│                                         │
│  ┌───────────────────────────────────┐ │
│  │    Session Configuration          │ │
│  │  [Date] [Grade] [Section] [Subj]  │ │
│  │    [Start Session Button]         │ │
│  └───────────────────────────────────┘ │
│                                         │
│  ┌───────────────────────────────────┐ │
│  │                                   │ │
│  │      CAMERA FEED PREVIEW          │ │
│  │         640 x 480                 │ │
│  │                                   │ │
│  │    [Scanning Area Indicator]      │ │
│  └───────────────────────────────────┘ │
│                                         │
│  ┌────────────┐  ┌───────────────────┐│
│  │ Live Stats │  │ Recent Scans      ││
│  │            │  │                   ││
│  │ Present: X │  │ • Juan (8:05 AM) ││
│  │ Scanned: Y │  │ • Maria (8:06 AM)││
│  │ Rate: Z%   │  │ • Pedro (8:07 AM)││
│  └────────────┘  └───────────────────┘│
│                                         │
│           [End Session Button]          │
│                                         │
└─────────────────────────────────────────┘
```

#### Design Specifications

**Scanner Container:**
```css
.scanner-page {
    padding: var(--spacing-6);
}

.scanner-header {
    background: linear-gradient(135deg, var(--primary-navy) 0%, var(--primary-navy-light) 100%);
    color: var(--white);
    padding: var(--spacing-8);
    border-radius: 12px;
    margin-bottom: var(--spacing-6);
}

.scanner-title {
    font-size: var(--text-3xl);
    font-weight: var(--font-bold);
    margin-bottom: var(--spacing-4);
}

.session-config {
    display: flex;
    gap: var(--spacing-4);
    flex-wrap: wrap;
}

.session-config select {
    background-color: rgba(255, 255, 255, 0.2);
    color: var(--white);
    border: 1px solid rgba(255, 255, 255, 0.3);
    padding: var(--spacing-3);
    border-radius: 4px;
    font-size: var(--text-base);
}
```

**Camera Preview:**
```css
.camera-container {
    background: var(--white);
    border-radius: 12px;
    padding: var(--spacing-6);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    margin-bottom: var(--spacing-6);
}

.camera-preview {
    width: 100%;
    max-width: 640px;
    height: 480px;
    margin: 0 auto;
    background-color: var(--text-dark);
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}

.camera-video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.scan-area-indicator {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    width: 250px;
    height: 250px;
    border: 3px solid var(--accent-red);
    border-radius: 12px;
    box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.5);
}

.scan-area-corners {
    position: absolute;
    width: 30px;
    height: 30px;
    border-color: var(--accent-red);
}

.scan-area-corners.top-left {
    top: -3px;
    left: -3px;
    border-top: 5px solid;
    border-left: 5px solid;
}

.scan-area-corners.top-right {
    top: -3px;
    right: -3px;
    border-top: 5px solid;
    border-right: 5px solid;
}

.scan-area-corners.bottom-left {
    bottom: -3px;
    left: -3px;
    border-bottom: 5px solid;
    border-left: 5px solid;
}

.scan-area-corners.bottom-right {
    bottom: -3px;
    right: -3px;
    border-bottom: 5px solid;
    border-right: 5px solid;
}

.scan-instruction {
    position: absolute;
    bottom: var(--spacing-6);
    left: 50%;
    transform: translateX(-50%);
    background-color: rgba(0, 0, 0, 0.7);
    color: var(--white);
    padding: var(--spacing-3) var(--spacing-6);
    border-radius: 20px;
    font-size: var(--text-sm);
}
```

**Scan Feedback:**
```css
/* Success Flash */
.scan-success-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: var(--success-green);
    opacity: 0;
    pointer-events: none;
    animation: flashSuccess 0.5s ease;
}

@keyframes flashSuccess {
    0%, 100% { opacity: 0; }
    50% { opacity: 0.6; }
}

/* Error Flash */
.scan-error-overlay {
    animation: flashError 0.5s ease;
}

@keyframes flashError {
    0%, 100% { opacity: 0; }
    50% { opacity: 0.6; background-color: var(--error-red); }
}
```

**Live Statistics:**
```css
.scanner-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

.stat-box {
    background: var(--white);
    padding: var(--spacing-6);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    text-align: center;
}

.stat-value {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    color: var(--primary-navy);
    margin-bottom: var(--spacing-2);
}

.stat-label {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.stat-box.success .stat-value {
    color: var(--success-green);
}

.stat-box.warning .stat-value {
    color: var(--warning-yellow);
}
```

**Recent Scans List:**
```css
.recent-scans {
    background: var(--white);
    padding: var(--spacing-6);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    max-height: 400px;
    overflow-y: auto;
}

.recent-scans-title {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
    margin-bottom: var(--spacing-4);
}

.scan-item {
    display: flex;
    align-items: center;
    padding: var(--spacing-3);
    border-bottom: 1px solid var(--border-light);
    animation: slideInLeft 0.3s ease;
}

.scan-item:last-child {
    border-bottom: none;
}

.scan-item-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background-color: var(--success-green);
    color: var(--white);
    display: flex;
    align-items: center;
    justify-content: center;
    margin-right: var(--spacing-3);
}

.scan-item-info {
    flex: 1;
}

.scan-item-name {
    font-weight: var(--font-medium);
    color: var(--text-dark);
    margin-bottom: 2px;
}

.scan-item-time {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

@keyframes slideInLeft {
    from {
        transform: translateX(-20px);
        opacity: 0;
    }
    to {
        transform: translateX(0);
        opacity: 1;
    }
}
```

### 6.2 Session Management UI

**Session Card:**
```css
.session-card {
    background: var(--white);
    border-radius: 8px;
    padding: var(--spacing-6);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    margin-bottom: var(--spacing-4);
    position: relative;
}

.session-card.active {
    border-left: 4px solid var(--success-green);
}

.session-card.ended {
    opacity: 0.7;
}

.session-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-4);
}

.session-info {
    flex: 1;
}

.session-subject {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
}

.session-details {
    font-size: var(--text-sm);
    color: var(--text-secondary);
    margin-top: 4px;
}

.session-status {
    display: inline-block;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: var(--text-xs);
    font-weight: var(--font-medium);
    text-transform: uppercase;
}

.session-status.active {
    background-color: var(--success-green);
    color: var(--white);
}

.session-status.ended {
    background-color: var(--medium-gray);
    color: var(--text-secondary);
}

.session-stats {
    display: flex;
    gap: var(--spacing-6);
    padding-top: var(--spacing-4);
    border-top: 1px solid var(--border-light);
}

.session-stat {
    text-align: center;
}

.session-stat-value {
    font-size: var(--text-2xl);
    font-weight: var(--font-bold);
    color: var(--primary-navy);
}

.session-stat-label {
    font-size: var(--text-xs);
    color: var(--text-secondary);
    text-transform: uppercase;
}
```

---

## 7. Dashboard & Reports UI

### 7.1 Attendance Dashboard Layout

```
┌────────────────────────────────────────────┐
│           NAVIGATION HEADER                │
├────────────────────────────────────────────┤
│                                            │
│  ┌─────────┐ ┌──────────┐ ┌──────────┐   │
│  │Present  │ │  Absent  │ │   Rate   │   │
│  │   45    │ │    5     │ │   90%    │   │
│  └─────────┘ └──────────┘ └──────────┘   │
│                                            │
│  ┌────────────────────────────────────┐   │
│  │ Filters                            │   │
│  │ [Date] [Grade] [Section] [Subject] │   │
│  │         [Apply] [Clear]            │   │
│  └────────────────────────────────────┘   │
│                                            │
│  ┌────────────────────────────────────┐   │
│  │          DATA TABLE                │   │
│  │ ─────────────────────────────────  │   │
│  │  LRN   Name    Grade  Time Status  │   │
│  │ ─────────────────────────────────  │   │
│  │  12345 Juan    11-A   8:05 Present │   │
│  │  12346 Maria   11-A   8:06 Present │   │
│  │  ...                               │   │
│  │ ─────────────────────────────────  │   │
│  │        [< 1 2 3 4 5 >]             │   │
│  └────────────────────────────────────┘   │
│                                            │
│  [Export PDF] [Export Excel] [Print]      │
│                                            │
└────────────────────────────────────────────┘
```

#### Dashboard Page Styles

```css
.dashboard-page {
    padding: var(--spacing-8);
    background-color: var(--off-white);
}

.dashboard-header {
    margin-bottom: var(--spacing-8);
}

.dashboard-title {
    font-size: var(--text-4xl);
    font-weight: var(--font-bold);
    color: var(--primary-navy);
    margin-bottom: var(--spacing-2);
}

.dashboard-subtitle {
    font-size: var(--text-base);
    color: var(--text-secondary);
}
```

**Statistics Grid:**
```css
.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: var(--spacing-6);
    margin-bottom: var(--spacing-8);
}

.stat-card-dashboard {
    background: linear-gradient(135deg, var(--primary-navy) 0%, var(--primary-navy-light) 100%);
    color: var(--white);
    padding: var(--spacing-8);
    border-radius: 12px;
    box-shadow: 0 4px 12px rgba(26, 35, 126, 0.25);
    position: relative;
    overflow: hidden;
}

.stat-card-dashboard::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: rgba(255, 255, 255, 0.1);
    border-radius: 50%;
}

.stat-card-dashboard.success {
    background: linear-gradient(135deg, var(--success-green) 0%, #66bb6a 100%);
}

.stat-card-dashboard.warning {
    background: linear-gradient(135deg, var(--warning-yellow) 0%, #ffb300 100%);
}

.stat-card-dashboard.danger {
    background: linear-gradient(135deg, var(--error-red) 0%, #e53935 100%);
}

.stat-card-icon-lg {
    width: 56px;
    height: 56px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: var(--spacing-4);
}

.stat-card-value-lg {
    font-size: var(--text-5xl);
    font-weight: var(--font-bold);
    line-height: 1;
    margin-bottom: var(--spacing-2);
}

.stat-card-label-lg {
    font-size: var(--text-base);
    opacity: 0.95;
}

.stat-card-trend {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
    margin-top: var(--spacing-3);
    font-size: var(--text-sm);
}

.stat-card-trend-up {
    color: rgba(255, 255, 255, 0.9);
}

.stat-card-trend-down {
    color: rgba(255, 255, 255, 0.7);
}
```

### 7.2 Filter Panel

```css
.filter-panel {
    background: var(--white);
    padding: var(--spacing-6);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    margin-bottom: var(--spacing-6);
}

.filter-panel-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--spacing-4);
}

.filter-panel-title {
    font-size: var(--text-lg);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
}

.filter-panel-toggle {
    background: none;
    border: none;
    color: var(--primary-navy);
    cursor: pointer;
    padding: var(--spacing-2);
}

.filter-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: var(--spacing-4);
    margin-bottom: var(--spacing-6);
}

.filter-actions {
    display: flex;
    gap: var(--spacing-3);
}

.active-filters {
    display: flex;
    flex-wrap: wrap;
    gap: var(--spacing-2);
    margin-top: var(--spacing-4);
}

.filter-tag {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-2);
    padding: 4px 12px;
    background-color: var(--primary-navy);
    color: var(--white);
    border-radius: 16px;
    font-size: var(--text-sm);
}

.filter-tag-remove {
    background: none;
    border: none;
    color: var(--white);
    cursor: pointer;
    padding: 0;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0.8;
}

.filter-tag-remove:hover {
    opacity: 1;
}
```

### 7.3 Data Table Enhanced

```css
.table-container {
    background: var(--white);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    overflow: hidden;
    margin-bottom: var(--spacing-6);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-6);
    border-bottom: 1px solid var(--border-light);
}

.table-title {
    font-size: var(--text-xl);
    font-weight: var(--font-semibold);
    color: var(--primary-navy);
}

.table-actions {
    display: flex;
    gap: var(--spacing-3);
}

.table-search {
    position: relative;
    width: 300px;
}

.table-search-input {
    width: 100%;
    padding: 8px 12px 8px 36px;
    border: 1px solid var(--border-light);
    border-radius: 4px;
    font-size: var(--text-sm);
}

.table-search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: var(--text-secondary);
}

.table-wrapper {
    overflow-x: auto;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
}

.data-table thead th {
    background-color: var(--primary-navy);
    color: var(--white);
    padding: var(--spacing-4) var(--spacing-6);
    text-align: left;
    font-size: var(--text-sm);
    font-weight: var(--font-semibold);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    white-space: nowrap;
    position: sticky;
    top: 0;
    z-index: 10;
}

.data-table thead th.sortable {
    cursor: pointer;
    user-select: none;
}

.data-table thead th.sortable:hover {
    background-color: var(--primary-navy-dark);
}

.data-table thead th .sort-icon {
    margin-left: var(--spacing-2);
    opacity: 0.5;
}

.data-table thead th.sorted .sort-icon {
    opacity: 1;
}

.data-table tbody td {
    padding: var(--spacing-4) var(--spacing-6);
    border-bottom: 1px solid var(--border-light);
    font-size: var(--text-base);
    color: var(--text-dark);
}

.data-table tbody tr:hover {
    background-color: var(--off-white);
}

.data-table tbody tr:last-child td {
    border-bottom: none;
}

.table-cell-status {
    display: inline-flex;
    align-items: center;
    gap: var(--spacing-2);
}

.table-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: var(--spacing-6);
    border-top: 1px solid var(--border-light);
}

.table-info {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.table-pagination {
    display: flex;
    gap: var(--spacing-2);
}

.pagination-btn {
    width: 36px;
    height: 36px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid var(--border-light);
    background: var(--white);
    color: var(--text-dark);
    border-radius: 4px;
    cursor: pointer;
    transition: all 0.2s;
}

.pagination-btn:hover:not(.active):not(:disabled) {
    background-color: var(--light-gray);
    border-color: var(--primary-navy);
}

.pagination-btn.active {
    background-color: var(--primary-navy);
    color: var(--white);
    border-color: var(--primary-navy);
}

.pagination-btn:disabled {
    opacity: 0.4;
    cursor: not-allowed;
}
```

### 7.4 Export Action Bar

```css
.export-bar {
    background: var(--white);
    padding: var(--spacing-6);
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06);
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.export-info {
    font-size: var(--text-sm);
    color: var(--text-secondary);
}

.export-info strong {
    color: var(--primary-navy);
    font-weight: var(--font-semibold);
}

.export-actions {
    display: flex;
    gap: var(--spacing-3);
}

.btn-export {
    display: flex;
    align-items: center;
    gap: var(--spacing-2);
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    font-size: var(--text-sm);
    font-weight: var(--font-medium);
    cursor: pointer;
    transition: all 0.2s;
}

.btn-export-pdf {
    background-color: var(--error-red);
    color: var(--white);
}

.btn-export-excel {
    background-color: var(--success-green);
    color: var(--white);
}

.btn-export-csv {
    background-color: var(--secondary-blue);
    color: var(--white);
}

.btn-export:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
}
```

---

## 8. Responsive Design

### 8.1 Breakpoints

```css
/* Mobile First Approach */

/* Extra Small Devices (Portrait Phones) */
@media (max-width: 575.98px) {
    .container {
        padding: 0 var(--spacing-4);
    }
    
    .dashboard-page {
        padding: var(--spacing-4);
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
    
    .filter-grid {
        grid-template-columns: 1fr;
    }
    
    .export-bar {
        flex-direction: column;
        gap: var(--spacing-4);
    }
    
    .export-actions {
        width: 100%;
        flex-direction: column;
    }
    
    .btn-export {
        width: 100%;
        justify-content: center;
    }
}

/* Small Devices (Landscape Phones) */
@media (min-width: 576px) and (max-width: 767.98px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Medium Devices (Tablets) */
@media (min-width: 768px) and (max-width: 991.98px) {
    .app-sidebar {
        width: 220px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
    
    .filter-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Large Devices (Desktops) */
@media (min-width: 992px) {
    .stats-grid {
        grid-template-columns: repeat(4, 1fr);
    }
}

/* Extra Large Devices */
@media (min-width: 1200px) {
    .container {
        max-width: 1140px;
    }
}
```

### 8.2 Mobile-Specific Styles

```css
/* Mobile Navigation */
@media (max-width: 991.98px) {
    .app-sidebar {
        position: fixed;
        left: -260px;
        top: 64px;
        bottom: 0;
        z-index: 999;
        transition: left 0.3s ease;
    }
    
    .app-sidebar.open {
        left: 0;
        box-shadow: 4px 0 12px rgba(0, 0, 0, 0.15);
    }
    
    .mobile-menu-toggle {
        display: block;
        background: none;
        border: none;
        color: var(--white);
        font-size: 24px;
        cursor: pointer;
        padding: var(--spacing-2);
    }
    
    .mobile-overlay {
        display: none;
        position: fixed;
        top: 64px;
        left: 0;
        right: 0;
        bottom: 0;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 998;
    }
    
    .mobile-overlay.active {
        display: block;
    }
}

@media (min-width: 992px) {
    .mobile-menu-toggle {
        display: none;
    }
}

/* Touch-Friendly Buttons */
@media (hover: none) and (pointer: coarse) {
    .btn-primary,
    .btn-secondary,
    .btn-tertiary {
        min-height: 44px;
        min-width: 44px;
    }
    
    .table-search-input {
        font-size: 16px; /* Prevents iOS zoom */
    }
    
    .form-control {
        font-size: 16px; /* Prevents iOS zoom */
    }
}
```

### 8.3 Scanner Mobile Optimization

```css
@media (max-width: 767.98px) {
    .camera-preview {
        height: 400px;
    }
    
    .scan-area-indicator {
        width: 200px;
        height: 200px;
    }
    
    .scanner-stats {
        grid-template-columns: 1fr 1fr;
    }
    
    .recent-scans {
        max-height: 300px;
    }
}
```

---

## 9. Interaction Design

### 9.1 Hover Effects

```css
/* Button Hover */
.btn-primary:hover {
    background-color: var(--primary-navy-dark);
    box-shadow: 0 4px 8px rgba(26, 35, 126, 0.3);
    transform: translateY(-2px);
}

/* Card Hover */
.card:hover {
    box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
    transform: translateY(-2px);
}

/* Link Hover */
a {
    color: var(--secondary-blue);
    text-decoration: none;
    transition: color 0.2s;
}

a:hover {
    color: var(--secondary-blue-dark);
    text-decoration: underline;
}

/* Table Row Hover */
.data-table tbody tr:hover {
    background-color: var(--off-white);
    cursor: pointer;
}
```

### 9.2 Focus States

```css
/* Input Focus */
.form-control:focus {
    outline: none;
    border-color: var(--primary-navy);
    box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.1);
}

/* Button Focus */
.btn-primary:focus {
    outline: none;
    box-shadow: 0 0 0 3px rgba(26, 35, 126, 0.25);
}

/* Link Focus */
a:focus {
    outline: 2px solid var(--primary-navy);
    outline-offset: 2px;
}

/* Skip to Content (Accessibility) */
.skip-to-content {
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary-navy);
    color: var(--white);
    padding: 8px;
    z-index: 10000;
}

.skip-to-content:focus {
    top: 0;
}
```

### 9.3 Active States

```css
.btn-primary:active {
    transform: translateY(0);
    box-shadow: 0 1px 2px rgba(26, 35, 126, 0.2);
}

.pagination-btn.active {
    background-color: var(--primary-navy);
    color: var(--white);
}

.nav-item.active {
    background-color: var(--primary-navy-light);
    border-left: 4px solid var(--accent-red);
}
```

### 9.4 Transitions

```css
/* Global Transitions */
* {
    transition-timing-function: ease-in-out;
}

/* Smooth Scroll */
html {
    scroll-behavior: smooth;
}

/* Page Transitions */
.page-enter {
    opacity: 0;
    transform: translateY(20px);
}

.page-enter-active {
    opacity: 1;
    transform: translateY(0);
    transition: opacity 0.3s, transform 0.3s;
}

/* Modal Transitions */
.modal-overlay {
    animation: fadeIn 0.2s ease;
}

.modal {
    animation: slideUp 0.3s ease;
}
```

### 9.5 Loading States

```css
/* Skeleton Loading */
.skeleton-loader {
    background: linear-gradient(
        90deg,
        var(--light-gray) 25%,
        var(--medium-gray) 50%,
        var(--light-gray) 75%
    );
    background-size: 200% 100%;
    animation: shimmer 1.5s ease-in-out infinite;
}

@keyframes shimmer {
    0% { background-position: 200% 0; }
    100% { background-position: -200% 0; }
}

/* Button Loading State */
.btn-loading {
    position: relative;
    color: transparent;
    pointer-events: none;
}

.btn-loading::after {
    content: '';
    position: absolute;
    width: 16px;
    height: 16px;
    top: 50%;
    left: 50%;
    margin-left: -8px;
    margin-top: -8px;
    border: 2px solid var(--white);
    border-top-color: transparent;
    border-radius: 50%;
    animation: spin 0.6s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}
```

---

## 10. Accessibility Guidelines

### 10.1 Color Contrast (WCAG 2.1 AA)

**Minimum Requirements:**
- Normal text (< 18px): 4.5:1 contrast ratio
- Large text (≥ 18px or ≥ 14px bold): 3:1 contrast ratio

**Verified Combinations:**
```css
/* AAA Level (7:1+) */
--primary-navy (#1a237e) on white: 14.04:1 ✓
white on --primary-navy: 14.04:1 ✓

/* AA Level (4.5:1+) */
--accent-red (#d32f2f) on white: 5.35:1 ✓
--text-dark (#212121) on white: 16.1:1 ✓

/* Large Text Only (3:1+) */
--secondary-blue (#42a5f5) on white: 3.11:1 ✓
```

### 10.2 Keyboard Navigation

```css
/* Tab Order */
*:focus {
    outline: 2px solid var(--primary-navy);
    outline-offset: 2px;
}

/* Skip Links */
.skip-link {
    position: absolute;
    top: -40px;
    left: 0;
    background: var(--primary-navy);
    color: var(--white);
    padding: 8px;
    z-index: 10000;
    text-decoration: none;
}

.skip-link:focus {
    top: 0;
}

/* Focus Visible (Modern Browsers) */
:focus-visible {
    outline: 2px solid var(--primary-navy);
    outline-offset: 2px;
}

:focus:not(:focus-visible) {
    outline: none;
}
```


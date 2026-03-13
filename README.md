# 🛡️ NorthPoint 360 (SafetyCore)

**NorthPoint 360** is a proprietary, enterprise-grade **Environment,
Health, and Safety (EHS)** management platform. Built for modern
workforces, it seamlessly connects remote field workers with management
to ensure regulatory compliance, track incidents, and foster a proactive
safety culture.

------------------------------------------------------------------------

## ⚠️ PRIVATE REPOSITORY

This codebase is **proprietary and confidential**.\
It is **not licensed for open-source distribution**.

Unauthorized copying, distribution, or modification is **strictly
prohibited**.

------------------------------------------------------------------------

# 🚀 Core Modules

## Hazard Reporting Lifecycle

A closed-loop system for logging, investigating, and resolving workplace
hazards and near misses.

Features include: - Photo and video evidence tracking - Investigation
workflows - Resolution tracking

------------------------------------------------------------------------

## Field Level Hazard Assessments (FLHA)

A robust digital wizard designed for remote job sites.

Features include: - Dynamic job step creation - Situational hazard
identification - PPE checklists - Mandatory end-of-shift close-outs

------------------------------------------------------------------------

## Incident & Accident Management

Secure logging of:

-   Workplace injuries
-   Property damage

Management can officially classify incidents as:

-   **OSHA Recordable**
-   **WCB Recordable**
-   **Lost-Time Incidents**

This enables compliance auditing and reporting.

------------------------------------------------------------------------

## Safety Meetings & Toolbox Talks

Tools for management to:

-   Host safety meetings
-   Document meeting topics
-   Record verified employee attendance dynamically

------------------------------------------------------------------------

## Multi-Tenant Architecture

Scalable hierarchy:

Company └── Branch / Store └── User

Includes strict **Role-Based Access Control (RBAC)** enforcement.

------------------------------------------------------------------------

## Dynamic SEO Engine

Database-driven metadata and **OpenGraph injection** for page routing
and SEO optimization.

------------------------------------------------------------------------

# 🛠️ Tech Stack

  Layer      Technology
  ---------- -----------------------------------------------------
  Backend    PHP 8.x (Vanilla procedural / modular architecture)
  Database   MySQL / MariaDB
  Frontend   HTML5 + Vanilla JavaScript (Fetch API)
  Styling    Tailwind CSS (CDN) + Custom CSS
  Icons      FontAwesome 6

------------------------------------------------------------------------

# 📂 Directory Structure

The application follows a **custom Front Controller pattern**, routing
all traffic through `index.php`.

SafetyCore/ ├── index.php ├── .htaccess ├── schema.sql ├── api/ │ ├──
flha.php │ ├── incidents.php │ ├── hazard_reporting.php │ ├──
meetings.php │ └── job_sites.php ├── includes/ │ ├── config.php │ ├──
db.php │ ├── header.php │ ├── footer.php │ ├── login_process.php │ └──
permissions.php ├── pages/ │ ├── admin-views/ │ ├── dashboard.php │ ├──
flha-form.php │ ├── incident-report.php │ └── ... ├── reports/ │ └──
uploads/ └── style/ ├── css/style.css └── images/logo.png

------------------------------------------------------------------------

# 💻 Local Development Setup

To run **NorthPoint 360 locally**, you need a standard
LAMP/WAMP/MAMP/XAMPP stack.

## 1. Clone the Repository

git clone git@github.com:YourOrg/SafetyCore.git cd SafetyCore

## 2. Database Configuration

Create a database:

northpoint_dev

Import schema:

mysql -u root -p northpoint_dev \< schema.sql

Example configuration:

``` php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'northpoint_dev');
```

## 3. Server Configuration

Ensure the document root points to the `SafetyCore/` directory.

Apache requirements:

-   mod_rewrite enabled
-   `.htaccess` active

Routing occurs through:

index.php?page=...

## 4. Default Login

Check `schema.sql` for seeded credentials or create a new local user.

------------------------------------------------------------------------

# 🔒 Security Guidelines

## Environment Variables

Never commit:

-   Production database credentials
-   API keys
-   Private tokens

## File Upload Security

User uploads stored in:

reports/uploads/

Keep `.htaccess` files intact to prevent script execution.

## SQL Injection Protection

Always use prepared statements.

Example:

``` php
$stmt = $conn->prepare($sql);
$stmt->bind_param(...);
```

## XSS Prevention

Always sanitize output with:

``` php
htmlspecialchars()
```

------------------------------------------------------------------------

# 🗺️ Development Roadmap

## ✅ Current Version (Beta 05)

-   [x] Multi-tenant RBAC engine
-   [x] Full hazard reporting CRUD lifecycle
-   [x] Field Level Hazard Assessments (FLHA) workflow
-   [x] Incident classification dashboard
-   [x] Safety meetings & attendance tracking
-   [x] Dynamic database-driven SEO

## 🔜 Upcoming (V1.0 Release)

-   [ ] Automated Email/SMS notifications for critical incidents
-   [ ] PDF generation & export for external audits
-   [ ] Equipment management & pre-use inspections
-   [ ] Employee training & certification matrix

------------------------------------------------------------------------

Maintained by the **NorthPoint 360 Development Team**

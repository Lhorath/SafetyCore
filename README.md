NorthPoint 360

Version: 4.0.0 (NorthPoint Beta 01)

Status: Live Beta / Testing

Framework: PHP / MySQL / Tailwind CSS

🎯 Project Overview

NorthPoint 360 (formerly Safety Hub) is a comprehensive, cloud-based Environmental, Health, and Safety (EHS) management platform. It is designed to bridge the gap between frontline hazard identification and high-level management oversight, replacing fragmented paper trails with a unified digital ecosystem.

Built on a Multi-Tenant SaaS Architecture, the platform supports complex organizational hierarchies, allowing multiple companies to operate securely within their own isolated workspaces while maintaining centralized administration.

🏢 Organizational Hierarchy

The system is designed around a hierarchical structure to support organizations of any size:

Company: The top-level tenant (e.g., "Elmwood Group").

Logic: Users authenticate into a specific Company Workspace.

Branch (Store): A physical or logical division (e.g., "Downey's Home Hardware").

Logic: Users can be assigned to multiple branches simultaneously.

Location: Specific zones within a branch where hazards occur (e.g., "Lumber Yard", "Paint Aisle").

Logic: Managed dynamically per store; users can add new locations on the fly.

👥 User Roles & Permissions

NorthPoint 360 utilizes a strictly enforced Role-Based Access Control (RBAC) system split into two levels.

1. Company Level Roles (Upper Management)

Owner / CEO: Full system access, including company structure management.

Safety Manager: Oversees compliance and reports across all branches.

Training Manager: (Roadmap) Manages training modules and certifications.

2. Branch Level Roles

Manager / Co-manager: Oversees daily operations and reports for their specific branch(es).

Safety Leader: Can manage branch-specific safety settings and reviews.

JHSC Member: Joint Health and Safety Committee member (Audit & Review capabilities).

Equipment Operator: (Roadmap) Specific access for pre-op inspections.

Full Time / Part Time Employee: Standard access to submit reports and view personal history.

🚀 Key Features

🔐 Authentication & Security

Multi-Tenant Login: Secure entry requiring users to select their Company Workspace.

Session Security: PHP Session-based state management with rigorous role verification.

Password Hashing: Industry-standard Bcrypt hashing for all user credentials.

📝 Hazard Reporting

Dynamic Intake Form: Context-aware form that populates employees and locations based on the selected store.

Rich Media Support: Upload up to 5 photos (2MB limit) and 2 videos (200MB limit) per report.

Conditional Logic: Smart fields that adapt based on user input (e.g., "Locked Out" status triggers Key Holder prompt).

📊 Management Dashboards

Personal History: Employees can track the status of their own submissions via "My Reports".

Store Dashboard: Managers have a real-time view of their branch's safety health.

Live Stats: Counters for reports this month and risk level distribution.

Filtering: Client-side filtering by Date Range and Risk Severity.

Master-Detail View: Inspect full report details and media without leaving the dashboard.

⚙️ Administration

User Management: Create, edit, and manage user accounts and roles.

Store Management: Add new branches to the company structure.

Scalable Architecture: "View-based" admin router allows for easy addition of future modules.

🛠 Technical Architecture

Frontend

Framework: Tailwind CSS (via CDN) for utility-first styling.

Design System: Custom "Blues and Blacks" palette:

Primary: Deep Safety Blue (#0f172a)

Secondary: Royal Blue (#3b82f6)

Accent: Safety Red (#ef4444)

JavaScript: Modular, vanilla JavaScript using Event Delegation and Fetch API for conflict-free dynamic interactions.

Backend

Language: PHP 7.4+ / 8.0+.

Database: MySQL (Relational).

API: Consolidated endpoint (api/hazard_reporting.php) handling all AJAX requests via a switch-case router.

Database Schema (Key Tables)

companies (Tenants)

users & user_stores (Many-to-Many assignment)

roles & permissions (RBAC)

stores & hazard_locations (Physical locations)

reports & report_files (Transactional data)

🔮 Future Roadmap

The NorthPoint 360 ecosystem is actively expanding. Upcoming modules include:

Equipment Inspections: Digital pre-operation checklists for forklifts and machinery.

Training Records: Centralized tracking of employee certifications and expiry alerts.

Incident Management: Comprehensive workflows for investigating workplace accidents.

Audits: Scheduled safety audits with digital scoring and signatures.

Compliance Dashboard: High-level regulatory status views for Company management.
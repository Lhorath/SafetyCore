# User Profile Rollout Plan

This rollout is designed to ship safely in phases.

## Phase 1 (Completed)

- Include `employee_position` in login session payload.
- Fix profile location rendering for both `multi_location` and `job_based` companies.
- Remove cross-tenant bypass conditions using `u.is_platform_admin = 1` in:
  - `api/training.php`
  - `pages/metrics.php`
- Enforce minimum password length (8 chars) in:
  - `pages/admin.php` (add-user flow)
  - `pages/admin-edit-user.php` (reset flow)
- Restrict `pages/admin.php` to true platform admins (`is_platform_admin()`).

## Phase 2 (Schema + Application Wiring) (Completed in code)

Apply in maintenance window:

```sql
ALTER TABLE users
  ADD COLUMN employee_code VARCHAR(50) NULL AFTER employee_position,
  ADD COLUMN status ENUM('active','inactive','suspended','terminated') NOT NULL DEFAULT 'active' AFTER employee_code,
  ADD COLUMN employment_type ENUM('full_time','part_time','contractor','temporary') NULL AFTER status,
  ADD COLUMN department VARCHAR(100) NULL AFTER employment_type,
  ADD COLUMN phone_number VARCHAR(30) NULL AFTER department,
  ADD COLUMN supervisor_user_id INT(10) UNSIGNED NULL AFTER phone_number,
  ADD COLUMN hire_date DATE NULL AFTER supervisor_user_id,
  ADD COLUMN last_login_at DATETIME NULL AFTER hire_date,
  ADD COLUMN password_changed_at DATETIME NULL AFTER last_login_at,
  ADD COLUMN mfa_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER password_changed_at,
  ADD COLUMN preferred_language VARCHAR(10) NULL AFTER mfa_enabled,
  ADD COLUMN timezone VARCHAR(50) NULL AFTER preferred_language,
  ADD KEY idx_users_status (status),
  ADD KEY idx_users_department (department),
  ADD KEY idx_users_supervisor (supervisor_user_id),
  ADD CONSTRAINT fk_users_supervisor
    FOREIGN KEY (supervisor_user_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;
```

Create emergency contacts table:

```sql
CREATE TABLE IF NOT EXISTS user_emergency_contacts (
  id INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id INT(10) UNSIGNED NOT NULL,
  contact_name VARCHAR(120) NOT NULL,
  relationship VARCHAR(80) NULL,
  phone_number VARCHAR(30) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_uec_user_id (user_id),
  CONSTRAINT fk_uec_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Implemented in app code:

- Added `includes/user_profile_fields.php` for centralized validation and enum constants.
- Wired new fields into:
  - platform add/edit user flows (`pages/admin.php`, `pages/admin-edit-user.php`, `pages/admin-views/add-user.php`)
  - company add/edit user flows (`pages/company-admin.php`)
  - profile display/edit (`pages/profile.php`, `pages/profile-edit.php`)
  - login session payload (`includes/login_process.php`)
- Added security behavior:
  - block non-active users from logging in
  - stamp `users.last_login_at` on successful login
  - stamp `password_changed_at` whenever password is changed by admins or end users

## Phase 3 (Backend Contracts)

- Extend login/session payload with:
  - `status`, `employment_type`, `department`, `employee_code`
- Block login when `status != 'active'`.
- Add profile API/server validations:
  - `phone_number` format (basic E.164-compatible pattern)
  - `employment_type` enum validation
  - `timezone` whitelist from known TZ list
- Add supervisor ownership checks (same company only).

## Phase 4 (UI/UX)

- Profile page/edit:
  - Display and edit contact/employment metadata.
- Company admin add/edit user:
  - Add `status`, `employment_type`, `department`, `phone_number`, `supervisor`.
- Reporting screens:
  - Add filters for `department`, `employment_type`, `status`.

## Phase 5 (Audit + Analytics)

- Add `user_audit_events` for profile changes and privileged actions.
- Add metrics cards:
  - inactive users
  - contractor incident rate
  - overdue training by department


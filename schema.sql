-- --------------------------------------------------------
-- Host:                         127.0.0.1
-- Server version:               10.4.32-MariaDB - mariadb.org binary distribution
-- Server OS:                    Win64
-- HeidiSQL Version:             12.16.0.7229
-- --------------------------------------------------------

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET NAMES utf8 */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;


-- Dumping database structure for u971098166_safetysite
CREATE DATABASE IF NOT EXISTS `u971098166_safetysite` /*!40100 DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci */;
USE `u971098166_safetysite`;

-- Dumping structure for table u971098166_safetysite.companies
CREATE TABLE IF NOT EXISTS `companies` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_name` varchar(255) NOT NULL,
  `company_type` enum('multi_location','job_based') NOT NULL DEFAULT 'multi_location',
  `is_system` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = NorthPoint 360 system/platform company',
  `industry` varchar(100) DEFAULT NULL,
  `contact_email` varchar(255) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `company_code` char(4) NOT NULL COMMENT '4-digit numeric code used at login. Unique across all tenants.',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_company_code` (`company_code`),
  KEY `idx_company_type` (`company_type`),
  KEY `idx_is_system` (`is_system`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.companies: ~3 rows (approximately)
REPLACE INTO `companies` (`id`, `company_name`, `company_type`, `is_system`, `industry`, `contact_email`, `is_active`, `created_at`, `updated_at`, `company_code`) VALUES
	(1, 'MacWeb Canada', 'multi_location', 0, NULL, NULL, 1, '2026-02-18 13:00:12', '2026-03-12 12:15:02', '0001'),
	(2, 'Elmwood Group', 'multi_location', 0, 'Hardware Retail', NULL, 1, '2026-03-12 12:08:37', '2026-03-12 12:15:02', '0002'),
	(3, 'Ridgeline Construction Inc.', 'job_based', 0, 'General Contracting', NULL, 1, '2026-03-12 12:08:37', '2026-03-12 12:15:02', '0003');

-- Dumping structure for table u971098166_safetysite.contact_messages
CREATE TABLE IF NOT EXISTS `contact_messages` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `contact_messages_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.contact_messages: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.flha_checklists
CREATE TABLE IF NOT EXISTS `flha_checklists` (
  `flha_id` int(10) unsigned NOT NULL,
  `type` enum('hazard','ppe') NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `item_name` varchar(255) NOT NULL,
  KEY `flha_id` (`flha_id`),
  CONSTRAINT `flha_checklists_ibfk_1` FOREIGN KEY (`flha_id`) REFERENCES `flha_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.flha_checklists: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.flha_records
CREATE TABLE IF NOT EXISTS `flha_records` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `creator_user_id` int(10) unsigned NOT NULL,
  `status` enum('Open','Closed') NOT NULL DEFAULT 'Open',
  `work_to_be_done` text NOT NULL,
  `task_location` varchar(255) NOT NULL,
  `emergency_location` varchar(255) NOT NULL,
  `permit_number` varchar(100) DEFAULT NULL,
  `warning_ribbon_required` tinyint(1) NOT NULL DEFAULT 0,
  `working_alone` tinyint(1) NOT NULL DEFAULT 0,
  `working_alone_desc` text DEFAULT NULL,
  `employer_supplied_ppe` tinyint(1) NOT NULL DEFAULT 0,
  `close_permits_closed` tinyint(1) DEFAULT NULL,
  `close_area_cleaned` tinyint(1) DEFAULT NULL,
  `close_hazards_remain` tinyint(1) DEFAULT NULL,
  `close_hazards_desc` text DEFAULT NULL,
  `close_incidents` tinyint(1) DEFAULT NULL,
  `close_incidents_desc` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `closed_at` timestamp NULL DEFAULT NULL,
  `job_site_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `creator_user_id` (`creator_user_id`),
  CONSTRAINT `flha_records_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flha_records_ibfk_2` FOREIGN KEY (`creator_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.flha_records: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.flha_tasks
CREATE TABLE IF NOT EXISTS `flha_tasks` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `flha_id` int(10) unsigned NOT NULL,
  `task_description` text NOT NULL,
  `associated_hazards` text NOT NULL,
  `mitigation_plan` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `flha_id` (`flha_id`),
  CONSTRAINT `flha_tasks_ibfk_1` FOREIGN KEY (`flha_id`) REFERENCES `flha_records` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.flha_tasks: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.flha_workers
CREATE TABLE IF NOT EXISTS `flha_workers` (
  `flha_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`flha_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `flha_workers_ibfk_1` FOREIGN KEY (`flha_id`) REFERENCES `flha_records` (`id`) ON DELETE CASCADE,
  CONSTRAINT `flha_workers_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.flha_workers: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.hazard_locations
CREATE TABLE IF NOT EXISTS `hazard_locations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `store_id` int(10) unsigned NOT NULL,
  `location_name` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_location_per_store` (`store_id`,`location_name`),
  CONSTRAINT `hazard_locations_ibfk_1` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.hazard_locations: ~2 rows (approximately)
REPLACE INTO `hazard_locations` (`id`, `store_id`, `location_name`, `is_active`, `created_at`) VALUES
	(1, 1, 'Test Location', 1, '2026-02-18 13:07:28'),
	(2, 1, 'test 2', 1, '2026-02-18 13:56:27');

-- Dumping structure for table u971098166_safetysite.incidents
CREATE TABLE IF NOT EXISTS `incidents` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL for job-based companies; use job_site_id instead',
  `reporter_user_id` int(10) unsigned NOT NULL,
  `incident_type` enum('Employee Injury','Customer Injury','Property Damage','Near Miss','Other') NOT NULL,
  `incident_date` datetime NOT NULL,
  `location_details` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `immediate_actions` text NOT NULL,
  `status` enum('Open','Under Review','Closed') NOT NULL DEFAULT 'Open',
  `is_recordable` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if recordable for regulatory compliance',
  `is_lost_time` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if incident resulted in lost time',
  `manager_review_notes` text DEFAULT NULL,
  `reviewed_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `job_site_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `store_id` (`store_id`),
  KEY `reporter_user_id` (`reporter_user_id`),
  KEY `reviewed_by_user_id` (`reviewed_by_user_id`),
  CONSTRAINT `incidents_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidents_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `incidents_ibfk_3` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `incidents_ibfk_4` FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.incidents: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.job_sites
CREATE TABLE IF NOT EXISTS `job_sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `job_number` varchar(50) NOT NULL COMMENT 'Internal job/project number (e.g. J-2026-001)',
  `job_name` varchar(255) NOT NULL COMMENT 'Human-readable project name',
  `client_name` varchar(255) DEFAULT NULL COMMENT 'Client or owner of the work',
  `site_address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province_state` varchar(100) DEFAULT NULL,
  `status` enum('Planning','Active','On Hold','Completed','Cancelled') NOT NULL DEFAULT 'Active',
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL COMMENT 'Estimated or actual completion date',
  `supervisor_user_id` int(10) unsigned DEFAULT NULL COMMENT 'FK to users.id — site supervisor',
  `created_by_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_job_number_per_company` (`company_id`,`job_number`),
  KEY `idx_company_id` (`company_id`),
  KEY `idx_status` (`status`),
  KEY `idx_supervisor` (`supervisor_user_id`),
  KEY `fk_job_sites_creator` (`created_by_user_id`),
  CONSTRAINT `fk_job_sites_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_job_sites_creator` FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE,
  CONSTRAINT `fk_job_sites_supervisor` FOREIGN KEY (`supervisor_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Temporary/project job sites for Job-Based companies';

-- Dumping data for table u971098166_safetysite.job_sites: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.login_attempts
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `identifier` varchar(255) NOT NULL COMMENT 'SHA-256 hash of lowercase(company_code + email) — no PII stored',
  `attempted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_identifier` (`identifier`),
  KEY `idx_ip` (`ip_address`),
  KEY `idx_attempted_at` (`attempted_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='DB-backed failed login tracking for rate limiting';

-- Dumping data for table u971098166_safetysite.login_attempts: ~1 rows (approximately)
REPLACE INTO `login_attempts` (`id`, `ip_address`, `identifier`, `attempted_at`) VALUES
	(1, '207.112.31.154', 'ad8c02f43fefbad1dd631f0d58a23346325975f5624d8e7d694f732cf93d75bf', '2026-03-12 23:39:26');

-- Dumping structure for table u971098166_safetysite.meeting_attendees
CREATE TABLE IF NOT EXISTS `meeting_attendees` (
  `meeting_id` int(10) unsigned NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`meeting_id`,`user_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `meeting_attendees_ibfk_1` FOREIGN KEY (`meeting_id`) REFERENCES `meetings` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meeting_attendees_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.meeting_attendees: ~15 rows (approximately)
REPLACE INTO `meeting_attendees` (`meeting_id`, `user_id`) VALUES
	(1, 1),
	(1, 2),
	(1, 3),
	(1, 4),
	(1, 5),
	(1, 6),
	(1, 7),
	(1, 8),
	(1, 9),
	(1, 10),
	(1, 11),
	(1, 12),
	(1, 13),
	(1, 14),
	(1, 15);

-- Dumping structure for table u971098166_safetysite.meetings
CREATE TABLE IF NOT EXISTS `meetings` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned DEFAULT NULL COMMENT 'NULL for job-based companies; use job_site_id instead',
  `host_user_id` int(10) unsigned NOT NULL,
  `topic` varchar(255) NOT NULL,
  `meeting_date` date NOT NULL,
  `comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `job_site_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `company_id` (`company_id`),
  KEY `store_id` (`store_id`),
  KEY `host_user_id` (`host_user_id`),
  CONSTRAINT `meetings_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meetings_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `meetings_ibfk_3` FOREIGN KEY (`host_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.meetings: ~1 rows (approximately)
REPLACE INTO `meetings` (`id`, `company_id`, `store_id`, `host_user_id`, `topic`, `meeting_date`, `comments`, `created_at`, `job_site_id`) VALUES
	(1, 1, 1, 1, 'TEST', '2026-02-24', 'TEST', '2026-02-24 20:48:29', NULL);

-- Dumping structure for table u971098166_safetysite.modules
CREATE TABLE IF NOT EXISTS `modules` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `module_key` varchar(50) NOT NULL,
  `module_name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `icon_class` varchar(80) NOT NULL DEFAULT 'fa-circle',
  `icon_bg` varchar(30) NOT NULL DEFAULT 'bg-gray-100',
  `icon_color` varchar(30) NOT NULL DEFAULT 'text-gray-600',
  `btn_class` varchar(80) NOT NULL DEFAULT 'btn-primary',
  `btn_label` varchar(50) NOT NULL DEFAULT 'Open',
  `route` varchar(100) NOT NULL,
  `area` enum('employee','company_admin','platform_admin') NOT NULL DEFAULT 'employee',
  `sort_order` tinyint(3) unsigned NOT NULL DEFAULT 99,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_module_key` (`module_key`),
  KEY `idx_area` (`area`),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Master catalogue of dashboard modules';

-- Dumping data for table u971098166_safetysite.modules: ~12 rows (approximately)
REPLACE INTO `modules` (`id`, `module_key`, `module_name`, `description`, `icon_class`, `icon_bg`, `icon_color`, `btn_class`, `btn_label`, `route`, `area`, `sort_order`, `is_active`) VALUES
	(1, 'hazard_report', 'Report a Hazard', 'Log a proactive safety concern, near miss, or hazard.', 'fa-exclamation-triangle', 'bg-orange-50', 'text-orange-500', 'bg-orange-500 text-white hover:bg-orange-600', 'Submit Hazard', '/hazard-report', 'employee', 1, 1),
	(2, 'incident_report', 'Report an Incident', 'Log injuries, accidents, and property damage.', 'fa-ambulance', 'bg-red-50', 'text-accent-red', 'btn-accent', 'Submit Incident', '/incident-report', 'employee', 2, 1),
	(3, 'daily_flha', 'Daily FLHA', 'Complete your Field Level Hazard Assessment.', 'fa-clipboard-check', 'bg-green-50', 'text-green-600', 'bg-green-500 text-white hover:bg-green-600', 'Open FLHA Hub', '/flha-list', 'employee', 3, 1),
	(4, 'my_history', 'My History', 'Review safety reports you submitted.', 'fa-folder-open', 'bg-blue-50', 'text-secondary', 'btn-primary', 'View My Reports', '/my-reports', 'employee', 4, 1),
	(5, 'my_profile', 'My Profile', 'Manage personal information and password.', 'fa-user-cog', 'bg-gray-100', 'text-gray-600', 'btn-secondary', 'Manage Account', '/profile', 'employee', 5, 1),
	(6, 'manage_incidents', 'Manage Incidents', 'Review and classify incidents.', 'fa-file-medical-alt', 'bg-red-100', 'text-red-800', 'bg-red-800 text-white hover:bg-red-900', 'Incident Dashboard', '/store-incidents', 'company_admin', 10, 1),
	(7, 'location_hazards', 'Location Hazards', 'Review hazard reports for locations.', 'fa-store', 'bg-blue-50', 'text-primary', 'btn-dark', 'Open Dashboard', '/store-reports', 'company_admin', 11, 1),
	(8, 'meetings_talks', 'Meetings & Talks', 'Host safety meetings and toolbox talks.', 'fa-users', 'bg-blue-50', 'text-secondary', 'bg-blue-100 text-secondary hover:bg-blue-200 font-bold', 'Open Hub', '/meetings-list', 'company_admin', 12, 1),
	(9, 'metrics_stats', 'Metrics & Stats', 'Analyse safety metrics.', 'fa-chart-pie', 'bg-indigo-50', 'text-indigo-600', 'bg-indigo-100 text-indigo-700 hover:bg-indigo-200', 'View Analytics', '/metrics', 'company_admin', 13, 1),
	(10, 'company_users', 'Manage Users', 'Create and manage employee accounts.', 'fa-users-cog', 'bg-purple-50', 'text-purple-700', 'bg-purple-600 text-white hover:bg-purple-700', 'Manage Users', '/company-admin?view=users', 'company_admin', 14, 1),
	(11, 'company_structure', 'Company Structure', 'Configure branches and job sites.', 'fa-building', 'bg-teal-50', 'text-teal-700', 'bg-teal-600 text-white hover:bg-teal-700', 'Configure', '/company-admin?view=structure', 'company_admin', 15, 1),
	(12, 'platform_admin', 'Platform Admin Panel', 'System-level administration.', 'fa-cogs', 'bg-slate-800', 'text-white', 'bg-primary text-white hover:bg-slate-900', 'System Administration', '/admin', 'platform_admin', 20, 1);

-- Dumping structure for table u971098166_safetysite.page_seo
CREATE TABLE IF NOT EXISTS `page_seo` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `page_route` varchar(100) NOT NULL COMMENT 'Matches the ?page= parameter',
  `meta_title` varchar(255) NOT NULL,
  `meta_description` text DEFAULT NULL,
  `meta_keywords` text DEFAULT NULL,
  `og_image` varchar(255) DEFAULT '/style/images/logo.png',
  `requires_login` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 if behind authentication',
  PRIMARY KEY (`id`),
  UNIQUE KEY `page_route` (`page_route`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Dumping data for table u971098166_safetysite.page_seo: ~18 rows (approximately)
REPLACE INTO `page_seo` (`id`, `page_route`, `meta_title`, `meta_description`, `meta_keywords`, `og_image`, `requires_login`) VALUES
	(1, 'home', 'Welcome', 'NorthPoint 360 is your central command for workplace safety compliance, hazard reporting, and operational excellence.', 'EHS, safety compliance, hazard reporting, NorthPoint 360', '/style/images/logo.png', 0),
	(2, 'services', 'Solutions', 'Discover our comprehensive suite of EHS management solutions tailored for modern businesses.', 'EHS solutions, safety software, incident management', '/style/images/logo.png', 0),
	(3, 'contact', 'Contact Support', 'Get in touch with NorthPoint 360 support for assistance with your EHS platform.', 'contact, support, NorthPoint 360', '/style/images/logo.png', 0),
	(4, 'login', 'Secure Login', 'Log in to your NorthPoint 360 portal to manage your workplace safety and reports.', 'login, secure portal, EHS software', '/style/images/logo.png', 0),
	(5, 'dashboard', 'Dashboard', 'Centralized safety dashboard providing real-time insights and quick actions.', 'dashboard, safety metrics, portal', '/style/images/logo.png', 1),
	(6, 'hazard-report', 'Report a Hazard', 'Submit a new workplace hazard, near miss, or safety observation.', 'hazard reporting, safety form', '/style/images/logo.png', 1),
	(7, 'my-reports', 'My History', 'Review and track the status of safety reports you have submitted.', 'my reports, history, safety tracking', '/style/images/logo.png', 1),
	(8, 'store-reports', 'Store Hazard Reports', 'Manage, investigate, and close hazard reports for your specific branch.', 'store reports, hazard management, branch safety', '/style/images/logo.png', 1),
	(9, 'incident-report', 'Report Incident', 'Log workplace injuries, property damage, and severe incidents.', 'incident reporting, injury log, OSHA', '/style/images/logo.png', 1),
	(10, 'store-incidents', 'Incident Management', 'Review, classify, and track recordable incidents and lost-time data.', 'incident management, OSHA compliance, lost time', '/style/images/logo.png', 1),
	(11, 'flha-list', 'FLHA Dashboard', 'Manage your daily Field Level Hazard Assessments (FLHA) for remote job sites.', 'FLHA, field hazard assessment, remote safety', '/style/images/logo.png', 1),
	(12, 'flha-form', 'New Field Assessment', 'Complete your mandatory pre-shift Field Level Hazard Assessment.', 'FLHA form, hazard assessment wizard', '/style/images/logo.png', 1),
	(13, 'flha-close', 'Close FLHA Record', 'Finalize your Field Level Hazard Assessment and log end-of-shift compliance conditions.', 'FLHA close out, safety compliance', '/style/images/logo.png', 1),
	(14, 'metrics', 'Statistics & Metrics', 'Deep dive into hazard trends, resolution times, and risk analytics.', 'safety metrics, EHS analytics, KPI', '/style/images/logo.png', 1),
	(15, 'meetings-list', 'Meetings & Talks', 'Review safety meetings, toolbox talks, and attendance records.', 'safety meetings, toolbox talks, attendance', '/style/images/logo.png', 1),
	(16, 'host-meeting', 'Host a Meeting', 'Log a new safety meeting, define topics, and track employee attendance.', 'host meeting, safety talk form', '/style/images/logo.png', 1),
	(17, 'company-admin', 'Company Administration', 'Manage users, roles, and location structure for your organisation.', 'company admin, user management, job sites, branches, NorthPoint 360', '/style/images/logo.png', 1),
	(18, 'admin-edit-user', 'Edit User', 'Modify an existing user account including role and location assignment.', 'edit user, role assignment, platform admin, NorthPoint 360', '/style/images/logo.png', 1);

-- Dumping structure for table u971098166_safetysite.permissions
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `permission_name` varchar(100) NOT NULL,
  `permission_description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `permission_name` (`permission_name`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.permissions: ~8 rows (approximately)
REPLACE INTO `permissions` (`id`, `permission_name`, `permission_description`) VALUES
	(1, 'create_user', 'Allows user to create new user accounts.'),
	(2, 'edit_user', 'Allows user to edit existing user accounts.'),
	(3, 'view_reports', 'Allows user to view all submitted reports.'),
	(4, 'confirm_reports', 'Allows user to mark reports as confirmed or reviewed.'),
	(5, 'manage_training', 'Allows creation and modification of training records.'),
	(6, 'manage_equipment', 'Allows adding, removing, or flagging equipment.'),
	(7, 'delete_user', 'Allows permanent deletion of user accounts.'),
	(8, 'manage_stores', 'Allows creation and modification of store branches.');

-- Dumping structure for table u971098166_safetysite.report_files
CREATE TABLE IF NOT EXISTS `report_files` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `report_id` int(10) unsigned NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_type` enum('photo','video') NOT NULL,
  `file_size` int(10) unsigned NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `report_id` (`report_id`),
  CONSTRAINT `report_files_ibfk_1` FOREIGN KEY (`report_id`) REFERENCES `reports` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.report_files: ~3 rows (approximately)
REPLACE INTO `report_files` (`id`, `report_id`, `file_path`, `file_type`, `file_size`, `uploaded_at`) VALUES
	(1, 2, 'reports/uploads/photos/photo_6995bbf50fdce2.98553343.jpg', 'photo', 362177, '2026-02-18 13:17:41'),
	(2, 1, 'reports/uploads/photos/photo_6995bbf50fdce2.98553343.jpg', 'photo', 362177, '2026-02-18 13:31:35'),
	(3, 5, 'reports/uploads/photos/photo_699e104ed4d642.45334214.jpeg', 'photo', 59779, '2026-02-24 20:55:42');

-- Dumping structure for table u971098166_safetysite.reports
CREATE TABLE IF NOT EXISTS `reports` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `reporter_user_id` int(10) unsigned NOT NULL COMMENT 'User who submitted the report',
  `store_id` int(10) unsigned NOT NULL COMMENT 'Store context of the report',
  `status` enum('Open','Under Review','Closed') NOT NULL DEFAULT 'Open',
  `report_date` datetime NOT NULL,
  `hazard_location_id` int(10) unsigned NOT NULL,
  `risk_level` tinyint(3) unsigned NOT NULL COMMENT '1=Low, 2=Med, 3=High/Near Miss',
  `hazard_observed_at` datetime NOT NULL,
  `hazard_type` varchar(100) NOT NULL,
  `hazard_description` text NOT NULL,
  `potential_consequences` text DEFAULT NULL,
  `action_taken` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=No, 1=Yes',
  `action_description` text NOT NULL,
  `equipment_locked_out` tinyint(1) NOT NULL DEFAULT 0,
  `lockout_key_holder` varchar(255) DEFAULT NULL,
  `notified_user_id` int(10) unsigned DEFAULT NULL COMMENT 'Supervisor notified',
  `additional_comments` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `job_site_id` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `reporter_user_id` (`reporter_user_id`),
  KEY `store_id` (`store_id`),
  KEY `hazard_location_id` (`hazard_location_id`),
  KEY `notified_user_id` (`notified_user_id`),
  CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_user_id`) REFERENCES `users` (`id`),
  CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`hazard_location_id`) REFERENCES `hazard_locations` (`id`),
  CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`notified_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.reports: ~5 rows (approximately)
REPLACE INTO `reports` (`id`, `reporter_user_id`, `store_id`, `status`, `report_date`, `hazard_location_id`, `risk_level`, `hazard_observed_at`, `hazard_type`, `hazard_description`, `potential_consequences`, `action_taken`, `action_description`, `equipment_locked_out`, `lockout_key_holder`, `notified_user_id`, `additional_comments`, `created_at`, `job_site_id`) VALUES
	(1, 1, 1, 'Closed', '2026-02-18 09:07:00', 1, 1, '0000-00-00 00:00:00', 'physical', '23', '2', 1, 'Fixed', 0, NULL, 1, '=== HAZARD RESOLVED ON February 24, 2026 at 8:07 PM BY WEB  ===\nde', '2026-02-18 13:08:04', NULL),
	(2, 2, 1, 'Closed', '2026-02-18 09:15:00', 1, 1, '0000-00-00 00:00:00', 'physical', 'Crap left all over the floor, blocking exits', 'Trip and break my neck and die', 1, 'Cleaned it all up', 0, NULL, 1, 'This is good now\n\n=== HAZARD RESOLVED ON February 24, 2026 at 6:32 PM BY WEB  ===\nGood', '2026-02-18 13:17:41', NULL),
	(3, 1, 1, 'Closed', '2026-02-18 09:56:00', 2, 1, '0000-00-00 00:00:00', 'physical', 'ewew', 'aweawe', 1, 'weawe', 1, 'Dan', 3, 'FIXED\n\n=== HAZARD RESOLVED ON February 24, 2026 at 6:32 PM BY WEB  ===\nGood', '2026-02-18 13:56:59', NULL),
	(4, 2, 1, 'Closed', '2026-02-19 17:53:00', 2, 3, '0000-00-00 00:00:00', 'electrical', 'Gfff', 'Gfgh', 1, 'Fgf', 1, 'Jin', 4, '=== HAZARD RESOLVED ON February 24, 2026 at 6:32 PM BY WEB  ===\nGood', '2026-02-19 21:55:48', NULL),
	(5, 15, 1, 'Open', '2026-02-24 16:52:00', 1, 2, '0000-00-00 00:00:00', 'psychological', 'People keep telling me I pooped my pants but I didn’t :(((', 'I’ll actually do it', 0, 'I scared :(((', 0, NULL, 10, '!:(!', '2026-02-24 20:55:42', NULL);

-- Dumping structure for table u971098166_safetysite.role_module_permissions
CREATE TABLE IF NOT EXISTS `role_module_permissions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `module_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_role_module` (`role_id`,`module_id`),
  KEY `idx_role_id` (`role_id`),
  KEY `idx_module_id` (`module_id`),
  CONSTRAINT `fk_rmp_module` FOREIGN KEY (`module_id`) REFERENCES `modules` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_rmp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=185 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Role based access to dashboard modules';

-- Dumping data for table u971098166_safetysite.role_module_permissions: ~134 rows (approximately)
REPLACE INTO `role_module_permissions` (`id`, `role_id`, `module_id`) VALUES
	(1, 1, 1),
	(2, 1, 2),
	(3, 1, 3),
	(4, 1, 4),
	(5, 1, 5),
	(6, 2, 1),
	(7, 2, 2),
	(8, 2, 3),
	(9, 2, 4),
	(10, 2, 5),
	(11, 3, 1),
	(12, 3, 2),
	(13, 3, 3),
	(14, 3, 4),
	(15, 3, 5),
	(16, 4, 1),
	(17, 4, 2),
	(18, 4, 3),
	(19, 4, 4),
	(20, 4, 5),
	(21, 5, 1),
	(22, 5, 2),
	(23, 5, 3),
	(24, 5, 4),
	(25, 5, 5),
	(26, 6, 1),
	(27, 6, 2),
	(28, 6, 3),
	(29, 6, 4),
	(30, 6, 5),
	(31, 7, 1),
	(32, 7, 2),
	(33, 7, 3),
	(34, 7, 4),
	(35, 7, 5),
	(36, 8, 1),
	(37, 8, 2),
	(38, 8, 3),
	(39, 8, 4),
	(40, 8, 5),
	(41, 9, 1),
	(42, 9, 2),
	(43, 9, 3),
	(44, 9, 4),
	(45, 9, 5),
	(46, 10, 1),
	(47, 10, 2),
	(48, 10, 3),
	(49, 10, 4),
	(50, 10, 5),
	(51, 11, 1),
	(52, 11, 2),
	(53, 11, 3),
	(54, 11, 4),
	(55, 11, 5),
	(56, 12, 1),
	(57, 12, 2),
	(58, 12, 3),
	(59, 12, 4),
	(60, 12, 5),
	(64, 1, 6),
	(65, 1, 7),
	(66, 1, 8),
	(67, 1, 9),
	(68, 1, 10),
	(69, 1, 11),
	(70, 2, 6),
	(71, 2, 7),
	(72, 2, 8),
	(73, 2, 9),
	(74, 2, 10),
	(75, 2, 11),
	(76, 3, 6),
	(77, 3, 7),
	(78, 3, 8),
	(79, 3, 9),
	(80, 3, 10),
	(81, 3, 11),
	(88, 5, 6),
	(89, 5, 7),
	(90, 5, 8),
	(91, 5, 9),
	(92, 5, 10),
	(93, 5, 11),
	(127, 1, 12),
	(128, 11, 6),
	(129, 11, 7),
	(130, 11, 8),
	(131, 11, 9),
	(132, 11, 10),
	(133, 11, 11),
	(134, 14, 6),
	(135, 14, 7),
	(136, 14, 8),
	(137, 14, 9),
	(138, 14, 10),
	(139, 14, 11),
	(140, 8, 6),
	(141, 8, 7),
	(142, 8, 8),
	(143, 8, 9),
	(144, 8, 10),
	(145, 8, 11),
	(146, 9, 6),
	(147, 9, 7),
	(148, 9, 8),
	(149, 9, 9),
	(150, 9, 10),
	(151, 9, 11),
	(152, 15, 6),
	(153, 15, 7),
	(154, 15, 8),
	(155, 15, 9),
	(156, 15, 10),
	(157, 15, 11),
	(159, 14, 1),
	(160, 14, 2),
	(161, 14, 3),
	(162, 14, 4),
	(163, 14, 5),
	(164, 16, 1),
	(165, 16, 2),
	(166, 16, 3),
	(167, 16, 4),
	(168, 16, 5),
	(169, 15, 1),
	(170, 15, 2),
	(171, 15, 3),
	(172, 15, 4),
	(173, 15, 5),
	(178, 16, 7),
	(179, 16, 6),
	(180, 16, 8),
	(181, 16, 9);

-- Dumping structure for table u971098166_safetysite.role_permissions
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(10) unsigned NOT NULL,
  `permission_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `permission_id` (`permission_id`),
  CONSTRAINT `role_permissions_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `role_permissions_ibfk_2` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.role_permissions: ~40 rows (approximately)
REPLACE INTO `role_permissions` (`role_id`, `permission_id`) VALUES
	(1, 1),
	(1, 2),
	(1, 3),
	(1, 4),
	(1, 5),
	(1, 6),
	(1, 7),
	(1, 8),
	(2, 1),
	(2, 2),
	(2, 3),
	(2, 4),
	(2, 6),
	(3, 2),
	(3, 3),
	(3, 4),
	(3, 6),
	(4, 3),
	(5, 3),
	(8, 1),
	(8, 2),
	(8, 3),
	(8, 4),
	(8, 5),
	(8, 6),
	(8, 7),
	(8, 8),
	(9, 1),
	(9, 2),
	(9, 3),
	(9, 4),
	(9, 5),
	(9, 6),
	(10, 3),
	(10, 5),
	(11, 1),
	(11, 2),
	(11, 3),
	(11, 4),
	(11, 6);

-- Dumping structure for table u971098166_safetysite.roles
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_name` varchar(100) NOT NULL COMMENT 'Unique identifier for role logic',
  PRIMARY KEY (`id`),
  UNIQUE KEY `role_name` (`role_name`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.roles: ~16 rows (approximately)
REPLACE INTO `roles` (`id`, `role_name`) VALUES
	(1, 'Admin'),
	(2, 'Manager'),
	(3, 'Safety Leader'),
	(4, 'Corporate'),
	(5, 'JHSC Member'),
	(6, 'Full Time Employee'),
	(7, 'Part Time Employee'),
	(8, 'Owner / CEO'),
	(9, 'Safety Manager'),
	(10, 'Training Manager'),
	(11, 'Co-manager'),
	(12, 'Equipment Operator'),
	(13, 'Custom'),
	(14, 'Company Admin'),
	(15, 'Site Supervisor'),
	(16, 'JHSC Leader');

-- Dumping structure for table u971098166_safetysite.stores
CREATE TABLE IF NOT EXISTS `stores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `company_id` int(10) unsigned NOT NULL DEFAULT 1,
  `store_name` varchar(255) NOT NULL,
  `store_number` varchar(50) NOT NULL,
  `location_type` varchar(50) DEFAULT 'store' COMMENT 'e.g. store, office, warehouse, facility',
  `address` varchar(255) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `province_state` varchar(100) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `manager_user_id` int(10) unsigned DEFAULT NULL,
  `jhsc_leader_user_id` int(10) unsigned DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_store_number_per_company` (`company_id`,`store_number`),
  KEY `company_id` (`company_id`),
  KEY `manager_user_id` (`manager_user_id`),
  KEY `jhsc_leader_user_id` (`jhsc_leader_user_id`),
  KEY `idx_stores_active` (`is_active`),
  CONSTRAINT `fk_store_company` FOREIGN KEY (`company_id`) REFERENCES `companies` (`id`) ON DELETE CASCADE,
  CONSTRAINT `stores_ibfk_1` FOREIGN KEY (`manager_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `stores_ibfk_2` FOREIGN KEY (`jhsc_leader_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.stores: ~0 rows (approximately)
REPLACE INTO `stores` (`id`, `company_id`, `store_name`, `store_number`, `location_type`, `address`, `city`, `province_state`, `is_active`, `manager_user_id`, `jhsc_leader_user_id`, `created_at`, `updated_at`) VALUES
	(1, 1, 'Office', '1', 'store', NULL, NULL, NULL, 1, 3, 6, '2026-02-18 13:05:30', '2026-02-18 13:30:18');

-- Dumping structure for table u971098166_safetysite.user_job_sites
CREATE TABLE IF NOT EXISTS `user_job_sites` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `job_site_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_user_job_site` (`user_id`,`job_site_id`),
  KEY `idx_user_id` (`user_id`),
  KEY `idx_job_site_id` (`job_site_id`),
  CONSTRAINT `fk_ujs_job_site` FOREIGN KEY (`job_site_id`) REFERENCES `job_sites` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_ujs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Many-to-many: users assigned to job sites';

-- Dumping data for table u971098166_safetysite.user_job_sites: ~0 rows (approximately)

-- Dumping structure for table u971098166_safetysite.user_stores
CREATE TABLE IF NOT EXISTS `user_stores` (
  `user_id` int(10) unsigned NOT NULL,
  `store_id` int(10) unsigned NOT NULL,
  `assigned_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`user_id`,`store_id`),
  KEY `fk_user_stores_store` (`store_id`),
  CONSTRAINT `fk_user_stores_store` FOREIGN KEY (`store_id`) REFERENCES `stores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_user_stores_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.user_stores: ~15 rows (approximately)
REPLACE INTO `user_stores` (`user_id`, `store_id`, `assigned_at`) VALUES
	(1, 1, '2026-02-18 13:06:12'),
	(2, 1, '2026-02-18 13:05:59'),
	(3, 1, '2026-02-18 13:21:50'),
	(4, 1, '2026-02-18 13:21:50'),
	(5, 1, '2026-02-18 13:21:50'),
	(6, 1, '2026-02-18 13:21:50'),
	(7, 1, '2026-02-18 13:21:50'),
	(8, 1, '2026-02-18 13:21:50'),
	(9, 1, '2026-02-18 13:21:50'),
	(10, 1, '2026-02-18 13:21:50'),
	(11, 1, '2026-02-18 13:21:50'),
	(12, 1, '2026-02-18 13:21:50'),
	(13, 1, '2026-02-18 13:21:50'),
	(14, 1, '2026-02-18 13:21:50'),
	(15, 1, '2026-02-24 20:45:16');

-- Dumping structure for table u971098166_safetysite.users
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `role_id` int(10) unsigned NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL COMMENT 'Bcrypt Hash',
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `employee_position` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `is_platform_admin` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = platform level admin',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `role_id` (`role_id`),
  CONSTRAINT `users_ibfk_1` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Dumping data for table u971098166_safetysite.users: ~15 rows (approximately)
REPLACE INTO `users` (`id`, `role_id`, `email`, `password`, `first_name`, `last_name`, `employee_position`, `created_at`, `updated_at`, `is_platform_admin`) VALUES
	(1, 1, 'admin@macweb.ca', '$2y$10$4EjAQ.FKXAMQ38nCMKK0TuXrDbUMjpdy2KUeP0W5w0jdw16JsdD52', 'Web', 'Admin', 'System Administrator', '2026-02-18 13:00:13', '2026-03-12 12:17:15', 1),
	(2, 1, 'justin@bizolver.com', '$2y$10$QGKE3U5PJJEQNhyvtp3umeQU.yYaV.0ix6olJdIoyrgGbaxwHgHDK', 'Justin', 'Weir', 'Admin', '2026-02-18 13:05:59', '2026-03-12 12:17:15', 1),
	(3, 2, 'manager@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Manager', 'Store Manager', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(4, 3, 'safety@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'SafetyLead', 'Safety Supervisor', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(5, 4, 'corporate@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Corporate', 'Regional Director', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(6, 5, 'jhsc@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'JHSC', 'Committee Member', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(7, 6, 'ft_employee@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'FullTime', 'Sales Associate', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(8, 7, 'pt_employee@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'PartTime', 'Cashier', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(9, 8, 'owner@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Owner', 'CEO', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(10, 9, 'safetymanager@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'SafetyMgr', 'EHS Director', '2026-02-18 13:21:49', '2026-02-18 13:21:49', 0),
	(11, 10, 'training@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Training', 'Training Coordinator', '2026-02-18 13:21:50', '2026-02-18 13:21:50', 0),
	(12, 11, 'comanager@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'CoManager', 'Assistant Manager', '2026-02-18 13:21:50', '2026-02-18 13:21:50', 0),
	(13, 12, 'operator@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Operator', 'Forklift Driver', '2026-02-18 13:21:50', '2026-02-18 13:21:50', 0),
	(14, 13, 'custom@test.com', '$2y$10$vI8aWBnW3fID.ZQ4/zo1G.q1lRps.9cGLcZEiGDMVr5yUP1KUOYTa', 'Test', 'Custom', 'Contractor', '2026-02-18 13:21:50', '2026-02-18 13:21:50', 0),
	(15, 1, 'skybordage@hotmail.com', '$2y$10$A/s.41TPZAqpDKKSTJrMoujg97Fr7h/fo6bBNgX7Be0pdsWpIuvYq', 'Caleb', 'Bordage', 'Tester', '2026-02-24 20:45:16', '2026-03-12 12:17:15', 1);

/*!40103 SET TIME_ZONE=IFNULL(@OLD_TIME_ZONE, 'system') */;
/*!40101 SET SQL_MODE=IFNULL(@OLD_SQL_MODE, '') */;
/*!40014 SET FOREIGN_KEY_CHECKS=IFNULL(@OLD_FOREIGN_KEY_CHECKS, 1) */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40111 SET SQL_NOTES=IFNULL(@OLD_SQL_NOTES, 1) */;

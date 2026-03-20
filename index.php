<?php
/**
 * Main Front Controller - index.php
 *
 * This is the primary entry point for the Sentry OHS application.
 * It handles page routing, dynamic SEO metadata injection, layout construction 
 * (header/footer), and global authentication checks.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// Initialize Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include Core Dependencies
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/csrf.php';

// --- 1. Route Determination ---
// Default to 'home' if no page is explicitly requested
$page = isset($_GET['page']) && !empty($_GET['page']) ? basename($_GET['page']) : 'home';

// --- 2. Route Whitelist ---
// Strict whitelist to prevent Local File Inclusion (LFI) vulnerabilities
$allowedPages = [
    // Public Pages
    'home', 'services', 'about', 'contact', 'login', '404',
    
    // Employee Hub & Profiling
    'dashboard', 'profile', 'profile-edit',
    
    // Hazard Reporting Module
    'hazard-report', 'my-reports', 'store-reports', 'edit-report',
    
    // Field Level Hazard Assessments (FLHA)
    'flha-list', 'flha-form', 'flha-close',
    
    // Incident & Accident Module
    'incident-report', 'store-incidents',
    
    // Analytics
    'metrics',
    
    // Meetings & Toolbox Talks
    'meetings-list', 'host-meeting',
    
    // Training & Certifications
    'training-matrix',
    
    // Dynamic Checklists
    'checklist-builder',
    'preshift-checklist',

    // Equipment Management
    'equipment-management',
    
    // System Administration
    'admin', 'admin-edit-user', 'company-admin'
];

// If the requested page is not in the whitelist, force a 404
if (!in_array($page, $allowedPages)) {
    $page = '404';
}

// --- 3. Dynamic SEO & Access Control (Database Check) ---
$pageTitle = 'Sentry OHS';
$pageDescription = 'Enterprise Environment, Health, and Safety Management.';
$requiresLogin = false;

// Query the database for page-specific SEO and access requirements
$seoSql = "SELECT meta_title, meta_description, requires_login FROM page_seo WHERE page_route = ? LIMIT 1";
if ($stmt = $conn->prepare($seoSql)) {
    $stmt->bind_param("s", $page);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        $pageTitle = $row['meta_title'] . ' | Sentry OHS';
        $pageDescription = $row['meta_description'];
        $requiresLogin = (bool)$row['requires_login'];
    }
    $stmt->close();
}

// Global Authentication Enforcement
// If the database flags the page as requiring login, and no user session exists, kick to login.
if ($requiresLogin && !isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

// --- 4. Page Assembly ---

// 4A. Load Header (injects CSS, Tailwind, FontAwesome, and standard navigation)
include 'includes/header.php';

// 4B. Load Requested View (The core content of the page)
$viewPath = 'pages/' . $page . '.php';
if (file_exists($viewPath)) {
    include $viewPath;
} else {
    // Failsafe in case a whitelisted file gets deleted
    include 'pages/404.php';
}

// 4C. Load Footer (injects standard footer UI and global JS scripts)
include 'includes/footer.php';
?>
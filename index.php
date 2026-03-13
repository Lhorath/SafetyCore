<?php
/**
 * Main Application Router - index.php
 *
 * Single entry point (Front Controller) for NorthPoint 360.
 * All web requests are routed through this file.
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-08 — Added 'company-admin' to $allowedPages whitelist.
 *           Previously missing, causing the entire Company Admin panel to
 *           resolve to a 404 on every request.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   9.0.0 (NorthPoint Beta 09)
 */

// ── 1. Routing Configuration ──────────────────────────────────────────────────

$defaultPage   = 'home';
$requestedPage = $_GET['page'] ?? $defaultPage;
$requestedPage = basename($requestedPage);  // LFI / directory traversal protection

$allowedPages = [
    // Public Pages
    'home',
    'about',
    'contact',
    'services',

    // Authentication & User Management
    'login',
    'profile',
    'profile-edit',

    // Core Dashboards
    'dashboard',
    'metrics',

    // Hazard Reporting Lifecycle
    'hazard-report',
    'my-reports',
    'store-reports',
    'edit-report',

    // FLHA Module [Beta 05]
    'flha-list',
    'flha-form',
    'flha-close',

    // Incident Management Module [Beta 05]
    'incident-report',
    'store-incidents',

    // Meetings & Toolbox Talks Module [Beta 05]
    'meetings-list',
    'host-meeting',

    // Company-Level Administration [Beta 08]
    'company-admin',      // F-08 FIX: was missing, entire panel returned 404

    // Platform Administration
    'admin',
    'admin-edit-user',
];

// ── 2. View Resolution ────────────────────────────────────────────────────────

$viewFile = in_array($requestedPage, $allowedPages)
    ? "pages/{$requestedPage}.php"
    : 'pages/404.php';

if (!file_exists($viewFile)) {
    $viewFile = 'pages/404.php';
}

// ── 3. Page Rendering ─────────────────────────────────────────────────────────

require_once 'includes/header.php';
require_once $viewFile;
require_once 'includes/footer.php';

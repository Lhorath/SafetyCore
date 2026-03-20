<?php
// Start a session on every page. This must be the very first line of the file.
// This enables user authentication state tracking ($isLoggedIn) globally.
if (session_status() === PHP_SESSION_NONE) { session_start(); }

/**
 * Header Template - includes/header.php
 *
 * This file contains the opening HTML structure for every page in the application.
 * It handles:
 * 1. Global Session Initialization
 * 2. Database Connection (via db.php)
 * 3. Dynamic SEO & Page Titling (Beta 05 Update)
 * 4. CSS/Font Resource Loading (Tailwind, FontAwesome, Google Fonts)
 * 5. Tailwind CSS Configuration (Custom Sentry OHS Brand Palette)
 * 6. Responsive Navigation Bar (Desktop & Mobile) with State-Aware Links
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// Establish the database connection.
require_once 'db.php';

// --- 1. Dynamic SEO & Meta Data Logic ---
// Get the current page from the URL. Defaults to 'home' if not set.
$currentPage = $_GET['page'] ?? 'home';
$currentPage = basename($currentPage); // Security sanitization

// Establish sensible default fallbacks in case the database doesn't have an entry for the route
$seo = [
    'title'          => ucwords(str_replace('-', ' ', $currentPage)),
    'description'    => 'Sentry OHS is a comprehensive EHS Management Platform.',
    'keywords'       => 'EHS, safety, compliance, workplace, management software',
    'og_image'       => '/style/images/logo.png',
    'requires_login' => false
];

// Query the database for the current page's specific SEO settings
$seoSql = "SELECT meta_title, meta_description, meta_keywords, og_image, requires_login FROM page_seo WHERE page_route = ?";
if ($stmt = $conn->prepare($seoSql)) {
    $stmt->bind_param("s", $currentPage);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($row = $result->fetch_assoc()) {
        // Overwrite defaults with database data if it exists and is not empty
        $seo['title']          = !empty($row['meta_title']) ? $row['meta_title'] : $seo['title'];
        $seo['description']    = !empty($row['meta_description']) ? $row['meta_description'] : $seo['description'];
        $seo['keywords']       = !empty($row['meta_keywords']) ? $row['meta_keywords'] : $seo['keywords'];
        $seo['og_image']       = !empty($row['og_image']) ? $row['og_image'] : $seo['og_image'];
        $seo['requires_login'] = (bool)$row['requires_login'];
    }
    $stmt->close();
}

// Ensure the description clearly states if a login is required for external web crawlers
$publicPages = ['home', 'about', 'contact', 'services', 'terms', 'privacy', 'login'];
$isProtected = !in_array($currentPage, $publicPages) || $seo['requires_login'];

if ($isProtected) {
    // Append this strictly to the meta description, not the visual UI
    $seo['description'] .= " (Note: Access to this feature requires an active, authenticated Sentry OHS user account.)";
}

// Set variables for the UI
$subheaderTitle = $seo['title'];
$isLoggedIn = isset($_SESSION['user']);

// Role-aware navigation shortcuts (matches dashboard permission model)
$canMeetings = false;
$canMetrics = false;
$canHazardReview = false;
$canIncidentReview = false;
$canCompanyAdmin = false;
$canPlatformAdmin = false;
$adminShortcutRoute = '';
$adminShortcutLabel = '';

if ($isLoggedIn) {
    require_once __DIR__ . '/permissions.php';
    $canMeetings = can_access_module($conn, 'meetings_talks');
    $canMetrics = can_access_module($conn, 'metrics_stats');
    $canHazardReview = can_access_module($conn, 'location_hazards');
    $canIncidentReview = can_access_module($conn, 'manage_incidents');
    $canCompanyAdmin = can_access_module($conn, 'company_users') || can_access_module($conn, 'company_structure');
    $canPlatformAdmin = can_access_module($conn, 'platform_admin');

    if ($canPlatformAdmin) {
        $adminShortcutRoute = '/admin';
        $adminShortcutLabel = 'Platform Admin';
    } elseif ($canCompanyAdmin) {
        $adminShortcutRoute = '/company-admin?view=users';
        $adminShortcutLabel = 'Company Admin';
    }
}

$isAdminPage = ($currentPage === 'admin' || $currentPage === 'company-admin');
$hasWorkspaceMenu = $isLoggedIn && ($canMeetings || $canMetrics || $canHazardReview || $canIncidentReview || !empty($adminShortcutRoute));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <title>Sentry OHS &bull; <?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="Sentry OHS (macweb.ca)">
    <meta name="robots" content="<?php echo $isProtected ? 'noindex, nofollow' : 'index, follow'; ?>">

    <meta property="og:type" content="website">
    <meta property="og:title" content="Sentry OHS - <?php echo htmlspecialchars($seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo['og_image']); ?>">
    <meta property="og:site_name" content="Sentry OHS">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Sentry OHS - <?php echo htmlspecialchars($seo['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo['og_image']); ?>">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico" type="image/x-icon">
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@500;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/style/css/tailwind.production.min.css">
    <link rel="stylesheet" href="style/css/style.css">
</head>
<body class="flex flex-col min-h-screen">

    <nav class="app-topbar sticky top-0 z-50 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between gap-4">
                <a href="/" class="app-brand flex items-center gap-3 group focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500/60 rounded-md">
                    <img src="/style/images/logo.png" alt="Sentry OHS" class="h-8 w-auto transition-transform duration-300 group-hover:scale-105">
                    <span class="text-[1.05rem] font-extrabold leading-none tracking-tight font-heading whitespace-nowrap">
                        <span style="color: #0F172A;">Sentry</span>
                        <span style="color: #2563EB;">OHS</span>
                    </span>
                </a>

                <div class="hidden md:flex items-center gap-1 min-w-0">
                    <a href="/" class="navbar-link <?php echo ($currentPage === 'home') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'home') ? 'aria-current="page"' : ''; ?>>Home</a>
                    <a href="/about" class="navbar-link <?php echo ($currentPage === 'about') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'about') ? 'aria-current="page"' : ''; ?>>About</a>
                    <a href="/services" class="navbar-link <?php echo ($currentPage === 'services') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'services') ? 'aria-current="page"' : ''; ?>>Services</a>
                    <a href="/contact" class="navbar-link <?php echo ($currentPage === 'contact') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'contact') ? 'aria-current="page"' : ''; ?>>Contact</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="/dashboard" class="navbar-link <?php echo ($currentPage === 'dashboard') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'dashboard') ? 'aria-current="page"' : ''; ?>>Dashboard</a>
                        <?php if ($hasWorkspaceMenu): ?>
                            <details class="navbar-more">
                                <summary class="navbar-link list-none">
                                    More
                                    <i class="fas fa-chevron-down text-[10px]"></i>
                                </summary>
                                <div class="navbar-more-menu">
                                    <?php if ($canMeetings): ?>
                                        <a href="/meetings-list" class="navbar-more-item <?php echo ($currentPage === 'meetings-list') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'meetings-list') ? 'aria-current="page"' : ''; ?>>Meetings</a>
                                    <?php endif; ?>
                                    <?php if ($canMetrics): ?>
                                        <a href="/metrics" class="navbar-more-item <?php echo ($currentPage === 'metrics') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'metrics') ? 'aria-current="page"' : ''; ?>>Executive Metrics</a>
                                    <?php endif; ?>
                                    <?php if ($canHazardReview): ?>
                                        <a href="/store-reports" class="navbar-more-item <?php echo ($currentPage === 'store-reports') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'store-reports') ? 'aria-current="page"' : ''; ?>>Hazard Reviews</a>
                                    <?php endif; ?>
                                    <?php if ($canIncidentReview): ?>
                                        <a href="/store-incidents" class="navbar-more-item <?php echo ($currentPage === 'store-incidents') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'store-incidents') ? 'aria-current="page"' : ''; ?>>Incident Reviews</a>
                                    <?php endif; ?>
                                    <?php if (!empty($adminShortcutRoute)): ?>
                                        <a href="<?php echo $adminShortcutRoute; ?>" class="navbar-more-item <?php echo $isAdminPage ? 'is-active' : ''; ?>" <?php echo $isAdminPage ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($adminShortcutLabel); ?></a>
                                    <?php endif; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <div class="hidden md:flex items-center gap-2 shrink-0">
                    <?php if ($isLoggedIn): ?>
                        <a href="/profile" class="navbar-profile <?php echo ($currentPage === 'profile' || $currentPage === 'profile-edit') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'profile' || $currentPage === 'profile-edit') ? 'aria-current="page"' : ''; ?>>
                            <span class="saas-user-avatar"><?php echo strtoupper(substr($_SESSION['user']['first_name'], 0, 1)); ?></span>
                            <span class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($_SESSION['user']['first_name']); ?></span>
                        </a>
                        <a href="/logout.php" class="navbar-logout">Sign Out</a>
                    <?php else: ?>
                        <a href="/login" class="btn bg-primary text-white hover:bg-secondary !px-5 !py-2 !text-sm shadow-sm transition-colors focus:ring-2 focus:ring-secondary/50 focus:outline-none">
                            Log In
                        </a>
                    <?php endif; ?>
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="saas-mobile-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open navigation menu">
                        <i class="fas fa-bars text-base"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="app-mobile-menu hidden md:hidden bg-white border-t border-slate-100 shadow-xl w-full left-0 z-50">
            <div class="px-4 py-4 space-y-1.5">
                <div class="mobile-nav-group">
                    <p class="mobile-nav-label">Main Navigation</p>
                    <a href="/" class="mobile-nav-link <?php echo ($currentPage === 'home') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'home') ? 'aria-current="page"' : ''; ?>>Home</a>
                    <a href="/about" class="mobile-nav-link <?php echo ($currentPage === 'about') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'about') ? 'aria-current="page"' : ''; ?>>About</a>
                    <a href="/services" class="mobile-nav-link <?php echo ($currentPage === 'services') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'services') ? 'aria-current="page"' : ''; ?>>Services</a>
                    <a href="/contact" class="mobile-nav-link <?php echo ($currentPage === 'contact') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'contact') ? 'aria-current="page"' : ''; ?>>Contact</a>
                    <?php if ($isLoggedIn): ?>
                        <a href="/dashboard" class="mobile-nav-link <?php echo ($currentPage === 'dashboard') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'dashboard') ? 'aria-current="page"' : ''; ?>>Dashboard</a>
                    <?php endif; ?>
                </div>

                <?php if ($isLoggedIn && $hasWorkspaceMenu): ?>
                    <div class="mobile-nav-divider"></div>
                    <div class="mobile-nav-group">
                        <p class="mobile-nav-label">More</p>
                        <?php if ($canMeetings): ?>
                            <a href="/meetings-list" class="mobile-nav-link <?php echo ($currentPage === 'meetings-list') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'meetings-list') ? 'aria-current="page"' : ''; ?>>Meetings</a>
                        <?php endif; ?>
                        <?php if ($canMetrics): ?>
                            <a href="/metrics" class="mobile-nav-link <?php echo ($currentPage === 'metrics') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'metrics') ? 'aria-current="page"' : ''; ?>>Executive Metrics</a>
                        <?php endif; ?>
                        <?php if ($canHazardReview): ?>
                            <a href="/store-reports" class="mobile-nav-link <?php echo ($currentPage === 'store-reports') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'store-reports') ? 'aria-current="page"' : ''; ?>>Hazard Reviews</a>
                        <?php endif; ?>
                        <?php if ($canIncidentReview): ?>
                            <a href="/store-incidents" class="mobile-nav-link <?php echo ($currentPage === 'store-incidents') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'store-incidents') ? 'aria-current="page"' : ''; ?>>Incident Reviews</a>
                        <?php endif; ?>
                        <?php if (!empty($adminShortcutRoute)): ?>
                            <a href="<?php echo $adminShortcutRoute; ?>" class="mobile-nav-link <?php echo $isAdminPage ? 'is-active' : ''; ?>" <?php echo $isAdminPage ? 'aria-current="page"' : ''; ?>><?php echo htmlspecialchars($adminShortcutLabel); ?></a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($isLoggedIn): ?>
                    <div class="mobile-nav-divider"></div>
                    <div class="mobile-nav-group">
                        <p class="mobile-nav-label">Account</p>
                        <a href="/profile" class="mobile-nav-link <?php echo ($currentPage === 'profile' || $currentPage === 'profile-edit') ? 'is-active' : ''; ?>" <?php echo ($currentPage === 'profile' || $currentPage === 'profile-edit') ? 'aria-current="page"' : ''; ?>>Profile</a>
                        <a href="/logout.php" class="mobile-nav-link text-red-600 hover:bg-red-50">Sign Out</a>
                    </div>
                <?php else: ?>
                    <div class="mobile-nav-divider"></div>
                    <a href="/login" class="mt-2 flex w-full justify-center items-center px-4 py-2 rounded-lg shadow-sm text-sm font-semibold text-white bg-primary hover:bg-secondary transition-colors">
                        Log In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <div class="app-subheader bg-primary text-white py-2 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <div class="flex items-center gap-2.5 text-xs sm:text-sm">
                <i class="fas fa-compass text-secondary/80"></i>
                <span class="text-slate-400 uppercase tracking-widest text-[10px] sm:text-xs font-bold">Section:</span>
                <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($subheaderTitle); ?></span>
            </div>
        </div>
    </div>

    <main class="app-main flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
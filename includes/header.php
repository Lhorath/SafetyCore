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
$publicPages = ['home', 'about', 'contact', 'services', 'login'];
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
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Montserrat:wght@500;700;800&display=swap" rel="stylesheet">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="/style/css/tailwind.production.min.css">
    <link rel="stylesheet" href="style/css/style.css">
</head>
<body class="flex flex-col min-h-screen">

    <nav class="bg-white/90 backdrop-blur-md shadow-sm sticky top-0 z-50 border-b border-slate-200 transition-all">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                
                <a href="/" class="flex items-center gap-3 group focus:outline-none">
                    <img src="/style/images/logo.png" alt="Sentry OHS" class="h-8 w-auto transition-transform duration-300 group-hover:scale-105">
                    <div class="hidden sm:flex flex-col justify-center">
                        <span class="text-[1.15rem] font-extrabold text-primary leading-none tracking-tight font-heading">SENTRY<span class="text-secondary">OHS</span></span>
                    </div>
                </a>

                <div class="hidden md:flex items-center space-x-1">
                    <a href="/" class="nav-link <?php echo ($currentPage == 'home') ? 'active' : ''; ?>">Home</a>
                    <a href="/services" class="nav-link <?php echo ($currentPage == 'services') ? 'active' : ''; ?>">Solutions</a>
                    <a href="/contact" class="nav-link <?php echo ($currentPage == 'contact') ? 'active' : ''; ?>">Contact</a>
                    
                    <div class="flex items-center border-l border-slate-200 ml-3 pl-3 gap-2">
                        <?php if ($isLoggedIn): ?>
                            <a href="/dashboard" class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
                            <?php if ($canMeetings): ?>
                                <a href="/meetings-list" class="nav-link <?php echo ($currentPage == 'meetings-list') ? 'active' : ''; ?>">Meetings</a>
                            <?php endif; ?>
                            <?php if ($canMetrics): ?>
                                <a href="/metrics" class="nav-link <?php echo ($currentPage == 'metrics') ? 'active' : ''; ?>">Metrics</a>
                            <?php endif; ?>
                            <?php if (!empty($adminShortcutRoute)): ?>
                                <a href="<?php echo $adminShortcutRoute; ?>" class="nav-link <?php echo ($currentPage == 'admin' || $currentPage == 'company-admin') ? 'active' : ''; ?>"><?php echo htmlspecialchars($adminShortcutLabel); ?></a>
                            <?php endif; ?>
                            
                            <a href="/profile" class="flex items-center gap-2 px-3 py-1.5 rounded-full border border-slate-200 hover:border-blue-300 hover:bg-blue-50 transition-all group focus:outline-none focus:ring-2 focus:ring-blue-500/50">
                                <div class="w-6 h-6 rounded-full bg-secondary text-white flex items-center justify-center text-[10px] font-bold shadow-sm group-hover:scale-105 transition-transform">
                                    <?php echo strtoupper(substr($_SESSION['user']['first_name'], 0, 1)); ?>
                                </div>
                                <span class="text-sm font-semibold text-slate-700 group-hover:text-secondary transition-colors">
                                    <?php echo htmlspecialchars($_SESSION['user']['first_name']); ?>
                                </span>
                            </a>

                            <a href="/logout.php" class="p-2 text-slate-400 hover:text-accent-red hover:bg-red-50 rounded-full transition-colors focus:outline-none" title="Sign Out">
                                <i class="fas fa-sign-out-alt"></i>
                            </a>
                        <?php else: ?>
                            <a href="/login" class="btn bg-primary text-white hover:bg-secondary !px-5 !py-2 !text-sm shadow-sm transition-colors focus:ring-2 focus:ring-secondary/50 focus:outline-none">
                                Log In
                            </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-slate-500 hover:text-primary focus:outline-none p-2 rounded-md hover:bg-slate-100 transition-colors">
                        <i class="fas fa-bars text-xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <div id="mobile-menu" class="hidden md:hidden bg-white border-t border-slate-100 shadow-xl absolute w-full left-0 z-50">
            <div class="px-4 py-4 space-y-1">
                <a href="/" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                    <i class="fas fa-home w-6 text-slate-400"></i> Home
                </a>
                <a href="/services" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                    <i class="fas fa-layer-group w-6 text-slate-400"></i> Solutions
                </a>
                <a href="/contact" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                    <i class="fas fa-envelope w-6 text-slate-400"></i> Contact
                </a>
                
                <div class="border-t border-slate-100 my-2"></div>

                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard" class="flex items-center px-3 py-2.5 rounded-md text-base font-bold text-secondary bg-blue-50/50 hover:bg-blue-50 transition-colors">
                        <i class="fas fa-chart-line w-6"></i> Dashboard
                    </a>
                    <?php if ($canMeetings): ?>
                        <a href="/meetings-list" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                            <i class="fas fa-users w-6 text-slate-400"></i> Meetings
                        </a>
                    <?php endif; ?>
                    <?php if ($canMetrics): ?>
                        <a href="/metrics" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                            <i class="fas fa-chart-pie w-6 text-slate-400"></i> Metrics
                        </a>
                    <?php endif; ?>
                    <?php if ($canHazardReview): ?>
                        <a href="/store-reports" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                            <i class="fas fa-store w-6 text-slate-400"></i> Hazard Reviews
                        </a>
                    <?php endif; ?>
                    <?php if ($canIncidentReview): ?>
                        <a href="/store-incidents" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                            <i class="fas fa-file-medical-alt w-6 text-slate-400"></i> Incident Reviews
                        </a>
                    <?php endif; ?>
                    <?php if (!empty($adminShortcutRoute)): ?>
                        <a href="<?php echo $adminShortcutRoute; ?>" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                            <i class="fas fa-cogs w-6 text-slate-400"></i> <?php echo htmlspecialchars($adminShortcutLabel); ?>
                        </a>
                    <?php endif; ?>
                    <a href="/profile" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-slate-700 hover:bg-slate-50 hover:text-secondary transition-colors">
                        <i class="fas fa-user-circle w-6 text-slate-400"></i> My Profile
                    </a>
                    <a href="/logout.php" class="flex items-center px-3 py-2.5 rounded-md text-base font-medium text-accent-red hover:bg-red-50 transition-colors mt-2">
                        <i class="fas fa-sign-out-alt w-6"></i> Sign Out
                    </a>
                <?php else: ?>
                    <a href="/login" class="mt-4 flex w-full justify-center items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-base font-medium text-white bg-primary hover:bg-secondary transition-colors">
                        Log In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <div class="bg-primary text-white py-2 border-b border-slate-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
            <div class="flex items-center gap-2.5 text-xs sm:text-sm">
                <i class="fas fa-compass text-secondary/80"></i>
                <span class="text-slate-400 uppercase tracking-widest text-[10px] sm:text-xs font-bold">Section:</span>
                <span class="font-semibold text-slate-200"><?php echo htmlspecialchars($subheaderTitle); ?></span>
            </div>
        </div>
    </div>

    <main class="flex-grow max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 py-8">
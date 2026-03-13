<?php
// Start a session on every page. This must be the very first line of the file.
// This enables user authentication state tracking ($isLoggedIn) globally.
session_start();

/**
 * Header Template - includes/header.php
 *
 * This file contains the opening HTML structure for every page in the application.
 * It handles:
 * 1. Global Session Initialization
 * 2. Database Connection (via db.php)
 * 3. Dynamic SEO & Page Titling (Beta 05 Update)
 * 4. CSS/Font Resource Loading (Tailwind, FontAwesome, Google Fonts)
 * 5. Tailwind CSS Configuration (Custom NorthPoint 360 Brand Palette)
 * 6. Responsive Navigation Bar (Desktop & Mobile) with State-Aware Links
 *
 * Updates in Beta 05 (Dynamic SEO):
 * - Removed hardcoded $pageTitles array.
 * - Implemented dynamic database fetching from `page_seo` table for metadata.
 * - Added comprehensive Meta, OpenGraph, and Twitter Card tags.
 * - Automatically appends login-requirement notices to meta descriptions for web crawlers.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   5.0.0 (NorthPoint Beta 05)
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
    'description'    => 'NorthPoint 360 is a comprehensive EHS Management Platform.',
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
    $seo['description'] .= " (Note: Access to this feature requires an active, authenticated NorthPoint 360 user account.)";
}

// Set variables for the UI
$subheaderTitle = $seo['title'];
$isLoggedIn = isset($_SESSION['user']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    
    <!-- Dynamic Title & Meta Tags -->
    <title>NorthPoint 360 &bull; <?php echo htmlspecialchars($seo['title']); ?></title>
    <meta name="description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="keywords" content="<?php echo htmlspecialchars($seo['keywords']); ?>">
    <meta name="author" content="NorthPoint 360 (macweb.ca)">
    <!-- Prevent indexing of protected dashboard/admin routes -->
    <meta name="robots" content="<?php echo $isProtected ? 'noindex, nofollow' : 'index, follow'; ?>">

    <!-- Open Graph / Facebook / LinkedIn -->
    <meta property="og:type" content="website">
    <meta property="og:title" content="NorthPoint 360 - <?php echo htmlspecialchars($seo['title']); ?>">
    <meta property="og:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta property="og:image" content="<?php echo htmlspecialchars($seo['og_image']); ?>">
    <meta property="og:site_name" content="NorthPoint 360">

    <!-- Twitter Cards -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="NorthPoint 360 - <?php echo htmlspecialchars($seo['title']); ?>">
    <meta name="twitter:description" content="<?php echo htmlspecialchars($seo['description']); ?>">
    <meta name="twitter:image" content="<?php echo htmlspecialchars($seo['og_image']); ?>">
    
    <!-- Google Fonts: Montserrat (Weights: 400, 500, 600, 700) -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Font Awesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <!-- External CSS (For custom overrides not handled by Tailwind) -->
    <link rel="stylesheet" href="style/css/style.css">

    <!-- Tailwind CSS (CDN) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <!-- Tailwind Configuration -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    // Custom Brand Palette: "Blues and Blacks"
                    colors: {
                        primary: '#0f172a',    // Dark Slate (Almost Black/Navy) - The Foundation
                        secondary: '#3b82f6',  // Royal Blue - Highlights & Buttons
                        accent: {
                            red: '#ef4444',    // Safety Red (Alerts)
                            gray: '#111827',   // Rich Black (Text/Footer)
                            light: '#e2e8f0'   // Light Slate (Borders/Subtle backgrounds)
                        },
                        bg: '#f8fafc',         // Cool Gray (Page Background)
                        success: '#10b981'     // Emerald Green
                    },
                    fontFamily: {
                        sans: ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>

    <!-- Custom Styles Layer (Reusable Tailwind Components) -->
    <style type="text/tailwindcss">
        @layer components {
            /* Standard Button Styles */
            .btn {
                @apply px-6 py-3 rounded-lg font-bold transition duration-300 ease-in-out inline-block text-center cursor-pointer;
            }
            .btn-primary {
                @apply bg-secondary text-white hover:bg-blue-700 shadow-md; /* Blue Buttons */
            }
            .btn-dark {
                @apply bg-primary text-white hover:bg-slate-800 shadow-md; /* Dark Buttons */
            }
            .btn-accent {
                @apply bg-accent-red text-white hover:bg-red-700 shadow-md; /* Red Buttons */
            }
            .btn-secondary {
                @apply bg-gray-200 text-gray-800 hover:bg-gray-300 shadow-md; /* Neutral Buttons */
            }
            
            /* Form Input Styles */
            .form-input {
                @apply w-full px-4 py-3 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-secondary focus:border-transparent transition bg-white;
            }
            .form-label {
                @apply block mb-2 font-bold text-primary text-sm uppercase tracking-wide;
            }
            
            /* Card Container Style */
            .card {
                @apply bg-white rounded-xl shadow-sm border border-gray-200 p-6;
            }

            /* Navigation Links */
            .nav-link { 
                @apply text-gray-600 hover:text-secondary font-semibold transition duration-200; 
            }
            .nav-link.active { 
                @apply text-secondary border-b-2 border-secondary; 
            }

            /* Modal Window Styles */
            /* Note: 'hidden' class is applied via JS/HTML, not enforced here to allow toggling */
            .modal {
                @apply fixed inset-0 z-50 overflow-auto bg-black bg-opacity-60 flex items-center justify-center;
            }
            .modal-content {
                @apply bg-white mx-auto p-6 border border-gray-300 w-11/12 max-w-lg rounded-xl shadow-2xl relative;
            }
        }
        
        /* Apply base styles to body */
        body {
            @apply bg-bg text-accent-gray font-sans;
        }
    </style>
</head>
<body class="flex flex-col min-h-screen">

    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md z-50 relative border-b border-gray-100">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center h-20">
                
                <!-- Logo Section -->
                <a href="/" class="flex items-center gap-3 group">
                    <img src="style/images/logo.png" alt="NorthPoint 360 Logo" class="h-10 w-auto transition-transform group-hover:scale-105">
                    <div class="hidden md:flex flex-col">
                        <span class="text-xl font-extrabold text-primary leading-none tracking-tight">NORTHPOINT<span class="text-secondary">360</span></span>
                        <span class="text-[10px] text-gray-400 font-bold uppercase tracking-widest">EHS Management</span>
                    </div>
                </a>

                <!-- Desktop Menu -->
                <div class="hidden md:flex items-center space-x-8">
                    <!-- Public Links -->
                    <a href="/" class="nav-link <?php echo ($currentPage == 'home') ? 'active' : ''; ?>">Home</a>
                    <a href="/services" class="nav-link <?php echo ($currentPage == 'services') ? 'active' : ''; ?>">Solutions</a>
                    <a href="/contact" class="nav-link <?php echo ($currentPage == 'contact') ? 'active' : ''; ?>">Contact</a>
                    
                    <!-- Authentication Logic -->
                    <?php if ($isLoggedIn): ?>
                        <div class="h-6 w-px bg-gray-200 mx-2"></div> <!-- Vertical Divider -->
                        
                        <!-- Internal App Links -->
                        <a href="/dashboard" class="nav-link <?php echo ($currentPage == 'dashboard') ? 'active' : ''; ?>">Dashboard</a>
                        
                        <!-- Profile Link with Avatar -->
                        <a href="/profile" class="flex items-center gap-2 text-primary hover:text-secondary font-bold transition group">
                            <div class="w-8 h-8 rounded-full bg-secondary text-white flex items-center justify-center text-xs shadow-sm">
                                <?php echo substr($_SESSION['user']['first_name'], 0, 1); ?>
                            </div>
                            <span class="group-hover:underline"><?php echo htmlspecialchars($_SESSION['user']['first_name']); ?></span>
                        </a>

                        <!-- Logout Button -->
                        <a href="/logout.php" class="btn bg-gray-100 text-accent-red hover:bg-red-50 !px-4 !py-2 !rounded text-sm border border-gray-200 transition">
                            Sign Out
                        </a>
                    <?php else: ?>
                        <div class="h-6 w-px bg-gray-200 mx-2"></div> <!-- Vertical Divider -->
                        <a href="/login" class="btn btn-dark !px-6 !py-2 shadow-lg hover:-translate-y-0.5 transition transform">
                            Log In
                        </a>
                    <?php endif; ?>
                </div>

                <!-- Mobile Menu Button (Hamburger) -->
                <div class="md:hidden flex items-center">
                    <button id="mobile-menu-btn" class="text-gray-600 hover:text-secondary focus:outline-none p-2">
                        <i class="fas fa-bars text-2xl"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- Mobile Menu (Collapsible) -->
        <!-- Controlled by JavaScript in footer.php -->
        <div id="mobile-menu" class="hidden md:hidden bg-gray-50 border-t border-gray-200 absolute w-full shadow-xl left-0 z-50">
            <div class="px-4 pt-2 pb-6 space-y-2">
                <a href="/" class="block py-3 px-2 text-gray-600 font-medium hover:bg-white hover:text-secondary rounded">Home</a>
                <a href="/services" class="block py-3 px-2 text-gray-600 font-medium hover:bg-white hover:text-secondary rounded">Solutions</a>
                <a href="/contact" class="block py-3 px-2 text-gray-600 font-medium hover:bg-white hover:text-secondary rounded">Contact</a>
                
                <div class="border-t border-gray-200 my-2"></div>

                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard" class="block py-3 px-2 text-secondary font-bold hover:bg-white rounded">Dashboard</a>
                    <a href="/profile" class="block py-3 px-2 text-gray-600 hover:bg-white rounded">My Profile</a>
                    <a href="/logout.php" class="block py-3 px-2 text-accent-red font-bold hover:bg-red-50 rounded">Sign Out</a>
                <?php else: ?>
                    <a href="/login" class="block py-3 px-2 text-secondary font-bold hover:bg-white rounded">Log In</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Subheader (Dark Bar with Breadcrumb style) -->
    <div class="bg-primary text-white shadow-inner py-3 border-b border-gray-800">
        <div class="max-w-7xl mx-auto px-4 flex justify-between items-center">
            <div class="flex items-center gap-2 text-sm">
                <i class="fas fa-layer-group text-secondary opacity-80"></i>
                <span class="font-medium text-gray-400 uppercase tracking-wider text-xs">Section:</span>
                <span class="font-bold text-white"><?php echo htmlspecialchars($subheaderTitle); ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content Wrapper -->
    <!-- Content injected by router (index.php) goes here. Closed in footer.php -->
    <main class="flex-grow max-w-7xl mx-auto w-full px-4 py-8">
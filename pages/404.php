<?php
/**
 * 404 Error Page - pages/404.php
 *
 * This file is displayed whenever a user attempts to access a page
 * that is not found in the router's whitelist or does not exist on the server.
 * It provides a user-friendly message indicating the error using the Sentry OHS branding.
 *
 * Features:
 * - Responsive Tailwind CSS layout.
 * - Branded color scheme (Primary Blue / Safety Red).
 * - Clear navigation back to the homepage.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */
?>
<div class="flex flex-col items-center justify-center min-h-[60vh] py-12 text-center">
    
    <!-- Large Warning Icon -->
    <div class="mb-6">
        <i class="fas fa-exclamation-triangle text-6xl text-accent-red opacity-80"></i>
    </div>

    <!-- Error Code & Title -->
    <h2 class="text-6xl font-bold text-primary mb-2">404</h2>
    <h3 class="text-2xl font-semibold text-accent-gray mb-4">Page Not Found</h3>
    
    <!-- User-Friendly Description -->
    <p class="text-gray-500 mb-8 max-w-md mx-auto">
        Sorry, the page you are looking for could not be found. It might have been removed, renamed, or did not exist in the first place.
    </p>
    
    <!-- Return Action Button -->
    <!-- Uses global 'btn' and 'btn-primary' utility classes defined in header.php -->
    <a href="/" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all flex items-center justify-center">
        <i class="fas fa-arrow-left mr-2"></i> Return to Homepage
    </a>

</div>
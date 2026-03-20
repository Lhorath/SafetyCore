<?php
/**
 * About Page - pages/about.php
 *
 * This page displays the corporate mission, vision, and core values of 
 * the Sentry OHS platform. It serves to build trust and authority 
 * with potential clients and users.
 *
 * Features:
 * - Tailwind CSS layout matching the modern brand aesthetic.
 * - Responsive grid for core values.
 * - Context-aware Call to Action (CTA) based on login status.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */
?>

<div class="max-w-6xl mx-auto space-y-16">
    
    <!-- 
      1. Hero Section
      High-level overview of the platform's purpose.
    -->
    <div class="text-center py-10">
        <h2 class="text-4xl font-bold text-primary mb-6">About Sentry OHS</h2>
        <p class="text-xl text-gray-500 max-w-3xl mx-auto leading-relaxed">
            <strong class="text-secondary">Sentry OHS</strong> is occupational health and safety software built to help teams digitize hazard reporting, incident management, and compliance workflows.
        </p>
    </div>

    <!-- 
      2. Mission Statement
      Split layout with text on left, icon visual on right.
    -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 md:p-12 flex flex-col md:flex-row items-center gap-10">
        <div class="flex-1 order-2 md:order-1">
            <h3 class="text-2xl font-bold text-primary mb-4 border-b-4 border-secondary inline-block pb-1">Our Mission</h3>
            <p class="text-gray-600 leading-relaxed mb-4">
                We believe every worker should be able to report risks quickly and every leader should have clear visibility to act. Our mission is to make safety processes simple, consistent, and accountable across the organization.
            </p>
            <p class="text-gray-600 leading-relaxed">
                From FLHA completion and toolbox talks to incident follow-up and corrective actions, Sentry OHS connects field activity with management oversight in real time.
            </p>
        </div>
        <div class="w-full md:w-1/3 flex justify-center order-1 md:order-2">
             <div class="h-48 w-48 bg-blue-50 rounded-full flex items-center justify-center text-primary shadow-inner">
                <i class="fas fa-shield-alt text-7xl text-secondary"></i>
             </div>
        </div>
    </div>

    <!-- 
      3. Core Values Grid 
      Three-column layout highlighting key benefits.
    -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        
        <!-- Value 1 -->
        <div class="card text-center hover:shadow-lg transition duration-300 group">
            <div class="h-16 w-16 bg-red-50 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                <i class="fas fa-heartbeat text-3xl text-accent-red"></i>
            </div>
            <h4 class="font-bold text-lg mb-2 text-primary">Proactive Risk Control</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Shift from reactive response to preventive action with structured hazard reporting, trend visibility, and documented closeout workflows.
            </p>
        </div>

        <!-- Value 2 -->
        <div class="card text-center hover:shadow-lg transition duration-300 group">
            <div class="h-16 w-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                <i class="fas fa-users text-3xl text-secondary"></i>
            </div>
            <h4 class="font-bold text-lg mb-2 text-primary">Field-Ready Adoption</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Give supervisors and crews practical, mobile-friendly tools that fit daily operations and improve reporting participation.
            </p>
        </div>

        <!-- Value 3 -->
        <div class="card text-center hover:shadow-lg transition duration-300 group">
            <div class="h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                <i class="fas fa-check-double text-3xl text-primary"></i>
            </div>
            <h4 class="font-bold text-lg mb-2 text-primary">Audit-Ready Compliance</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Maintain complete, organized safety records that support inspections, audits, and internal compliance reviews.
            </p>
        </div>
    </div>

    <!-- 
      4. Call to Action 
      Only displayed if the user is NOT logged in.
    -->
    <?php if (!isset($_SESSION['user'])): ?>
    <div class="text-center pt-8 pb-4">
        <h3 class="text-2xl font-bold text-primary mb-4">Ready to strengthen your safety program?</h3>
        <div class="flex justify-center gap-4">
            <a href="/login" class="btn btn-primary shadow-lg px-8 py-3 text-lg">
                Access Portal
            </a>
            <a href="/contact" class="btn btn-secondary text-primary shadow-lg px-8 py-3 text-lg">
                Contact Sales <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php
/**
 * About Page - pages/about.php
 *
 * This page displays the corporate mission, vision, and core values of 
 * the NorthPoint 360 platform. It serves to build trust and authority 
 * with potential clients and users.
 *
 * Features:
 * - Tailwind CSS layout matching the modern brand aesthetic.
 * - Responsive grid for core values.
 * - Context-aware Call to Action (CTA) based on login status.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>

<div class="max-w-6xl mx-auto space-y-16">
    
    <!-- 
      1. Hero Section
      High-level overview of the platform's purpose.
    -->
    <div class="text-center py-10">
        <h2 class="text-4xl font-bold text-primary mb-6">Building Safer Workplaces</h2>
        <p class="text-xl text-gray-500 max-w-3xl mx-auto leading-relaxed">
            <strong class="text-secondary">NorthPoint 360</strong> is a comprehensive management ecosystem designed to streamline hazard reporting, compliance tracking, and safety communication across your entire organization.
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
                We believe that safety is a collective responsibility. Our goal is to remove the barriers between identifying a risk and resolving it. By providing intuitive tools for employees and powerful insights for management, we create a culture where safety comes first.
            </p>
            <p class="text-gray-600 leading-relaxed">
                From instant hazard reporting to real-time analytics, NorthPoint 360 bridges the gap between the shop floor and the boardroom, ensuring that every voice is heard and every risk is managed.
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
            <h4 class="font-bold text-lg mb-2 text-primary">Proactive Safety</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Moving from reactive fixes to proactive prevention through data-driven insights and ease of reporting.
            </p>
        </div>

        <!-- Value 2 -->
        <div class="card text-center hover:shadow-lg transition duration-300 group">
            <div class="h-16 w-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                <i class="fas fa-users text-3xl text-secondary"></i>
            </div>
            <h4 class="font-bold text-lg mb-2 text-primary">Employee First</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Empowering every team member to act as a safety officer with accessible, mobile-friendly tools.
            </p>
        </div>

        <!-- Value 3 -->
        <div class="card text-center hover:shadow-lg transition duration-300 group">
            <div class="h-16 w-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4 group-hover:scale-110 transition-transform">
                <i class="fas fa-check-double text-3xl text-primary"></i>
            </div>
            <h4 class="font-bold text-lg mb-2 text-primary">Total Compliance</h4>
            <p class="text-sm text-gray-500 leading-relaxed">
                Keeping your records organized, auditable, and up-to-date effortlessly to meet all regulatory standards.
            </p>
        </div>
    </div>

    <!-- 
      4. Call to Action 
      Only displayed if the user is NOT logged in.
    -->
    <?php if (!isset($_SESSION['user'])): ?>
    <div class="text-center pt-8 pb-4">
        <h3 class="text-2xl font-bold text-primary mb-4">Ready to elevate your safety standards?</h3>
        <div class="flex justify-center gap-4">
            <a href="/login" class="btn btn-primary shadow-lg px-8 py-3 text-lg">
                Client Login
            </a>
            <a href="/contact" class="btn btn-secondary text-primary shadow-lg px-8 py-3 text-lg">
                Contact Sales <i class="fas fa-arrow-right ml-2"></i>
            </a>
        </div>
    </div>
    <?php endif; ?>

</div>
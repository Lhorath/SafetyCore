<?php
/**
 * Public Home Page - pages/home.php
 *
 * This view serves as the public landing page for the NorthPoint 360 platform.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>

<!-- Hero Section -->
<div class="bg-primary text-white py-20 relative overflow-hidden rounded-b-xl shadow-lg mb-12">
    <!-- Decorative background element -->
    <div class="absolute top-0 right-0 w-[800px] h-[800px] bg-secondary rounded-full transform translate-x-1/3 -translate-y-1/2 opacity-10 pointer-events-none"></div>
    
    <div class="max-w-7xl mx-auto px-6 relative z-10 flex flex-col md:flex-row items-center">
        <!-- Hero Copy -->
        <div class="md:w-3/5 pr-0 md:pr-12 mb-10 md:mb-0 text-center md:text-left">
            <div class="inline-block bg-blue-900 text-blue-200 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider mb-4 border border-blue-700 shadow-sm">
                Now in Beta 05
            </div>
            <h1 class="text-4xl md:text-6xl font-extrabold leading-tight mb-6 tracking-tight">
                Next-Generation <br><span class="text-secondary">EHS Management</span>
            </h1>
            <p class="text-lg md:text-xl text-gray-300 mb-8 leading-relaxed max-w-2xl mx-auto md:mx-0">
                NorthPoint 360 is your central command for workplace safety. From proactive hazard reporting and daily field assessments to OSHA-compliant incident tracking and executive analytics.
            </p>
            <div class="flex flex-col sm:flex-row justify-center md:justify-start gap-4">
                <a href="/login" class="btn bg-secondary text-white hover:bg-blue-600 shadow-lg text-lg px-8 py-4 transform hover:-translate-y-0.5 transition-all">
                    Access Portal <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <a href="/services" class="btn bg-transparent text-white border-2 border-gray-600 hover:border-gray-400 hover:bg-gray-800 transition text-lg px-8 py-4">
                    Explore Solutions
                </a>
            </div>
        </div>
        
        <!-- Hero Graphic (Abstract Representation of a Dashboard) -->
        <div class="md:w-2/5 flex justify-center">
            <div class="bg-slate-800 p-6 rounded-2xl shadow-2xl border border-slate-700 w-full max-w-md transform rotate-2 hover:rotate-0 transition duration-500">
                <!-- Window Controls Mockup -->
                <div class="flex gap-2 mb-4">
                    <div class="w-3 h-3 rounded-full bg-red-500"></div>
                    <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                    <div class="w-3 h-3 rounded-full bg-green-500"></div>
                </div>
                <!-- Abstract Data Blocks -->
                <div class="space-y-4">
                    <div class="h-8 bg-slate-700 rounded w-3/4 animate-pulse"></div>
                    <div class="h-24 bg-slate-700 rounded w-full"></div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="h-20 bg-secondary rounded opacity-80"></div>
                        <div class="h-20 bg-accent-red rounded opacity-80"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Key Features Grid -->
<div class="max-w-7xl mx-auto px-6 py-12">
    <div class="text-center mb-16 max-w-3xl mx-auto">
        <h2 class="text-3xl font-bold text-primary mb-4 tracking-tight">A Complete Safety Ecosystem</h2>
        <p class="text-gray-600 text-lg leading-relaxed">Built for modern workforces, NorthPoint 360 seamlessly connects field workers with management, ensuring compliance and fostering a proactive safety culture.</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
        
        <!-- Feature 1: Hazard Reports -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-orange-500 shadow-md">
            <div class="bg-orange-50 w-14 h-14 rounded-xl flex items-center justify-center text-orange-500 text-2xl mb-6 shadow-sm">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Hazard Reporting</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Empower employees to instantly report hazards, near misses, and safety observations. Features include photo/video evidence, automated routing, and a complete resolution lifecycle.
            </p>
        </div>

        <!-- Feature 2: FLHA -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-green-500 shadow-md">
            <div class="bg-green-50 w-14 h-14 rounded-xl flex items-center justify-center text-green-600 text-2xl mb-6 shadow-sm">
                <i class="fas fa-clipboard-check"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Field Level Assessments</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Digital FLHA workflows designed for remote job sites. Track daily tasks, identify situational hazards, confirm PPE requirements, and mandate end-of-shift close-outs securely.
            </p>
        </div>

        <!-- Feature 3: Incident Management -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-accent-red shadow-md">
            <div class="bg-red-50 w-14 h-14 rounded-xl flex items-center justify-center text-accent-red text-2xl mb-6 shadow-sm">
                <i class="fas fa-file-medical-alt"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Incident Management</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Log injuries and property damage immediately. Allows management to classify incidents as OSHA/WCB Recordable or Lost-Time, generating a reliable audit trail for compliance.
            </p>
        </div>

        <!-- Feature 4: Safety Meetings -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-secondary shadow-md">
            <div class="bg-blue-50 w-14 h-14 rounded-xl flex items-center justify-center text-secondary text-2xl mb-6 shadow-sm">
                <i class="fas fa-users-class"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Safety Talks & Meetings</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Host toolbox talks and tailgate meetings dynamically. Select topics, record notes, and track individual employee attendance to ensure training compliance across branches.
            </p>
        </div>

        <!-- Feature 5: Advanced Analytics -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-indigo-500 shadow-md">
            <div class="bg-indigo-50 w-14 h-14 rounded-xl flex items-center justify-center text-indigo-600 text-2xl mb-6 shadow-sm">
                <i class="fas fa-chart-pie"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Executive Analytics</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Transform data into actionable insights. Real-time dashboards aggregate hazard trends, risk levels, top locations, and closure rates by specific store and month.
            </p>
        </div>

        <!-- Feature 6: Multi-Tenant Architecture -->
        <div class="card hover:-translate-y-1 transition duration-300 border-t-4 border-t-slate-800 shadow-md">
            <div class="bg-slate-100 w-14 h-14 rounded-xl flex items-center justify-center text-slate-800 text-2xl mb-6 shadow-sm">
                <i class="fas fa-network-wired"></i>
            </div>
            <h3 class="text-xl font-bold text-primary mb-3">Multi-Branch Scaling</h3>
            <p class="text-gray-600 text-sm leading-relaxed">
                Built on a secure, multi-tenant database. Manage multiple store locations, assign floating employees across branches, and utilize granular Role-Based Access Control (RBAC).
            </p>
        </div>

    </div>
</div>

<!-- CTA Section -->
<div class="bg-gray-50 border-t border-gray-200 py-16 mt-12 rounded-xl shadow-inner mx-4 lg:mx-auto max-w-7xl mb-8">
    <div class="max-w-4xl mx-auto px-6 text-center">
        <h2 class="text-3xl font-bold text-primary mb-6 tracking-tight">Ready to elevate your safety standards?</h2>
        <p class="text-gray-600 mb-8 text-lg">Join the platform that is redefining how companies protect their people, properties, and compliance ratings.</p>
        <a href="/contact" class="btn btn-primary text-lg px-10 py-4 shadow-xl transform hover:-translate-y-0.5 transition-all">Contact Our Team</a>
    </div>
</div>
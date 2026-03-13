<?php
/**
 * Public Solutions/Services Page - pages/services.php
 *
 * Details the specific modules available in NorthPoint 360 and
 * outlines the development roadmap.
 *
 * Updates in Beta 05:
 * - Added comprehensive sections for the new FLHA, Incidents, and Meetings modules.
 * - Updated the "Development Roadmap" to mark Beta 05 milestones as complete.
 * - Outlined the upcoming features targeted for the V1.0 release.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   5.0.0 (NorthPoint Beta 05)
 */
?>

<!-- Header -->
<div class="bg-primary text-white py-16 border-b-4 border-secondary">
    <div class="max-w-7xl mx-auto px-6 text-center">
        <h1 class="text-4xl md:text-5xl font-extrabold mb-4 tracking-tight">Platform Solutions</h1>
        <p class="text-lg text-gray-300 max-w-2xl mx-auto leading-relaxed">Explore the modules that make NorthPoint 360 the ultimate Environment, Health, and Safety (EHS) platform.</p>
    </div>
</div>

<div class="max-w-7xl mx-auto px-6 py-16 space-y-24">

    <!-- Module 1: Hazard Lifecycle -->
    <div class="flex flex-col md:flex-row items-center gap-12">
        <div class="md:w-1/2">
            <div class="text-orange-500 text-5xl mb-6 shadow-sm inline-block bg-orange-50 p-4 rounded-2xl"><i class="fas fa-shield-alt"></i></div>
            <h2 class="text-3xl font-bold text-primary mb-4 tracking-tight">Proactive Hazard Lifecycle</h2>
            <p class="text-gray-600 mb-6 text-lg leading-relaxed">
                Safety starts with observation. Our core hazard reporting module allows any employee to quickly document unsafe conditions, near misses, or active risks using a simplified, mobile-friendly interface.
            </p>
            <ul class="space-y-3 text-base font-medium text-gray-700">
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Attach Photo & Video evidence directly from mobile devices.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Assign precise risk levels and custom store locations.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Track equipment lockout and tagout statuses securely.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Managers log resolution details for a complete, closed-loop audit trail.</li>
            </ul>
        </div>
        <div class="md:w-1/2 bg-gray-50 rounded-2xl p-8 border border-gray-200 shadow-inner flex items-center justify-center min-h-[300px]">
            <!-- Abstract UI Representation -->
            <div class="w-full max-w-sm bg-white rounded-xl shadow-md border border-gray-100 p-4 space-y-3">
                <div class="flex justify-between items-center mb-2">
                    <div class="h-4 bg-gray-200 rounded w-1/3"></div>
                    <div class="h-4 bg-orange-200 rounded w-1/4"></div>
                </div>
                <div class="h-20 bg-gray-100 rounded w-full"></div>
                <div class="flex gap-2">
                    <div class="h-8 bg-secondary rounded w-1/2 opacity-80"></div>
                    <div class="h-8 bg-gray-200 rounded w-1/2"></div>
                </div>
            </div>
        </div>
    </div>

    <hr class="border-gray-200">

    <!-- Module 2: FLHA -->
    <div class="flex flex-col md:flex-row-reverse items-center gap-12">
        <div class="md:w-1/2">
            <div class="text-green-600 text-5xl mb-6 shadow-sm inline-block bg-green-50 p-4 rounded-2xl"><i class="fas fa-clipboard-list"></i></div>
            <h2 class="text-3xl font-bold text-primary mb-4 tracking-tight">Field Level Hazard Assessments (FLHA)</h2>
            <p class="text-gray-600 mb-6 text-lg leading-relaxed">
                Designed specifically for construction and remote job sites, the FLHA module ensures crews systematically assess their environment before work begins. It replaces fragile paper forms with a robust digital wizard.
            </p>
            <ul class="space-y-3 text-base font-medium text-gray-700">
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Multi-step wizard: Identify hazards, define tasks, and confirm PPE.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Dynamically add daily task steps and specific mitigation plans.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Record "Working Alone" procedures and assign co-workers dynamically.</li>
                <li class="flex items-start"><i class="fas fa-check text-green-500 mt-1 mr-3"></i> Mandatory End-of-Shift close-outs for permit and incident tracking.</li>
            </ul>
        </div>
        <div class="md:w-1/2 bg-gray-50 rounded-2xl p-8 border border-gray-200 shadow-inner flex items-center justify-center min-h-[300px]">
             <!-- Abstract UI Representation -->
            <div class="w-full max-w-sm space-y-3">
                <div class="h-4 bg-green-200 rounded w-1/4 mb-4"></div>
                <div class="h-12 bg-white border border-gray-200 shadow-sm rounded-lg w-full flex items-center px-4"><div class="w-4 h-4 rounded-full border-2 border-green-500 mr-3"></div><div class="h-3 bg-gray-200 rounded w-1/2"></div></div>
                <div class="h-12 bg-white border border-gray-200 shadow-sm rounded-lg w-full flex items-center px-4"><div class="w-4 h-4 rounded-full border-2 border-gray-300 mr-3"></div><div class="h-3 bg-gray-200 rounded w-2/3"></div></div>
                <div class="h-12 bg-white border border-gray-200 shadow-sm rounded-lg w-3/4 flex items-center px-4"><div class="w-4 h-4 rounded-full border-2 border-gray-300 mr-3"></div><div class="h-3 bg-gray-200 rounded w-1/3"></div></div>
            </div>
        </div>
    </div>

    <hr class="border-gray-200">

    <!-- Module 3: Incidents & Meetings (Grid) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
        <!-- Incident Management -->
        <div class="card shadow-md hover:shadow-lg transition duration-300 border-t-4 border-t-accent-red p-8">
            <div class="text-accent-red text-4xl mb-6 bg-red-50 w-16 h-16 rounded-xl flex items-center justify-center"><i class="fas fa-ambulance"></i></div>
            <h3 class="text-2xl font-bold text-primary mb-3">Incident Management</h3>
            <p class="text-gray-600 text-base mb-6 leading-relaxed">
                When accidents happen, rapid documentation is legally required. Our incident module handles employee injuries, customer incidents, and property damage seamlessly.
            </p>
            <p class="text-sm font-bold text-red-800 bg-red-50 p-4 rounded-lg border border-red-100 shadow-sm">
                Management can securely classify reports as OSHA/WCB Recordable or Lost-Time incidents to ensure regulatory reporting accuracy.
            </p>
        </div>
        
        <!-- Safety Meetings -->
        <div class="card shadow-md hover:shadow-lg transition duration-300 border-t-4 border-t-secondary p-8">
            <div class="text-secondary text-4xl mb-6 bg-blue-50 w-16 h-16 rounded-xl flex items-center justify-center"><i class="fas fa-users-class"></i></div>
            <h3 class="text-2xl font-bold text-primary mb-3">Safety Meetings & Talks</h3>
            <p class="text-gray-600 text-base mb-6 leading-relaxed">
                A proactive safety culture requires constant communication. Host digital Toolbox Talks or Tailgate Meetings from any branch or job site.
            </p>
            <p class="text-sm font-bold text-blue-800 bg-blue-50 p-4 rounded-lg border border-blue-100 shadow-sm">
                Select topics, document discussion notes, and dynamically check off employee attendance to maintain rigorous compliance training records.
            </p>
        </div>
    </div>

    <hr class="border-gray-200">

    <!-- Roadmap Section -->
    <div class="bg-primary rounded-3xl p-8 md:p-14 text-white shadow-2xl relative overflow-hidden mb-8">
        <!-- Decorative Elements -->
        <div class="absolute top-0 right-0 w-[500px] h-[500px] bg-slate-800 rounded-full transform translate-x-1/2 -translate-y-1/2 opacity-50 pointer-events-none"></div>
        <div class="absolute bottom-0 left-0 w-64 h-64 bg-secondary rounded-full transform -translate-x-1/2 translate-y-1/2 opacity-20 pointer-events-none"></div>
        
        <h2 class="text-3xl md:text-4xl font-extrabold mb-10 relative z-10 tracking-tight">Development Roadmap</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-16 relative z-10">
            
            <!-- Currently Implemented (Beta 05) -->
            <div class="bg-slate-800/50 p-8 rounded-2xl border border-slate-700 backdrop-blur-sm">
                <h3 class="text-xl font-bold text-secondary mb-6 flex items-center border-b border-slate-600 pb-3">
                    <i class="fas fa-check-circle mr-3 text-2xl"></i> Current Capabilities (Beta 05)
                </h3>
                <ul class="space-y-4 text-sm md:text-base text-gray-300">
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Multi-Tenant Architecture:</strong> Branch segregation and global Role-Based Access Control (RBAC).</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Hazard Lifecycle:</strong> Full CRUD operations, from open reporting to management resolution.</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Digital FLHAs:</strong> Comprehensive remote site assessments and shift close-outs.</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Incident Classification:</strong> Dedicated lost-time and recordable compliance tracking.</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Meeting Attendance:</strong> Digital toolbox talk logging and attendee verification.</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Executive Metrics:</strong> Dynamic visual dashboards and cross-branch KPI aggregation.</span></li>
                    <li class="flex items-start"><i class="fas fa-check text-secondary mt-1 mr-3"></i> <span><strong>Dynamic SEO:</strong> Database-driven meta tags and OpenGraph rendering engine.</span></li>
                </ul>
            </div>

            <!-- Coming Soon (V1.0) -->
            <div class="bg-slate-800/50 p-8 rounded-2xl border border-slate-700 backdrop-blur-sm">
                <h3 class="text-xl font-bold text-orange-400 mb-6 flex items-center border-b border-slate-600 pb-3">
                    <i class="fas fa-rocket mr-3 text-2xl"></i> Coming Soon (V1.0 Release)
                </h3>
                <ul class="space-y-4 text-sm md:text-base text-gray-300">
                    <li class="flex items-start"><i class="fas fa-circle text-orange-400/50 mt-1.5 mr-3 text-[10px]"></i> <span><strong>Automated Notifications:</strong> Email and SMS alerts for critical incidents and overdue FLHA close-outs.</span></li>
                    <li class="flex items-start"><i class="fas fa-circle text-orange-400/50 mt-1.5 mr-3 text-[10px]"></i> <span><strong>Export & Reporting:</strong> Generate formal, branded PDF reports for external audits and WCB claims.</span></li>
                    <li class="flex items-start"><i class="fas fa-circle text-orange-400/50 mt-1.5 mr-3 text-[10px]"></i> <span><strong>Equipment Management:</strong> Digital maintenance logs and mandatory pre-use equipment inspections.</span></li>
                    <li class="flex items-start"><i class="fas fa-circle text-orange-400/50 mt-1.5 mr-3 text-[10px]"></i> <span><strong>Training Matrix:</strong> Track individual employee certifications, expiry dates, and required retraining intervals.</span></li>
                    <li class="flex items-start"><i class="fas fa-circle text-orange-400/50 mt-1.5 mr-3 text-[10px]"></i> <span><strong>Progressive Web App (PWA):</strong> True offline mode capabilities with background syncing for extremely remote job sites.</span></li>
                </ul>
            </div>
            
        </div>
    </div>

</div>
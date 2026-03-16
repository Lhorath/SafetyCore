<?php
/**
 * Public Landing Page - pages/home.php
 *
 * The main public-facing landing page for NorthPoint 360.
 * Features a modern full-width hero section and highlights core EHS modules.
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>

<!-- ==========================================
     STATIC HERO SECTION (Beta 10)
     ========================================== -->
<div class="relative w-full bg-slate-900 overflow-hidden py-24 lg:py-36 border-b border-slate-800">
    
    <!-- Background Gradient & Subtle Dot Pattern (No external image dependencies) -->
    <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_right,_var(--tw-gradient-stops))] from-blue-900/40 via-slate-900 to-black z-0"></div>
    <div class="absolute inset-0 bg-[url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+PGNpcmNsZSBjeD0iMSIgY3k9IjEiIHI9IjEiIGZpbGw9InJnYmEoMjU1LCAyNTUsIDI1NSwgMC4wNSkiLz48L3N2Zz4=')] z-0"></div>
    
    <!-- Content -->
    <div class="relative z-10 text-center px-4 max-w-5xl mx-auto">
        
        <span class="inline-block py-1.5 px-4 rounded-full bg-blue-600/20 text-blue-400 text-xs md:text-sm font-bold tracking-widest uppercase mb-6 border border-blue-500/30 backdrop-blur-md shadow-lg">
            <i class="fas fa-rocket mr-2"></i> Now in Beta 10
        </span>
        
        <h1 class="text-5xl md:text-6xl lg:text-8xl font-extrabold text-white tracking-tight mb-6 drop-shadow-lg">
            NorthPoint <span class="text-blue-500">360</span>
        </h1>
        
        <p class="text-lg md:text-2xl text-gray-300 mb-10 font-light max-w-3xl mx-auto leading-relaxed">
            The ultimate enterprise Environment, Health, and Safety (EHS) management platform built for the modern workforce.
        </p>
        
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            <a href="/login" class="inline-flex justify-center items-center bg-blue-600 hover:bg-blue-500 text-white font-bold py-3.5 px-8 rounded-lg text-lg transition-all transform hover:-translate-y-1 shadow-[0_4px_14px_0_rgba(37,99,235,0.39)] w-full sm:w-auto">
                Access Portal <i class="fas fa-arrow-right ml-2"></i>
            </a>
            <a href="/services" class="inline-flex justify-center items-center bg-slate-800/50 hover:bg-slate-700 border border-slate-600 hover:border-slate-500 text-white font-bold py-3.5 px-8 rounded-lg text-lg transition-all transform hover:-translate-y-1 backdrop-blur-sm w-full sm:w-auto">
                Explore Solutions
            </a>
        </div>
        
    </div>
</div>

<!-- ==========================================
     PLATFORM FEATURES SECTION
     ========================================== -->
<div class="bg-gray-50 py-20">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="text-center max-w-3xl mx-auto mb-16">
            <h2 class="text-sm font-bold text-blue-600 tracking-widest uppercase mb-2">Complete EHS Solution</h2>
            <h3 class="text-3xl md:text-4xl font-extrabold text-slate-800 tracking-tight">Everything you need to build a proactive safety culture.</h3>
            <p class="mt-4 text-lg text-gray-500">NorthPoint 360 eliminates paper trails, bridging the gap between field operators and management in real-time.</p>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            
            <!-- Feature 1 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-red-50 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-exclamation-triangle text-2xl text-accent-red"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Hazard & Incident Logging</h4>
                <p class="text-gray-500 leading-relaxed">Report unsafe conditions, near misses, and actual incidents instantly. Track resolutions through a unified management dashboard.</p>
            </div>

            <!-- Feature 2 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-blue-50 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-clipboard-list text-2xl text-blue-600"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Digital FLHAs</h4>
                <p class="text-gray-500 leading-relaxed">Empower workers with an intuitive Field Level Hazard Assessment wizard. Identify daily job steps and mitigate risks before work begins.</p>
            </div>

            <!-- Feature 3 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-orange-50 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-truck-pickup text-2xl text-orange-500"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Equipment Management</h4>
                <p class="text-gray-500 leading-relaxed">Track asset inventory, build dynamic pre-shift inspection checklists, and automatically remove failed equipment from service.</p>
            </div>

            <!-- Feature 4 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-purple-50 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-certificate text-2xl text-purple-600"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Training Matrix</h4>
                <p class="text-gray-500 leading-relaxed">Ensure compliance by tracking employee certifications. Easily monitor validity periods and upcoming expiration dates at a glance.</p>
            </div>

            <!-- Feature 5 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-teal-50 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-users-class text-2xl text-teal-600"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Safety Meetings</h4>
                <p class="text-gray-500 leading-relaxed">Host digital toolbox talks. Document topics discussed and seamlessly record verified employee attendance logs.</p>
            </div>

            <!-- Feature 6 -->
            <div class="bg-white p-8 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 group">
                <div class="w-14 h-14 bg-slate-100 rounded-xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform">
                    <i class="fas fa-shield-alt text-2xl text-slate-700"></i>
                </div>
                <h4 class="text-xl font-bold text-slate-800 mb-3">Enterprise Security</h4>
                <p class="text-gray-500 leading-relaxed">Built with robust RBAC (Role-Based Access Control) and multi-tenant architecture, ensuring your company data remains isolated and secure.</p>
            </div>

        </div>
    </div>
</div>

<!-- ==========================================
     CALL TO ACTION SECTION
     ========================================== -->
<div class="bg-primary py-16 relative overflow-hidden">
    <div class="absolute inset-0 opacity-10 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-blue-400 via-transparent to-transparent"></div>
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center relative z-10">
        <h2 class="text-3xl font-extrabold text-white mb-6 tracking-tight">Ready to elevate your safety standards?</h2>
        <p class="text-lg text-blue-100 mb-8 font-light">Join the NorthPoint 360 Beta 10 testing phase and experience the future of digital compliance.</p>
        <a href="/login" class="inline-block bg-white text-primary font-bold py-3 px-10 rounded-lg text-lg hover:bg-gray-100 transition-colors shadow-lg">
            Login to Dashboard
        </a>
    </div>
</div>
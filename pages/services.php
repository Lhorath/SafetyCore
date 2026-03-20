<?php
/**
 * Solutions / Services Page - pages/services.php
 *
 * Details the core modules and capabilities of the Sentry OHS platform.
 *
 * @package   Sentry OHS
 * @version   Version 11.0.0 (sentry ohs launch)
 */
?>

<!-- ==========================================
     PAGE HEADER
     ========================================== -->
<div class="relative bg-slate-900 py-24 border-b border-slate-800 overflow-hidden">
    <!-- Subtle Background Elements -->
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,_var(--tw-gradient-stops))] from-blue-900/20 via-transparent to-transparent z-0"></div>
    <div class="absolute right-0 bottom-0 opacity-10 transform translate-x-1/4 translate-y-1/4">
        <i class="fas fa-shield-alt text-[250px] text-white"></i>
    </div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <span class="inline-block py-1 px-3 rounded-full bg-blue-600/20 text-blue-400 text-sm font-bold tracking-widest uppercase mb-4 border border-blue-500/30">
            Platform Capabilities
        </span>
        <h1 class="text-4xl md:text-5xl lg:text-6xl font-extrabold text-white tracking-tight mb-6">
            Occupational Health and Safety <span class="text-blue-500">Solutions</span>
        </h1>
        <p class="text-lg md:text-xl text-gray-300 font-light max-w-3xl mx-auto leading-relaxed">
            Sentry OHS provides digital OHS software for hazard reporting, incident management, FLHA workflows, safety meetings, training records, and compliance tracking.
        </p>
    </div>
</div>

<!-- ==========================================
     CORE MODULES SECTION
     ========================================== -->
<div class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="mb-16 text-center">
            <h2 class="text-3xl font-extrabold text-slate-800 tracking-tight">Core OHS Modules</h2>
            <div class="w-24 h-1 bg-blue-500 mx-auto mt-4 rounded-full"></div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-10">
            
            <!-- Digital FLHA -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-blue-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-blue-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-blue-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-clipboard-list text-2xl text-blue-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Field Level Hazard Assessments</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Digitize daily FLHAs with guided job steps, hazard identification, and required controls so work starts with clear risk mitigation.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-blue-500 mr-2"></i> Custom job steps & hazard tagging</li>
                    <li><i class="fas fa-check text-blue-500 mr-2"></i> Supervisor review workflows</li>
                    <li><i class="fas fa-check text-blue-500 mr-2"></i> End-of-shift closeouts</li>
                </ul>
            </div>

            <!-- Dynamic Equipment Hub -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-emerald-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-emerald-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-emerald-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-truck-pickup text-2xl text-emerald-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Equipment Management</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Maintain a real-time asset inventory and standardize pre-shift inspections with configurable checklists and documented outcomes.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Custom Checklist Builder</li>
                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Daily Pre-Shift Logs</li>
                    <li><i class="fas fa-check text-emerald-500 mr-2"></i> Auto-Out-Of-Service on failure</li>
                </ul>
            </div>

            <!-- Training Matrix -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-purple-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-purple-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-purple-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-certificate text-2xl text-purple-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Training Matrix</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Track certifications and training records by worker and role, monitor expiries, and keep teams qualified for regulated tasks.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-purple-500 mr-2"></i> Expiry date tracking</li>
                    <li><i class="fas fa-check text-purple-500 mr-2"></i> Custom training categories</li>
                    <li><i class="fas fa-check text-purple-500 mr-2"></i> Visual compliance grid</li>
                </ul>
            </div>

            <!-- Hazard & Near Miss Reporting -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-orange-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-orange-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-orange-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-exclamation-triangle text-2xl text-orange-500"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Hazard Reporting</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Enable workers to report unsafe conditions and near misses from the field with structured forms and evidence attachments.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-orange-500 mr-2"></i> Photo & video evidence uploads</li>
                    <li><i class="fas fa-check text-orange-500 mr-2"></i> Risk matrix categorization</li>
                    <li><i class="fas fa-check text-orange-500 mr-2"></i> Manager review workflows</li>
                </ul>
            </div>

            <!-- Incident Management -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-red-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-red-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-red-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-ambulance text-2xl text-red-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Incident Management</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Capture injuries, property damage, and first-aid events with consistent documentation that supports investigations and reporting.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-red-500 mr-2"></i> OSHA/WCB compliance tracking</li>
                    <li><i class="fas fa-check text-red-500 mr-2"></i> Body-part injury mapping</li>
                    <li><i class="fas fa-check text-red-500 mr-2"></i> Immediate notification triggers</li>
                </ul>
            </div>

            <!-- Safety Meetings -->
            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-100 hover:shadow-xl hover:border-teal-200 transition-all duration-300 relative overflow-hidden group">
                <div class="absolute top-0 right-0 w-24 h-24 bg-teal-100 rounded-bl-full -z-10 transition-transform group-hover:scale-150 opacity-50"></div>
                <div class="w-14 h-14 bg-white shadow-sm border border-teal-100 rounded-xl flex items-center justify-center mb-6 group-hover:-translate-y-1 transition-transform">
                    <i class="fas fa-users-class text-2xl text-teal-600"></i>
                </div>
                <h3 class="text-xl font-bold text-slate-800 mb-3">Toolbox Talks & Meetings</h3>
                <p class="text-gray-600 leading-relaxed mb-4">
                    Run toolbox talks digitally, capture attendance, and maintain searchable records for internal reviews and external audits.
                </p>
                <ul class="text-sm text-gray-500 space-y-2 font-medium">
                    <li><i class="fas fa-check text-teal-500 mr-2"></i> Digital sign-in sheets</li>
                    <li><i class="fas fa-check text-teal-500 mr-2"></i> Centralized meeting archive</li>
                    <li><i class="fas fa-check text-teal-500 mr-2"></i> Attach presentation materials</li>
                </ul>
            </div>

        </div>
    </div>
</div>

<!-- ==========================================
     WHY CHOOSE US / STATS SECTION
     ========================================== -->
<div class="bg-slate-800 py-16">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8 text-center divide-y md:divide-y-0 md:divide-x divide-slate-700">
            
            <div class="py-4 md:py-0">
                <div class="text-blue-400 mb-3"><i class="fas fa-leaf text-4xl"></i></div>
                <h4 class="text-white font-bold text-xl mb-2">Digital-First Workflows</h4>
                <p class="text-slate-400 text-sm max-w-xs mx-auto">Replace manual forms with standardized digital processes, faster submissions, and complete record visibility.</p>
            </div>
            
            <div class="py-4 md:py-0">
                <div class="text-blue-400 mb-3"><i class="fas fa-bolt text-4xl"></i></div>
                <h4 class="text-white font-bold text-xl mb-2">Real-Time Visibility</h4>
                <p class="text-slate-400 text-sm max-w-xs mx-auto">Give supervisors immediate insight when FLHAs, hazards, or incidents are submitted from the field.</p>
            </div>
            
            <div class="py-4 md:py-0">
                <div class="text-blue-400 mb-3"><i class="fas fa-shield-alt text-4xl"></i></div>
                <h4 class="text-white font-bold text-xl mb-2">Compliance Ready</h4>
                <p class="text-slate-400 text-sm max-w-xs mx-auto">Keep complete, searchable records that support inspections, audits, and internal compliance reviews.</p>
            </div>

        </div>
    </div>
</div>

<!-- ==========================================
     CALL TO ACTION
     ========================================== -->
<div class="bg-gray-50 py-20 text-center">
    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
        <i class="fas fa-hard-hat text-5xl text-blue-300 mb-6 block"></i>
        <h2 class="text-3xl md:text-4xl font-extrabold text-slate-800 mb-4 tracking-tight">Build a stronger safety culture with better data.</h2>
        <p class="text-lg text-gray-500 mb-8">
            Centralize OHS workflows, improve accountability, and give leadership the visibility needed to reduce risk and improve performance.
        </p>
        <div class="flex flex-col sm:flex-row justify-center items-center gap-4">
            <a href="/login" class="inline-flex justify-center items-center bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 px-8 rounded-lg text-lg transition-all shadow-md w-full sm:w-auto">
                Go to Dashboard
            </a>
            <a href="/contact" class="inline-flex justify-center items-center bg-white border border-gray-300 hover:bg-gray-50 text-slate-700 font-bold py-3 px-8 rounded-lg text-lg transition-all shadow-sm w-full sm:w-auto">
                Contact Sales
            </a>
        </div>
    </div>
</div>
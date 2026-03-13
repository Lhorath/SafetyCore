<?php
/**
 * Footer Template - includes/footer.php
 *
 * This file contains the closing HTML structure for every page.
 * It includes the main footer (styled with Tailwind CSS) and all the
 * client-side JavaScript logic for the application's dynamic features.
 *
 * FEATURES:
 * - Responsive Tailwind Footer with Brand, Links, and Contact info.
 * - Modular JavaScript architecture to prevent page conflicts.
 * - Logic for Hazard Reporting (Dependent Dropdowns, Modals, Validation).
 * - Logic for Dashboard Filtering and Statistics.
 * - Logic for Master-Detail Report Viewing & Status Management (Editing/Closing).
 * - Logic for Advanced Statistics & Metrics Dashboard (Beta 04).
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   4.4.0 (NorthPoint Beta 04)
 */
?>
    </main> <!-- End of Main Content Wrapper (Opened in header.php) -->

    <!-- Footer (Styled with Tailwind CSS) -->
    <footer class="bg-primary text-white mt-auto border-t-4 border-secondary">
        <!-- Expanded to max-w-7xl in Beta 04 to match the new global layout width -->
        <div class="max-w-7xl mx-auto px-6 py-12">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                
                <!-- Column 1: Brand & About -->
                <div class="space-y-4">
                    <div class="bg-white p-2 rounded w-fit">
                        <img src="style/images/logo.png" alt="NorthPoint 360 Logo" class="h-10 opacity-90">
                    </div>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        <strong class="text-white">NorthPoint 360</strong> is your central command for workplace safety compliance, reporting, and operational excellence.
                    </p>
                </div>

                <!-- Column 2: Quick Links -->
                <div>
                    <h3 class="text-lg font-bold text-secondary mb-4 border-b border-gray-700 pb-2 inline-block">Quick Links</h3>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="/" class="hover:text-secondary transition flex items-center"><i class="fas fa-home w-5 mr-2"></i> Home</a></li>
                        <li><a href="/services" class="hover:text-secondary transition flex items-center"><i class="fas fa-layer-group w-5 mr-2"></i> Solutions</a></li>
                        <li><a href="/dashboard" class="hover:text-secondary transition flex items-center"><i class="fas fa-tachometer-alt w-5 mr-2"></i> Dashboard</a></li>
                        <li><a href="/login" class="hover:text-secondary transition flex items-center"><i class="fas fa-sign-in-alt w-5 mr-2"></i> Log In</a></li>
                    </ul>
                </div>

                <!-- Column 3: Contact Info -->
                <div>
                    <h3 class="text-lg font-bold text-secondary mb-4 border-b border-gray-700 pb-2 inline-block">Contact</h3>
                    <ul class="space-y-3 text-sm text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-envelope w-5 mt-1 text-secondary mr-2"></i> 
                            <span>support@macweb.ca</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-phone w-5 mt-1 text-secondary mr-2"></i> 
                            <span>(902) 754 1070</span>
                        </li>
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt w-5 mt-1 text-secondary mr-2"></i> 
                            <span>Moncton, NB, Canada</span>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Copyright Bar -->
            <div class="border-t border-gray-800 mt-10 pt-6 text-center text-gray-500 text-xs">
                &copy; <span id="currentYearFooter"></span> MacWeb.ca. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Application JavaScript -->
    <script>
        // Global Modal Logic for "Close Report" functionality (used in store-reports)
        window.openCloseModal = function(reportId) {
            const modal = document.getElementById('closeReportModal');
            if(modal) {
                document.getElementById('closeReportId').value = reportId;
                document.getElementById('resolutionComments').value = '';
                modal.classList.remove('hidden');
            }
        };

        document.addEventListener('DOMContentLoaded', function() {
            
            // ============================================================
            // 1. GLOBAL LOGIC
            // ============================================================
            
            // Set Copyright Year
            const yearSpan = document.getElementById('currentYearFooter');
            if (yearSpan) yearSpan.textContent = new Date().getFullYear();

            // Mobile Menu Toggle (for Header)
            const mobileBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');
            if (mobileBtn && mobileMenu) {
                mobileBtn.addEventListener('click', () => mobileMenu.classList.toggle('hidden'));
            }

            // --- Modal Close Logic ---
            const closeReportModal = document.getElementById('closeReportModal');
            if (closeReportModal) {
                document.querySelectorAll('.close-modal-btn').forEach(btn => {
                    btn.addEventListener('click', () => closeReportModal.classList.add('hidden'));
                });
                
                // Confirm Close Action
                document.getElementById('confirmCloseBtn').addEventListener('click', () => {
                    const reportId = document.getElementById('closeReportId').value;
                    const comments = document.getElementById('resolutionComments').value.trim();
                    if(!comments) { alert('Please enter resolution actions taken.'); return; }
                    
                    fetch('/api/hazard_reporting.php?action=close_report', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ report_id: reportId, resolution_comments: comments })
                    }).then(r=>r.json()).then(res => {
                        if(res.success) {
                            closeReportModal.classList.add('hidden');
                            
                            // Reload the Store Report list to reflect the new 'Closed' status
                            const storeSelector = document.getElementById('storeSelector');
                            if (storeSelector) storeSelector.dispatchEvent(new Event('change'));

                            // Briefly show success before list refreshes UI
                            const viewer = document.getElementById('reportViewer');
                            if(viewer) viewer.innerHTML = `<div class="p-8 text-center text-green-500 font-bold"><i class="fas fa-check-circle text-4xl mb-3"></i><br>Report Successfully Closed. Reloading data...</div>`;
                        } else {
                            alert('Error: ' + res.message);
                        }
                    });
                });
            }

            // ============================================================
            // 2. HAZARD REPORT FORM LOGIC
            // ============================================================
            const hazardFormStoreSelect = document.getElementById('storeSelect');
            if (hazardFormStoreSelect) {
                const reportedBySelect = document.getElementById('reportedBySelect');
                const hazardLocationSelect = document.getElementById('hazardLocationSelect');
                const addNewLocationBtn = document.getElementById('addNewLocationBtn');
                const whoNotifiedSelect = document.getElementById('whoNotifiedSelect');
                
                const today = new Date();
                const repDate = document.getElementById('reporterDate');
                if(repDate) {
                    const yyyy = today.getFullYear();
                    const mm = String(today.getMonth() + 1).padStart(2, '0');
                    const dd = String(today.getDate()).padStart(2, '0');
                    const HH = String(today.getHours()).padStart(2, '0');
                    const MM = String(today.getMinutes()).padStart(2, '0');
                    repDate.value = `${yyyy}-${mm}-${dd}`;
                    document.getElementById('hazardDate').value = `${yyyy}-${mm}-${dd}`;
                    document.getElementById('reporterTime').value = `${HH}:${MM}`;
                    document.getElementById('hazardTime').value = `${HH}:${MM}`;
                }

                hazardFormStoreSelect.addEventListener('change', function() {
                    const storeId = this.value;
                    if (reportedBySelect && reportedBySelect.tagName === 'SELECT') resetDropdown(reportedBySelect, 'Select a store first');
                    resetDropdown(hazardLocationSelect, 'Select a store first');
                    resetDropdown(whoNotifiedSelect, 'Select a store first');

                    if (storeId) {
                        if (reportedBySelect && reportedBySelect.tagName === 'SELECT') fetchData('get_employees', storeId, reportedBySelect, 'Employee');
                        fetchData('get_locations', storeId, hazardLocationSelect, 'Location');
                        fetchData('get_supervisors', storeId, whoNotifiedSelect, 'Supervisor');
                        addNewLocationBtn.disabled = false;
                        addNewLocationBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                        addNewLocationBtn.classList.add('bg-gray-600', 'hover:bg-gray-700');
                    } else {
                        addNewLocationBtn.disabled = true;
                        addNewLocationBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                        addNewLocationBtn.classList.remove('bg-gray-600', 'hover:bg-gray-700');
                    }
                });

                document.querySelectorAll('input[name="immediateActionTaken"]').forEach(radio => {
                    radio.addEventListener('change', function() { document.getElementById('actionDescriptionLabel').textContent = (this.value === 'yes') ? 'Describe the action(s) taken:' : 'If No, why not?'; });
                });

                document.querySelectorAll('input[name="equipmentLockedOut"]').forEach(radio => {
                    radio.addEventListener('change', function() {
                        const keyHolderGroup = document.getElementById('keyHolderGroup');
                        const keyHolderInput = document.getElementById('keyHolderName');
                        if (this.value === 'yes') { keyHolderGroup.style.display = 'block'; keyHolderInput.required = true; } 
                        else { keyHolderGroup.style.display = 'none'; keyHolderInput.required = false; keyHolderInput.value = ''; }
                    });
                });

                const modal = document.getElementById('addLocationModal');
                if (modal) {
                    const closeBtn = document.querySelector('.modal-close-btn');
                    document.getElementById('addNewLocationBtn').addEventListener('click', () => modal.classList.remove('hidden'));
                    if (closeBtn) closeBtn.addEventListener('click', () => modal.classList.add('hidden'));
                    window.addEventListener('click', (event) => { if (event.target == modal) modal.classList.add('hidden'); });
                    
                    document.getElementById('saveNewLocationBtn').addEventListener('click', () => {
                        const locationName = document.getElementById('newLocationName').value.trim();
                        const sId = hazardFormStoreSelect.value;
                        if (!locationName || !sId) { alert('Please enter a location.'); return; }
                        
                        fetch('/api/hazard_reporting.php?action=add_location', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ store_id: sId, location_name: locationName })
                        }).then(r => r.json()).then(res => {
                            if (res.success) {
                                if (!document.querySelector(`#hazardLocationSelect option[value='${res.id}']`)) {
                                    hazardLocationSelect.add(new Option(res.location_name, res.id), null);
                                }
                                hazardLocationSelect.value = res.id;
                                document.getElementById('newLocationName').value = '';
                                modal.classList.add('hidden');
                            } else alert('Error: ' + res.message);
                        });
                    });
                }
                
                const photoInput = document.getElementById('photoUpload');
                if (photoInput) photoInput.addEventListener('change', function() { validateFiles(this, 5, 2097152, 'photos', '2MB'); });
                const videoInput = document.getElementById('videoUpload');
                if (videoInput) videoInput.addEventListener('change', function() { validateFiles(this, 2, 209715200, 'videos', '200MB'); });
            }

            // ============================================================
            // 3. REPORT VIEWING LOGIC (Master-Detail Views)
            // ============================================================
            const selectableReportList = document.querySelector('.report-list-selectable');
            if (selectableReportList) {
                const viewer = document.getElementById('reportViewer');
                
                selectableReportList.addEventListener('click', function(e) {
                    const item = e.target.closest('.report-item');
                    if (!item) return;
                    
                    // Highlight active list item
                    document.querySelectorAll('.report-item').forEach(el => el.classList.remove('ring-2', 'ring-secondary', 'bg-blue-50'));
                    item.classList.add('ring-2', 'ring-secondary', 'bg-blue-50');

                    const reportId = item.dataset.reportId;
                    if(viewer) {
                        // Reset viewer state
                        viewer.classList.remove('items-center', 'justify-center', 'text-gray-400');
                        viewer.classList.add('block', 'ring-primary', 'ring-opacity-50');
                        viewer.innerHTML = '<div class="p-8 text-center text-gray-500 w-full"><i class="fas fa-spinner fa-spin mr-2"></i>Loading details...</div>';
                        
                        // Mobile scroll
                        if(window.innerWidth < 1024) viewer.scrollIntoView({ behavior: 'smooth' });

                        // Fetch detailed report data
                        fetch(`/api/hazard_reporting.php?action=get_report_details&id=${reportId}`)
                        .then(r => r.json()).then(res => {
                            if(res.success) {
                                const r = res.data;
                                let files = '';
                                
                                // Process Media Attachments
                                if(r.files?.length) {
                                    files = '<h4 class="font-bold mt-6 mb-3 text-sm uppercase text-gray-500 border-b pb-2">Attachments</h4><div class="grid grid-cols-2 gap-4">';
                                    r.files.forEach(f => {
                                        if(f.file_type === 'photo') files += `<a href="${f.file_path}" target="_blank"><img src="${f.file_path}" class="h-32 w-full object-cover rounded shadow-sm border hover:opacity-80 transition"></a>`;
                                        else files += `<a href="${f.file_path}" target="_blank" class="block p-6 bg-primary text-white text-center rounded shadow-sm hover:bg-slate-800 transition"><i class="fas fa-play-circle text-2xl mb-2"></i><br>View Video</a>`;
                                    });
                                    files += '</div>';
                                }

                                // Contextual Action Buttons
                                let actionButtons = '';
                                const isMyReports = window.location.pathname.includes('my-reports');
                                const isStoreReports = window.location.pathname.includes('store-reports');
                                
                                if (isMyReports && r.status !== 'Closed') {
                                    actionButtons += `<a href="/edit-report?id=${r.id}" class="mt-2 text-xs font-bold bg-white text-secondary border border-secondary px-3 py-1 rounded hover:bg-secondary hover:text-white transition shadow-sm inline-flex items-center"><i class="fas fa-edit mr-1"></i> Edit Details</a>`;
                                }
                                
                                if (isStoreReports && r.can_close) {
                                    actionButtons += `<button onclick="window.openCloseModal(${r.id})" class="mt-2 text-xs font-bold bg-white text-accent-red border border-accent-red px-3 py-1 rounded hover:bg-accent-red hover:text-white transition shadow-sm inline-flex items-center ml-2"><i class="fas fa-check-circle mr-1"></i> Close Report</button>`;
                                }

                                const statusBadgeClass = r.status === 'Open' ? 'bg-green-100 text-green-700' : (r.status === 'Under Review' ? 'bg-orange-100 text-orange-700' : 'bg-gray-100 text-gray-600');
                                
                                // Parse Resolution Comments Block
                                let additionalCommentsBlock = '';
                                if(r.additional_comments && r.additional_comments.trim() !== '') {
                                    additionalCommentsBlock = `
                                        <div class="mt-6 border-t pt-4">
                                            <span class="block text-xs font-bold text-gray-400 uppercase mb-2">Resolution & Comments</span>
                                            <div class="bg-blue-50 p-4 rounded border border-blue-100 text-primary font-medium whitespace-pre-wrap">${htmlspecialchars(r.additional_comments)}</div>
                                        </div>
                                    `;
                                }

                                // Inject Viewer HTML
                                viewer.innerHTML = `
                                    <div class="flex justify-between items-start border-b pb-4 mb-4">
                                        <div>
                                            <div class="flex items-center gap-3 mb-1">
                                                <h3 class="text-2xl font-bold text-primary">Report #${r.id}</h3>
                                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider ${statusBadgeClass}">${r.status}</span>
                                            </div>
                                            <p class="text-sm text-gray-500"><i class="fas fa-map-marker-alt mr-1"></i> ${htmlspecialchars(r.hazard_location_name)}</p>
                                        </div>
                                        <div class="flex flex-col items-end">
                                            <span class="px-3 py-1 rounded-full text-xs font-bold text-white bg-${getRiskColor(r.risk_level)} shadow-sm">Risk Level ${r.risk_level}</span>
                                            <div>${actionButtons}</div>
                                        </div>
                                    </div>
                                    <div class="space-y-4 text-sm text-gray-800">
                                        <div class="grid grid-cols-2 gap-4 bg-gray-50 p-4 rounded-lg border border-gray-100">
                                            <div><span class="block text-xs font-bold text-gray-400 uppercase mb-1">Reported By</span>${htmlspecialchars(r.reporter_name)}</div>
                                            <div><span class="block text-xs font-bold text-gray-400 uppercase mb-1">Date Logged</span>${new Date(r.created_at).toLocaleString()}</div>
                                        </div>
                                        <div class="mt-4">
                                            <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Hazard Description</span>
                                            <div class="bg-gray-50 p-4 rounded border border-gray-100">${nl2br(htmlspecialchars(r.hazard_description))}</div>
                                        </div>
                                        <div class="mt-4">
                                            <span class="block text-xs font-bold text-gray-400 uppercase mb-1">Immediate Action Taken</span>
                                            <div class="bg-gray-50 p-4 rounded border border-gray-100">${nl2br(htmlspecialchars(r.action_description))}</div>
                                        </div>
                                        ${additionalCommentsBlock}
                                        ${files}
                                    </div>
                                `;
                            } else { viewer.innerHTML = `<div class="text-red-500 p-4 font-bold text-center">${res.message}</div>`; }
                        });
                    }
                });
            }

            // ============================================================
            // 4. STORE DASHBOARD LOGIC (List Generation & Filtering)
            // ============================================================
            const dashSelector = document.getElementById('storeSelector');
            if (dashSelector) {
                const dashContent = document.getElementById('dashboardContent');
                const listContainer = document.getElementById('reportListContainer');
                const startFilter = document.getElementById('dateStartFilter');
                const endFilter = document.getElementById('dateEndFilter');
                const riskFilter = document.getElementById('riskFilter');
                let allReports = [];

                dashSelector.addEventListener('change', function() {
                    if(this.value) {
                        dashContent.style.display = 'block';
                        const viewer = document.getElementById('reportViewer');
                        if (viewer) {
                            viewer.innerHTML = '<div class="p-8 text-center text-gray-500 w-full flex flex-col items-center"><i class="fas fa-hand-pointer text-5xl mb-4 opacity-50"></i><p class="text-lg font-medium">Select a report from the list to view details</p></div>';
                        }
                        listContainer.innerHTML = '<div class="text-center p-4 text-gray-400"><i class="fas fa-spinner fa-spin mr-2"></i>Loading reports...</div>';
                        fetchStoreData(this.value);
                    } else { dashContent.style.display = 'none'; }
                });
                
                if(startFilter) startFilter.addEventListener('change', applyFilters);
                if(endFilter) endFilter.addEventListener('change', applyFilters);
                if(riskFilter) riskFilter.addEventListener('change', applyFilters);

                function fetchStoreData(id) {
                    fetch(`/api/hazard_reporting.php?action=get_store_stats&store_id=${id}`).then(r=>r.json()).then(res=>{
                        if(res.success) {
                            document.getElementById('statMonthCount').textContent = res.data.reports_this_month;
                            document.getElementById('statRisk1').textContent = res.data.risk_counts['1'];
                            document.getElementById('statRisk2').textContent = res.data.risk_counts['2'];
                            document.getElementById('statRisk3').textContent = res.data.risk_counts['3'];
                        }
                    });
                    fetch(`/api/hazard_reporting.php?action=get_store_reports&store_id=${id}`).then(r=>r.json()).then(res=>{
                        if(res.success) { allReports = res.data; applyFilters(); } 
                        else { listContainer.innerHTML = `<div class="text-center text-red-500 p-4">Error loading reports.</div>`; }
                    });
                }

                function applyFilters() {
                    const start = startFilter.value;
                    const end = endFilter.value;
                    const risk = riskFilter.value;

                    const filtered = allReports.filter(r => {
                        const d = r.created_at.split(' ')[0];
                        const riskMatch = !risk || r.risk_level == risk;
                        const startDateMatch = !start || d >= start;
                        const endDateMatch = !end || d <= end;
                        return riskMatch && startDateMatch && endDateMatch;
                    });
                    populateReportList(filtered);
                }

                function populateReportList(reports) {
                    let html = '';
                    if (reports.length > 0) {
                        reports.forEach(report => {
                            const statusColor = report.status === 'Open' ? 'bg-green-500' : (report.status === 'Under Review' ? 'bg-orange-500' : 'bg-gray-400');
                            html += `
                                <div class="report-item bg-white p-4 rounded-lg border border-gray-200 shadow-sm hover:shadow-md cursor-pointer mb-3 transition relative overflow-hidden group" data-report-id="${report.id}">
                                    <div class="absolute left-0 top-0 bottom-0 w-1 ${statusColor}"></div>
                                    <div class="pl-2">
                                        <div class="flex justify-between items-center mb-1">
                                            <span class="font-bold text-primary group-hover:text-secondary transition-colors">#${report.id}</span>
                                            <span class="text-[10px] text-gray-500">${new Date(report.created_at).toLocaleDateString()}</span>
                                        </div>
                                        <div class="text-sm font-medium text-gray-700 truncate group-hover:text-primary transition-colors">${htmlspecialchars(report.hazard_location_name)}</div>
                                        <div class="mt-2 flex justify-between items-center">
                                            <span class="inline-block px-2 py-0.5 rounded text-[10px] font-bold text-white bg-${getRiskColor(report.risk_level)}">Risk ${report.risk_level}</span>
                                            <span class="text-[10px] uppercase font-bold text-gray-400 tracking-wider">${report.status}</span>
                                        </div>
                                    </div>
                                </div>`;
                        });
                    } else { html = '<div class="text-center text-gray-400 p-8">No reports match the current filters.</div>'; }
                    listContainer.innerHTML = html;
                }
            }

            // ============================================================
            // 5. STATISTICS & METRICS LOGIC (Beta 04)
            // ============================================================
            const metricsStoreSelect = document.getElementById('metricsStoreSelect');
            if (metricsStoreSelect) {
                const metricsMonthSelect = document.getElementById('metricsMonthSelect');
                const metricsContent = document.getElementById('metricsContent');
                const metricsPlaceholder = document.getElementById('metricsPlaceholder');

                function fetchMetrics() {
                    const storeId = metricsStoreSelect.value;
                    const month = metricsMonthSelect.value;
                    
                    if (!storeId) {
                        metricsContent.style.display = 'none';
                        metricsPlaceholder.style.display = 'flex';
                        return;
                    }

                    fetch(`/api/hazard_reporting.php?action=get_advanced_metrics&store_id=${storeId}&month=${month}`)
                    .then(r => r.json())
                    .then(res => {
                        if(res.success) {
                            metricsPlaceholder.style.display = 'none';
                            metricsContent.style.display = 'block';
                            renderMetrics(res.data);
                        } else {
                            alert("Failed to load metrics data.");
                        }
                    });
                }

                metricsStoreSelect.addEventListener('change', fetchMetrics);
                metricsMonthSelect.addEventListener('change', fetchMetrics);

                function renderMetrics(data) {
                    // Update Top Cards
                    document.getElementById('mTotal').textContent = data.total;
                    document.getElementById('mLockout').textContent = data.lockout_count;
                    
                    // Combine Open and Under Review for the orange box
                    const openCount = (data.status_counts['Open'] || 0) + (data.status_counts['Under Review'] || 0);
                    document.getElementById('mOpen').textContent = openCount;
                    document.getElementById('mClosed').textContent = data.status_counts['Closed'] || 0;
                    
                    // Update Risk Levels
                    document.getElementById('mRisk1').textContent = data.risk_levels['1'] || 0;
                    document.getElementById('mRisk2').textContent = data.risk_levels['2'] || 0;
                    document.getElementById('mRisk3').textContent = data.risk_levels['3'] || 0;

                    // Helper to render responsive bars for locations & hazard types
                    const renderBars = (obj, containerId, colorClass) => {
                        let html = '';
                        // Find the max value to calculate percentage widths correctly
                        const values = Object.values(obj);
                        const max = values.length > 0 ? Math.max(...values) : 1;
                        
                        for(let key in obj) {
                            const count = obj[key];
                            const pct = Math.max((count / max) * 100, 5); // 5% minimum so bar is visible
                            
                            html += `
                                <div class="mb-4">
                                    <div class="flex justify-between items-end mb-1">
                                        <span class="text-xs font-bold text-gray-600 truncate mr-2" title="${htmlspecialchars(key)}">${htmlspecialchars(key)}</span>
                                        <span class="text-xs font-bold text-gray-500">${count}</span>
                                    </div>
                                    <div class="w-full bg-gray-100 rounded-full h-2">
                                        <div class="h-2 rounded-full ${colorClass}" style="width: ${pct}%"></div>
                                    </div>
                                </div>
                            `;
                        }
                        document.getElementById(containerId).innerHTML = html || '<div class="text-center text-sm text-gray-400 p-4 border border-dashed rounded">No data for this period.</div>';
                    };

                    renderBars(data.locations, 'mLocations', 'bg-indigo-500');
                    renderBars(data.hazard_types, 'mHazardTypes', 'bg-indigo-400');
                }
            }


            // ============================================================
            // 6. HELPER FUNCTIONS
            // ============================================================
            function fetchData(action, id, el, name) {
                if(!el) return;
                el.innerHTML = '<option>Loading...</option>';
                fetch(`/api/hazard_reporting.php?action=${action}&store_id=${id}`).then(r=>r.json()).then(d=>{
                    el.innerHTML = `<option value="">-- Select ${name} --</option>`;
                    d.forEach(i => el.add(new Option(i.location_name || `${i.first_name} ${i.last_name}`, i.id)));
                    el.disabled = false;
                });
            }
            
            function resetDropdown(el, msg) { 
                if (el && el.tagName === 'SELECT') { 
                    el.innerHTML = `<option>${msg}</option>`; 
                    el.disabled = true; 
                } 
            }
            
            function htmlspecialchars(str) {
                if (typeof str !== 'string') return '';
                const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
                return str.replace(/[&<>"']/g, m => map[m]);
            }
            
            function nl2br(str) {
                if (typeof str !== 'string') return '';
                return str.replace(/\\r\\n|\\n|\\r/g, '<br>');
            }
            
            function getRiskColor(level) {
                if(level == 1) return 'secondary text-black'; 
                if(level == 2) return 'orange-500'; 
                return 'accent-red'; 
            }
            
            function validateFiles(input, max, size, name, sizeStr) {
                if(input.files.length > max) { alert(`Max ${max} ${name}`); input.value = ''; }
                for(let f of input.files) if(f.size > size) { alert(`File too large (${sizeStr} limit)`); input.value = ''; }
            }
            
        });
    </script>
</body>
</html>
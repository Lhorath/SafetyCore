<?php
/**
 * Footer Template - includes/footer.php
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @version   Version 11.0.0 (sentry ohs launch)
 */
?>
    </main> <!-- Closes the <main> tag opened in header.php -->

    <!-- Modern Footer -->
    <footer class="bg-primary text-slate-400 pt-16 pb-8 border-t border-slate-800 mt-auto relative overflow-hidden">
        
        <!-- Subtle Background Glow -->
        <div class="absolute top-0 left-1/2 -translate-x-1/2 w-full max-w-3xl h-px bg-gradient-to-r from-transparent via-blue-500/50 to-transparent"></div>

        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 lg:gap-16 mb-12">
                
                <!-- Brand & About Section -->
                <div class="md:col-span-1 lg:col-span-2">
                    <a href="/" class="flex items-center gap-3 mb-5 group w-fit focus:outline-none">
                        <img src="/style/images/logo.png" alt="Sentry OHS" class="h-8 w-auto grayscale opacity-70 group-hover:grayscale-0 group-hover:opacity-100 transition-all duration-500">
                        <div class="flex flex-col justify-center">
                            <span class="text-[1.15rem] font-extrabold text-white leading-none tracking-tight font-heading">SENTRY<span class="text-secondary">OHS</span></span>
                        </div>
                    </a>
                    <p class="text-sm text-slate-400 leading-relaxed max-w-md">
                        The ultimate enterprise Environment, Health, and Safety (EHS) management platform built for the modern workforce. Digitize your safety culture today.
                    </p>
                </div>

                <!-- Quick Links -->
                <div>
                    <h4 class="text-white font-bold mb-5 uppercase tracking-widest text-xs">Platform</h4>
                    <ul class="space-y-3 text-sm font-medium">
                        <li><a href="/" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> Home</a></li>
                        <li><a href="/services" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> Solutions & Modules</a></li>
                        <li><a href="/login" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> Client Portal</a></li>
                    </ul>
                </div>

                <!-- Support & Contact -->
                <div>
                    <h4 class="text-white font-bold mb-5 uppercase tracking-widest text-xs">Support</h4>
                    <ul class="space-y-3 text-sm font-medium">
                        <li><a href="/about" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> About Sentry OHS</a></li>
                        <li><a href="/contact" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> Contact Us</a></li>
                        <li><a href="mailto:support@sentryohs.com" class="hover:text-blue-400 transition-colors flex items-center"><i class="fas fa-angle-right text-[10px] mr-2 text-slate-600"></i> support@sentryohs.com</a></li>
                    </ul>
                </div>

            </div>

            <!-- Bottom Row: Copyright & Legal -->
            <div class="pt-8 border-t border-slate-800/80 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-500 font-medium">
                    &copy; <?php echo date('Y'); ?> macweb.ca. All Rights Reserved. <span class="hidden sm:inline">|</span> <span class="block sm:inline mt-1 sm:mt-0 text-slate-600">Sentry OHS Beta 10</span>
                </p>
                <div class="flex gap-6 text-xs font-medium">
                    <a href="#" class="text-slate-500 hover:text-slate-300 transition-colors">Privacy Policy</a>
                    <a href="#" class="text-slate-500 hover:text-slate-300 transition-colors">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Global Application Scripts -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            
            // ==========================================
            // 1. Mobile Menu Toggle Logic
            // ==========================================
            const mobileBtn = document.getElementById('mobile-menu-btn');
            const mobileMenu = document.getElementById('mobile-menu');

            if (mobileBtn && mobileMenu) {
                mobileBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    mobileMenu.classList.toggle('hidden');
                    
                    const icon = mobileBtn.querySelector('i');
                    if (mobileMenu.classList.contains('hidden')) {
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                        mobileBtn.classList.remove('bg-slate-100', 'text-primary');
                    } else {
                        icon.classList.remove('fa-bars');
                        icon.classList.add('fa-times');
                        mobileBtn.classList.add('bg-slate-100', 'text-primary');
                    }
                });

                document.addEventListener('click', (e) => {
                    if (!mobileMenu.classList.contains('hidden') && !mobileMenu.contains(e.target) && !mobileBtn.contains(e.target)) {
                        mobileMenu.classList.add('hidden');
                        const icon = mobileBtn.querySelector('i');
                        icon.classList.remove('fa-times');
                        icon.classList.add('fa-bars');
                        mobileBtn.classList.remove('bg-slate-100', 'text-primary');
                    }
                });
            }

            // ==========================================
            // 2. Global Modal Handlers
            // ==========================================
            const closeBtns = document.querySelectorAll('.modal-close-btn, .close-modal-btn');
            closeBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const modal = this.closest('.modal');
                    if (modal) modal.classList.add('hidden');
                });
            });

            window.addEventListener('click', function(e) {
                if (e.target.classList.contains('modal')) {
                    e.target.classList.add('hidden');
                }
            });

            window.toggleModal = function(modalId) {
                const modal = document.getElementById(modalId);
                if (modal) modal.classList.toggle('hidden');
            };

            // ==========================================
            // 3. Hazard Report Module Logic (Legacy Hooks)
            // ==========================================
            const storeSelect = document.getElementById('storeSelect');
            const locationSelect = document.getElementById('hazardLocationSelect');
            const addNewLocationBtn = document.getElementById('addNewLocationBtn');
            const addLocationModal = document.getElementById('addLocationModal');
            const saveNewLocationBtn = document.getElementById('saveNewLocationBtn');
            const newLocationNameInput = document.getElementById('newLocationName');
            const whoNotifiedSelect = document.getElementById('whoNotifiedSelect');

            if (storeSelect && locationSelect) {
                storeSelect.addEventListener('change', function() {
                    const storeId = this.value;
                    if (!storeId) {
                        locationSelect.innerHTML = '<option value="">-- Select a store first --</option>';
                        locationSelect.disabled = true;
                        if (addNewLocationBtn) {
                            addNewLocationBtn.disabled = true;
                            addNewLocationBtn.classList.add('bg-gray-400', 'cursor-not-allowed');
                            addNewLocationBtn.classList.remove('bg-secondary', 'hover:bg-blue-600');
                        }
                        if (whoNotifiedSelect) {
                            whoNotifiedSelect.innerHTML = '<option value="">-- Select a store first --</option>';
                            whoNotifiedSelect.disabled = true;
                        }
                        return;
                    }

                    locationSelect.innerHTML = '<option value="">Loading locations...</option>';
                    locationSelect.disabled = true;
                    
                    fetch(`/api/hazard_reporting.php?action=get_locations&store_id=${storeId}`)
                        .then(res => res.json())
                        .then(data => {
                            locationSelect.innerHTML = '<option value="">-- Select a Location --</option>';
                            data.forEach(loc => {
                                locationSelect.add(new Option(loc.location_name, loc.id));
                            });
                            locationSelect.disabled = false;
                            locationSelect.classList.remove('bg-gray-50', 'cursor-not-allowed');
                            
                            if (addNewLocationBtn) {
                                addNewLocationBtn.disabled = false;
                                addNewLocationBtn.classList.remove('bg-gray-400', 'cursor-not-allowed');
                                addNewLocationBtn.classList.add('bg-secondary');
                            }
                        });

                    if (whoNotifiedSelect) {
                        whoNotifiedSelect.innerHTML = '<option value="">Loading supervisors...</option>';
                        fetch(`/api/hazard_reporting.php?action=get_supervisors&store_id=${storeId}`)
                            .then(res => res.json())
                            .then(data => {
                                whoNotifiedSelect.innerHTML = '<option value="">-- Select Supervisor --</option>';
                                data.forEach(user => {
                                    whoNotifiedSelect.add(new Option(`${user.first_name} ${user.last_name}`, user.id));
                                });
                                whoNotifiedSelect.disabled = false;
                                whoNotifiedSelect.classList.remove('bg-gray-50', 'cursor-not-allowed');
                            });
                    }
                });

                if (addNewLocationBtn && addLocationModal) {
                    addNewLocationBtn.addEventListener('click', () => {
                        addLocationModal.classList.remove('hidden');
                    });
                }

                if (saveNewLocationBtn && newLocationNameInput) {
                    saveNewLocationBtn.addEventListener('click', () => {
                        const storeId = storeSelect.value;
                        const locName = newLocationNameInput.value.trim();
                        const csrfInput = document.querySelector('input[name="_csrf_token"]');
                        const csrfToken = csrfInput ? csrfInput.value : '';
                        if (!storeId || !locName) {
                            alert("Please enter a location name.");
                            return;
                        }
                        if (!csrfToken) {
                            alert("Security token missing. Please refresh the page and try again.");
                            return;
                        }

                        const originalText = saveNewLocationBtn.innerHTML;
                        saveNewLocationBtn.innerHTML = "Saving...";
                        saveNewLocationBtn.disabled = true;

                        fetch('/api/hazard_reporting.php?action=add_location', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ store_id: storeId, location_name: locName, csrf_token: csrfToken })
                        })
                        .then(res => res.json())
                        .then(data => {
                            if (data.success) {
                                const opt = new Option(data.location_name, data.id);
                                locationSelect.add(opt);
                                locationSelect.value = data.id;
                                addLocationModal.classList.add('hidden');
                                newLocationNameInput.value = '';
                            } else {
                                alert(data.message || "Failed to add location.");
                            }
                        })
                        .finally(() => {
                            saveNewLocationBtn.innerHTML = originalText;
                            saveNewLocationBtn.disabled = false;
                        });
                    });
                }

                const actionRadios = document.querySelectorAll('input[name="immediateActionTaken"]');
                const actionLabel = document.getElementById('actionDescriptionLabel');
                actionRadios.forEach(r => {
                    r.addEventListener('change', function() {
                        if(this.value === 'yes') actionLabel.textContent = "Describe action taken";
                        else actionLabel.textContent = "If No, why not?";
                    });
                });

                const lockoutRadios = document.querySelectorAll('input[name="equipmentLockedOut"]');
                const keyHolderGroup = document.getElementById('keyHolderGroup');
                const keyHolderInput = document.getElementById('keyHolderName');
                lockoutRadios.forEach(r => {
                    r.addEventListener('change', function() {
                        if(this.value === 'yes') {
                            keyHolderGroup.style.display = 'block';
                            if (keyHolderInput) keyHolderInput.setAttribute('required', 'required');
                        } else {
                            keyHolderGroup.style.display = 'none';
                            if (keyHolderInput) {
                                keyHolderInput.removeAttribute('required');
                                keyHolderInput.value = '';
                            }
                        }
                    });
                });
            }

            // ==========================================
            // 4. "My Reports" Viewer Logic
            // ==========================================
            const reportList = document.querySelector('.report-list-selectable');
            const reportViewer = document.getElementById('reportViewer');

            if (reportList && reportViewer && window.location.pathname.includes('my-reports')) {
                reportList.addEventListener('click', function(e) {
                    const item = e.target.closest('.report-item');
                    if (!item) return;

                    document.querySelectorAll('.report-item').forEach(el => {
                        el.classList.remove('ring-2', 'ring-secondary', 'bg-blue-50');
                        const ribbon = el.querySelector('.absolute.left-0');
                        if(ribbon) ribbon.classList.remove('w-2');
                    });
                    item.classList.add('ring-2', 'ring-secondary', 'bg-blue-50');
                    const ribbon = item.querySelector('.absolute.left-0');
                    if(ribbon) ribbon.classList.add('w-2');

                    const reportId = item.getAttribute('data-report-id');
                    reportViewer.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-secondary"><i class="fas fa-circle-notch fa-spin text-4xl mb-4"></i><p class="font-bold">Loading Details...</p></div>`;

                    fetch(`/api/hazard_reporting.php?action=get_report_details&id=${reportId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const r = data.data;
                            
                            // BUG FIX: Using correct 'created_at' column to parse date safely
                            const dateObserved = new Date(r.created_at).toLocaleString();

                            let html = `
                                <div class="p-6 animate-fade-in-up w-full">
                                    <div class="flex justify-between items-start border-b border-gray-100 pb-4 mb-6">
                                        <div>
                                            <h3 class="text-2xl font-bold text-primary">Report #${r.id}</h3>
                                            <p class="text-sm font-bold text-gray-500 mt-1">${r.hazard_type}</p>
                                        </div>
                                        <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-bold shadow-sm border border-gray-200">${r.status}</span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 shadow-inner text-sm">
                                        <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Location</span><span class="font-bold text-primary">${r.location_name}</span></div>
                                        <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Date Observed</span><span class="font-bold text-primary">${dateObserved}</span></div>
                                    </div>

                                    <div class="space-y-6 text-sm">
                                        <div>
                                            <span class="block text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Hazard Description</span>
                                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-gray-700 whitespace-pre-wrap shadow-sm">${r.hazard_description}</div>
                                        </div>

                                        <div>
                                            <span class="block text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Immediate Actions Taken</span>
                                            <div class="bg-white p-4 rounded-lg border border-gray-200 text-gray-700 whitespace-pre-wrap shadow-sm">${r.action_description}</div>
                                        </div>
                                    </div>
                            `;

                            if (r.status !== 'Closed') {
                                html += `
                                    <div class="mt-8 pt-4 border-t border-gray-100 text-right">
                                        <a href="/edit-report?id=${r.id}" class="btn btn-secondary text-sm shadow-sm hover:shadow transition"><i class="fas fa-edit mr-2 text-gray-400"></i> Edit Details</a>
                                    </div>
                                `;
                            }
                            html += `</div>`;
                            reportViewer.innerHTML = html;
                        } else {
                            reportViewer.innerHTML = `<div class="text-red-500 font-bold p-8"><i class="fas fa-exclamation-triangle mr-2"></i>${data.message}</div>`;
                        }
                    })
                    .catch(err => {
                        reportViewer.innerHTML = `<div class="text-red-500 font-bold p-8">Network error retrieving details.</div>`;
                    });
                });
            }
        });
    </script>

</body>
</html>
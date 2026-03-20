<?php
/**
 * FLHA Close Out Form - pages/flha-close.php
 *
 * This view provides the mandatory end-of-shift closure process for an open FLHA.
 * It ensures field workers confirm that the work area is safe, permits are closed, 
 * and requires explicit documentation if hazards remain or an incident occurred.
 *
 * Updates in Beta 05:
 * - Initial implementation of the FLHA Close Out workflow.
 * - Added strict client-side validation for conditional text areas.
 * - Enhanced UI with clear warnings about the permanent nature of the closure.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// --- 1. Security & Authentication ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

require_once __DIR__ . '/../includes/csrf.php';
$csrfToken = generate_csrf_token();

// Validate the incoming FLHA ID
$flhaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// Redirect back to the list if no valid ID is provided
if (!$flhaId) {
    header('Location: /flha-list');
    exit();
}
?>

<div class="max-w-3xl mx-auto py-8">
    
    <!-- Page Header -->
    <div class="mb-8 border-b-2 border-primary pb-4">
        <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
            <i class="fas fa-lock text-gray-400 mr-3"></i> End of Shift Close Out
        </h2>
        <p class="text-base text-gray-500 mt-2 font-medium">Finalize and permanently close FLHA Record <span class="text-secondary font-bold">#FLHA-<?php echo htmlspecialchars($flhaId); ?></span></p>
    </div>

    <div class="card p-0 overflow-hidden shadow-xl border-0 ring-1 ring-gray-200">
        
        <!-- Permanent Action Warning -->
        <div class="bg-orange-50 border-b border-orange-200 p-6 flex items-start">
            <div class="bg-white rounded-full p-2 shadow-sm border border-orange-100 text-orange-500 text-xl mr-4 flex-shrink-0">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div>
                <h4 class="font-bold text-orange-800 text-lg mb-1">Important Notice</h4>
                <p class="text-sm text-orange-700 leading-relaxed">Please ensure your shift is completely over and your work area is secured before closing this record. <strong>Once closed, an FLHA cannot be reopened or edited.</strong></p>
            </div>
        </div>

        <!-- Close Out Form -->
        <form id="flhaCloseForm" class="p-6 md:p-10 space-y-8 bg-white">
            <input type="hidden" id="c_flhaId" value="<?php echo htmlspecialchars($flhaId); ?>">
            <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

            <!-- Question 1: Permits -->
            <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-100 pb-6 gap-4">
                <label class="font-bold text-gray-800 text-base md:w-2/3">1. Have all associated permits been officially closed out?</label>
                <div class="flex gap-6 md:w-1/3 md:justify-end">
                    <label class="flex items-center cursor-pointer group">
                        <input type="radio" name="c_permits" value="1" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" required> 
                        <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">Yes</span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="radio" name="c_permits" value="0" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary"> 
                        <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">No / N/A</span>
                    </label>
                </div>
            </div>

            <!-- Question 2: Cleanliness -->
            <div class="flex flex-col md:flex-row md:items-center justify-between border-b border-gray-100 pb-6 gap-4">
                <label class="font-bold text-gray-800 text-base md:w-2/3">2. Was the work area cleaned and secured at the end of the shift?</label>
                <div class="flex gap-6 md:w-1/3 md:justify-end">
                    <label class="flex items-center cursor-pointer group">
                        <input type="radio" name="c_clean" value="1" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" required> 
                        <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">Yes</span>
                    </label>
                    <label class="flex items-center cursor-pointer group">
                        <input type="radio" name="c_clean" value="0" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary"> 
                        <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">No</span>
                    </label>
                </div>
            </div>

            <!-- Question 3: Remaining Hazards -->
            <div class="border-b border-gray-100 pb-6">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
                    <label class="font-bold text-gray-800 text-base md:w-2/3">3. Do any hazards remain in the work area?</label>
                    <div class="flex gap-6 md:w-1/3 md:justify-end">
                        <label class="flex items-center cursor-pointer group">
                            <input type="radio" name="c_hazards" value="1" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" onchange="toggleDesc('c_hazards_desc_container', true)" required> 
                            <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">Yes</span>
                        </label>
                        <label class="flex items-center cursor-pointer group">
                            <input type="radio" name="c_hazards" value="0" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" onchange="toggleDesc('c_hazards_desc_container', false)"> 
                            <span class="ml-2 font-bold text-gray-600 group-hover:text-primary">No</span>
                        </label>
                    </div>
                </div>
                <!-- Conditional Textarea -->
                <div id="c_hazards_desc_container" class="hidden bg-yellow-50 p-4 rounded-lg border border-yellow-200 mt-2 animate-fade-in-up">
                    <label for="c_hazards_desc" class="form-label text-yellow-800 text-xs flex items-center">
                        <i class="fas fa-info-circle mr-2"></i> Please describe the remaining hazards
                    </label>
                    <textarea id="c_hazards_desc" class="form-input text-sm border-yellow-300 focus:ring-yellow-500 shadow-sm" rows="3" placeholder="Detail what hazards were left and why..."></textarea>
                </div>
            </div>

            <!-- Question 4: Incidents -->
            <div class="pb-4">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-4 gap-4">
                    <label class="font-bold text-accent-red text-base md:w-2/3 flex items-center">
                        <i class="fas fa-ambulance mr-2 opacity-75"></i> 4. Were there any incidents, injuries, or near misses during this shift?
                    </label>
                    <div class="flex gap-6 md:w-1/3 md:justify-end">
                        <label class="flex items-center cursor-pointer group">
                            <input type="radio" name="c_incidents" value="1" class="w-5 h-5 text-accent-red border-gray-300 focus:ring-red-500" onchange="toggleDesc('c_incidents_desc_container', true)" required> 
                            <span class="ml-2 font-bold text-gray-600 group-hover:text-accent-red">Yes</span>
                        </label>
                        <label class="flex items-center cursor-pointer group">
                            <input type="radio" name="c_incidents" value="0" class="w-5 h-5 text-accent-red border-gray-300 focus:ring-red-500" onchange="toggleDesc('c_incidents_desc_container', false)"> 
                            <span class="ml-2 font-bold text-gray-600 group-hover:text-accent-red">No</span>
                        </label>
                    </div>
                </div>
                <!-- Conditional Textarea -->
                <div id="c_incidents_desc_container" class="hidden bg-red-50 p-4 rounded-lg border border-red-200 mt-2 animate-fade-in-up">
                    <label for="c_incidents_desc" class="form-label text-red-800 text-xs flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i> Briefly describe the incident
                    </label>
                    <textarea id="c_incidents_desc" class="form-input text-sm border-red-300 focus:ring-red-500 shadow-sm mb-2" rows="3" placeholder="What happened?"></textarea>
                    <p class="text-[10px] text-red-600 font-bold uppercase tracking-wide">Note: A formal Incident Report must also be filed separately via the dashboard.</p>
                </div>
            </div>

            <!-- Form Actions -->
            <div class="flex justify-between items-center bg-gray-50 -mx-6 md:-mx-10 -mb-6 md:-mb-10 p-6 md:p-8 border-t border-gray-200 mt-8">
                <a href="/flha-list" class="btn btn-secondary px-6 shadow-sm hover:shadow">
                    <i class="fas fa-arrow-left mr-2"></i> Go Back
                </a>
                <button type="submit" id="submitCloseBtn" class="btn btn-primary text-lg px-8 shadow-xl transform hover:-translate-y-0.5 transition-all flex items-center group">
                    <i class="fas fa-lock mr-3 group-hover:scale-110 transition-transform"></i> Finalize &amp; Close Record
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ==========================================
     Client-Side Logic
     ========================================== -->
<script>
    /**
     * Toggles the visibility and required state of the conditional textareas.
     */
    function toggleDesc(containerId, show) {
        const container = document.getElementById(containerId);
        const textarea = container.querySelector('textarea');
        
        if (show) {
            container.classList.remove('hidden');
            textarea.setAttribute('required', 'required');
        } else {
            container.classList.add('hidden');
            textarea.removeAttribute('required');
            textarea.value = ''; // Clear value if user toggles back to "No"
        }
    }

    /**
     * Handles form submission, validation, and API fetch request.
     */
    document.getElementById('flhaCloseForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const csrfToken = document.getElementById('csrf_token').value;
        
        const btn = document.getElementById('submitCloseBtn');
        const originalBtnText = btn.innerHTML;
        
        // 1. Gather Payload Data
        const payload = {
            flha_id: document.getElementById('c_flhaId').value,
            permits_closed: document.querySelector('input[name="c_permits"]:checked').value,
            area_cleaned: document.querySelector('input[name="c_clean"]:checked').value,
            hazards_remain: document.querySelector('input[name="c_hazards"]:checked').value,
            hazards_desc: document.getElementById('c_hazards_desc').value.trim(),
            incidents: document.querySelector('input[name="c_incidents"]:checked').value,
            incidents_desc: document.getElementById('c_incidents_desc').value.trim()
        };

        // 2. Strict Client-Side Validation
        if (payload.hazards_remain === '1' && !payload.hazards_desc) { 
            alert("Validation Error: Please describe the remaining hazards before closing."); 
            document.getElementById('c_hazards_desc').focus();
            return; 
        }
        
        if (payload.incidents === '1' && !payload.incidents_desc) { 
            alert("Validation Error: Please provide a brief description of the incident."); 
            document.getElementById('c_incidents_desc').focus();
            return; 
        }

        // 3. UI Loading State
        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Closing Record...';
        btn.disabled = true;

        // 4. API Request
        fetch('/api/flha.php?action=close_out', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Redirect back to the list on success
                window.location.href = '/flha-list';
            } else {
                alert('System Error: ' + (res.message || 'Failed to close the record. Please try again.'));
                btn.innerHTML = originalBtnText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Submission Error:', err);
            alert('A network error occurred while attempting to close the record.');
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        });
    });
</script>
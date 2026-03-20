<?php
/**
 * Incident Management Dashboard - pages/store-incidents.php
 *
 * A restricted management view that allows authorized leadership to review submitted 
 * incident reports, add investigation notes, and officially classify them as 
 * OSHA/WCB Recordable or Lost-Time events for compliance tracking.
 *
 * Updates in Beta 05:
 * - Initial implementation of the Incident Management UI.
 * - Dual-pane layout (List / Details & Form) using max-w-7xl constraints.
 * - Integrated AJAX interactions with `api/incidents.php`.
 * - Styled with the platform's custom Tailwind color palette (accent-red focus).
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// --- 1. Security & Access Control ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';

// Define roles authorized to review and classify incidents
$allowedRoles = [
    'Admin', 
    'Manager', 
    'Safety Manager', 
    'Safety Leader', 
    'Owner / CEO', 
    'Co-manager', 
    'JHSC Leader'
];

if (!in_array($userRole, $allowedRoles)) {
    // Silently redirect unauthorized users to the dashboard
    header('Location: /dashboard');
    exit();
}

$companyId = $_SESSION['user']['company_id'];
$userId = $_SESSION['user']['id'];

// --- 2. Data Fetching ---
$stores = [];

// Admins and Safety Managers typically see all company stores; 
// other managers might be restricted to user_stores in a stricter RBAC setup.
// For Beta 05 standard, we fetch based on the management role hierarchy.
if (in_array($userRole, ['Admin', 'Owner / CEO', 'Safety Manager'])) {
    $sql = "SELECT id, store_name, store_number FROM stores WHERE company_id = ? ORDER BY store_name ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $companyId);
        $stmt->execute();
        $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
} else {
    $sql = "SELECT s.id, s.store_name, s.store_number 
            FROM stores s 
            JOIN user_stores us ON s.id = us.store_id 
            WHERE us.user_id = ? 
            ORDER BY s.store_name ASC";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stores = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}
?>

<div class="max-w-7xl mx-auto py-8">
    
    <!-- Page Header -->
    <div class="mb-8 border-b-2 border-accent-red pb-4 flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-file-medical-alt text-accent-red mr-3"></i> Incident Management
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Review reports, assess lost-time, and log recordable events for OSHA/WCB compliance.</p>
        </div>
        <div class="bg-red-50 text-red-800 px-4 py-2 rounded-lg border border-red-200 font-bold text-sm shadow-sm flex items-center">
            <i class="fas fa-lock mr-2"></i> Management Access Only
        </div>
    </div>

    <!-- Store Selector Panel -->
    <div class="bg-white p-6 md:p-8 rounded-xl shadow-sm border border-gray-200 text-center mb-8 max-w-2xl mx-auto">
        <label for="incStoreSelector" class="block text-sm font-bold text-gray-400 uppercase tracking-wider mb-3">
            Select a Branch / Location to Review
        </label>
        <div class="relative max-w-md mx-auto">
            <select id="incStoreSelector" class="form-input w-full text-center font-bold cursor-pointer appearance-none bg-gray-50 hover:bg-white transition-colors focus:ring-accent-red">
                <option value="" disabled selected>-- Choose a Location --</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo $store['id']; ?>">
                        <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center px-4 text-gray-500">
                <i class="fas fa-chevron-down text-sm"></i>
            </div>
        </div>
    </div>

    <!-- Dual Pane Dashboard (Hidden until a store is selected) -->
    <div id="incDashboardContent" style="display: none;" class="flex flex-col lg:flex-row gap-8 items-start animate-fade-in-up">
        
        <!-- LEFT PANE: Incident List (1/3 Width) -->
        <div class="w-full lg:w-1/3 flex flex-col gap-4">
            <div class="bg-gray-50 p-3 rounded-t-xl border border-gray-200 border-b-0 text-center">
                <span class="text-xs font-bold text-gray-500 uppercase tracking-wider">Incident History</span>
            </div>
            <div id="incListContainer" class="overflow-y-auto max-h-[700px] custom-scrollbar pr-2 space-y-3 pb-4">
                <!-- Javascript populated list goes here -->
                <div class="text-center p-8 text-gray-400"><i class="fas fa-spinner fa-spin text-2xl"></i></div>
            </div>
        </div>

        <!-- RIGHT PANE: Incident Viewer & Management Form (2/3 Width) -->
        <div class="w-full lg:w-2/3 lg:sticky lg:top-6">
            <div id="incViewer" class="card min-h-[500px] flex flex-col items-center justify-center text-gray-400 shadow-lg border border-gray-200 transition-all">
                <div class="bg-gray-50 w-20 h-20 rounded-full flex items-center justify-center mb-4 border border-gray-100 shadow-inner">
                    <i class="fas fa-hand-pointer text-4xl text-gray-300"></i>
                </div>
                <p class="text-lg font-bold text-gray-500">Select an incident to review</p>
                <p class="text-sm mt-2 text-gray-400">Clicking an item in the list will load the full investigation details here.</p>
            </div>
        </div>

    </div>
</div>

<!-- ==========================================
     Client-Side Logic (AJAX & DOM Rendering)
     ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const selector = document.getElementById('incStoreSelector');
    const content = document.getElementById('incDashboardContent');
    const listContainer = document.getElementById('incListContainer');
    const viewer = document.getElementById('incViewer');

    /**
     * Helper: Format Date strings safely
     */
    function formatDate(dateString) {
        if (!dateString) return 'N/A';
        const opts = { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute:'2-digit' };
        return new Date(dateString).toLocaleDateString(undefined, opts);
    }

    /**
     * Event Listener: Store Selection Change
     * Fetches the high-level list of incidents for the selected branch.
     */
    selector.addEventListener('change', function() {
        if (this.value) {
            content.style.display = 'flex';
            
            // Reset UI states
            viewer.innerHTML = `
                <div class="flex flex-col items-center justify-center h-full text-gray-400 w-full min-h-[400px]">
                    <div class="bg-gray-50 w-20 h-20 rounded-full flex items-center justify-center mb-4 border border-gray-100 shadow-inner">
                        <i class="fas fa-hand-pointer text-4xl text-gray-300"></i>
                    </div>
                    <p class="text-lg font-bold text-gray-500">Select an incident to review</p>
                </div>`;
            
            listContainer.innerHTML = '<div class="text-center p-8 text-secondary"><i class="fas fa-circle-notch fa-spin text-3xl"></i></div>';
            
            fetch(`/api/incidents.php?action=get_store_incidents&store_id=${this.value}`)
            .then(response => response.json())
            .then(res => {
                if (res.success) {
                    let html = '';
                    
                    if (res.data.length === 0) {
                        html = `
                            <div class="bg-white p-8 text-center rounded-xl border border-dashed border-gray-300 shadow-sm">
                                <i class="fas fa-shield-check text-green-500 text-3xl mb-3 block"></i>
                                <span class="text-gray-500 font-bold">No incidents logged.</span>
                            </div>`;
                    } else {
                        res.data.forEach(inc => {
                            let badges = '';
                            if (inc.is_recordable == 1) {
                                badges += `<span class="bg-red-100 text-red-700 px-2.5 py-0.5 rounded text-[10px] font-bold mr-1 border border-red-200 shadow-sm">Recordable</span>`;
                            }
                            if (inc.is_lost_time == 1) {
                                badges += `<span class="bg-purple-100 text-purple-700 px-2.5 py-0.5 rounded text-[10px] font-bold border border-purple-200 shadow-sm">Lost Time</span>`;
                            }

                            // Status color coding
                            let statusColor = 'text-gray-400';
                            if (inc.status === 'Open') statusColor = 'text-orange-500';
                            if (inc.status === 'Under Review') statusColor = 'text-blue-500';
                            if (inc.status === 'Closed') statusColor = 'text-green-500';

                            html += `
                                <div class="inc-item bg-white p-4.5 rounded-xl border border-gray-200 shadow-sm hover:shadow-md hover:border-accent-red cursor-pointer transition-all relative group" data-id="${inc.id}">
                                    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gray-200 group-hover:bg-accent-red transition-colors rounded-l-xl"></div>
                                    <div class="pl-4">
                                        <div class="flex justify-between items-center mb-1.5">
                                            <span class="font-bold text-primary text-sm group-hover:text-accent-red transition-colors">#INC-${inc.id}</span>
                                            <span class="text-[10px] text-gray-500 flex items-center"><i class="far fa-clock mr-1"></i>${new Date(inc.incident_date).toLocaleDateString()}</span>
                                        </div>
                                        <div class="text-sm font-bold text-gray-800 mb-2">${inc.incident_type}</div>
                                        <div class="flex justify-between items-center">
                                            <div class="flex flex-wrap gap-1">${badges}</div>
                                            <span class="text-[10px] uppercase font-bold tracking-wider ${statusColor}">${inc.status}</span>
                                        </div>
                                    </div>
                                </div>`;
                        });
                    }
                    listContainer.innerHTML = html;
                } else {
                    listContainer.innerHTML = `<div class="text-red-500 text-center p-4 text-sm font-bold"><i class="fas fa-exclamation-triangle mr-2"></i> Error: ${res.message}</div>`;
                }
            })
            .catch(err => {
                console.error('Fetch error:', err);
                listContainer.innerHTML = '<div class="text-red-500 text-center p-4">Network error loading incidents.</div>';
            });
        } else {
            content.style.display = 'none';
        }
    });

    /**
     * Event Listener: Click on an Incident List Item
     * Fetches deep details and renders the right-hand review pane.
     */
    listContainer.addEventListener('click', function(e) {
        const item = e.target.closest('.inc-item');
        if (!item) return;
        
        // UI Selection Highlight
        document.querySelectorAll('.inc-item').forEach(el => {
            el.classList.remove('ring-2', 'ring-accent-red', 'bg-red-50');
            el.querySelector('.absolute').classList.remove('bg-accent-red');
            el.querySelector('.absolute').classList.add('bg-gray-200');
        });
        item.classList.add('ring-2', 'ring-accent-red', 'bg-red-50');
        item.querySelector('.absolute').classList.remove('bg-gray-200');
        item.querySelector('.absolute').classList.add('bg-accent-red');

        // Loading State
        viewer.innerHTML = `
            <div class="flex flex-col items-center justify-center p-12 text-center w-full min-h-[400px]">
                <i class="fas fa-circle-notch fa-spin text-4xl text-accent-red mb-4"></i>
                <span class="text-gray-500 font-bold animate-pulse">Retrieving incident record...</span>
            </div>`;
        
        fetch(`/api/incidents.php?action=get_details&id=${item.dataset.id}`)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                const d = res.data;
                
                // Construct the Detailed View HTML
                viewer.innerHTML = `
                    <div class="p-2 w-full animate-fade-in-up">
                        <!-- Header -->
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center border-b border-gray-100 pb-4 mb-6 gap-4">
                            <div>
                                <h3 class="text-2xl font-extrabold text-primary flex items-center">
                                    Incident <span class="text-accent-red ml-2">#INC-${d.id}</span>
                                </h3>
                                <p class="text-sm font-bold text-gray-500 mt-1">${d.incident_type}</p>
                            </div>
                            <div class="text-right">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-widest mb-1">Current Status</span>
                                <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-bold border border-gray-200 shadow-sm">${d.status}</span>
                            </div>
                        </div>
                        
                        <!-- Context Grid -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 bg-slate-50 p-5 rounded-xl border border-slate-200 shadow-inner mb-6">
                            <div>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><i class="fas fa-user mr-1"></i> Reporter</span>
                                <span class="text-sm font-bold text-primary">${d.reporter_name}</span>
                            </div>
                            <div>
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><i class="fas fa-clock mr-1"></i> Date & Time</span>
                                <span class="text-sm font-bold text-primary">${formatDate(d.incident_date)}</span>
                            </div>
                            <div class="md:col-span-2 pt-2 border-t border-slate-200 mt-2">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-1"><i class="fas fa-map-marker-alt mr-1"></i> Specific Location</span>
                                <span class="text-sm font-bold text-primary">${d.location_details}</span>
                            </div>
                        </div>

                        <!-- Text Details -->
                        <div class="space-y-6 mb-8">
                            <div>
                                <span class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Description of Event</span>
                                <div class="bg-white p-5 rounded-lg border border-gray-200 text-sm text-gray-700 leading-relaxed shadow-sm whitespace-pre-wrap">${d.description}</div>
                            </div>
                            <div>
                                <span class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Immediate Actions Taken</span>
                                <div class="bg-red-50 p-5 rounded-lg border border-red-100 text-sm text-gray-800 leading-relaxed shadow-sm whitespace-pre-wrap">${d.immediate_actions}</div>
                            </div>
                        </div>

                        <!-- Management Classification Form -->
                        <div class="border-t-2 border-dashed border-gray-200 pt-8 mt-4 relative">
                            <div class="absolute -top-3 left-1/2 transform -translate-x-1/2 bg-white px-4 text-xs font-bold text-gray-400 uppercase tracking-widest">Management Section</div>
                            
                            <h4 class="font-bold text-primary mb-5 flex items-center text-lg">
                                <i class="fas fa-clipboard-check text-accent-red mr-2"></i> Compliance Classification
                            </h4>
                            
                            <form id="incReviewForm" class="space-y-6 bg-white p-6 rounded-xl border border-gray-200 shadow-md">
                                <input type="hidden" id="r_id" value="${d.id}">
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label for="r_status" class="form-label text-xs">Workflow Status</label>
                                        <select id="r_status" class="form-input shadow-sm text-sm">
                                            <option value="Open" ${d.status === 'Open' ? 'selected' : ''}>Open (Pending Investigation)</option>
                                            <option value="Under Review" ${d.status === 'Under Review' ? 'selected' : ''}>Under Review</option>
                                            <option value="Closed" ${d.status === 'Closed' ? 'selected' : ''}>Closed (Resolved)</option>
                                        </select>
                                    </div>
                                    
                                    <div class="flex flex-col gap-3 justify-center bg-gray-50 p-4 rounded-lg border border-gray-100">
                                        <label class="flex items-center space-x-3 cursor-pointer group">
                                            <input type="checkbox" id="r_recordable" class="form-checkbox h-5 w-5 text-red-600 rounded border-gray-300 focus:ring-red-500" ${d.is_recordable == 1 ? 'checked' : ''}>
                                            <span class="font-bold text-red-800 text-sm group-hover:text-red-600 transition-colors">OSHA/WCB Recordable</span>
                                        </label>
                                        <label class="flex items-center space-x-3 cursor-pointer group">
                                            <input type="checkbox" id="r_lost_time" class="form-checkbox h-5 w-5 text-purple-600 rounded border-gray-300 focus:ring-purple-500" ${d.is_lost_time == 1 ? 'checked' : ''}>
                                            <span class="font-bold text-purple-800 text-sm group-hover:text-purple-600 transition-colors">Resulted in Lost Time</span>
                                        </label>
                                    </div>
                                </div>
                                
                                <div>
                                    <label for="r_notes" class="form-label text-xs">Investigation Notes / Findings</label>
                                    <textarea id="r_notes" class="form-input shadow-sm text-sm" rows="3" placeholder="Enter formal investigation findings, corrective actions, or compliance notes...">${d.manager_review_notes || ''}</textarea>
                                </div>
                                
                                <div class="flex justify-between items-center pt-2">
                                    <span class="text-[10px] text-gray-400 font-bold uppercase tracking-wider">
                                        Last Reviewed By: <span class="text-gray-600">${d.reviewer_name || 'Pending'}</span>
                                    </span>
                                    <button type="submit" class="btn btn-dark !px-6 shadow-lg transform hover:-translate-y-0.5 transition-all flex items-center">
                                        <i class="fas fa-save mr-2"></i> Update Classification
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                `;

                // Attach submit handler to the newly injected form
                const reviewForm = document.getElementById('incReviewForm');
                if (reviewForm) {
                    reviewForm.addEventListener('submit', function(ev) {
                        ev.preventDefault();
                        
                        const btn = this.querySelector('button[type="submit"]');
                        const originalBtnHtml = btn.innerHTML;
                        btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';
                        btn.disabled = true;
                        
                        const payload = {
                            incident_id: document.getElementById('r_id').value,
                            status: document.getElementById('r_status').value,
                            is_recordable: document.getElementById('r_recordable').checked ? 1 : 0,
                            is_lost_time: document.getElementById('r_lost_time').checked ? 1 : 0,
                            manager_review_notes: document.getElementById('r_notes').value
                        };

                        fetch('/api/incidents.php?action=update_review', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify(payload)
                        })
                        .then(res => res.json())
                        .then(resp => {
                            if (resp.success) {
                                // Provide visual feedback
                                btn.innerHTML = '<i class="fas fa-check text-green-400 mr-2"></i> Saved';
                                setTimeout(() => {
                                    btn.innerHTML = originalBtnHtml;
                                    btn.disabled = false;
                                }, 2000);
                                
                                // Silently refresh the left-hand list to update badges/status
                                selector.dispatchEvent(new Event('change'));
                            } else {
                                alert('Error: ' + (resp.message || 'Failed to update classification.'));
                                btn.innerHTML = originalBtnHtml;
                                btn.disabled = false;
                            }
                        })
                        .catch(err => {
                            console.error('Update Error:', err);
                            alert('A network error occurred.');
                            btn.innerHTML = originalBtnHtml;
                            btn.disabled = false;
                        });
                    });
                }

            } else {
                viewer.innerHTML = `<div class="text-red-500 text-center p-8"><i class="fas fa-exclamation-triangle mr-2 text-2xl mb-2 block"></i> ${res.message}</div>`;
            }
        })
        .catch(err => {
            console.error('Fetch detail error:', err);
            viewer.innerHTML = '<div class="text-red-500 text-center p-8">Network error loading details.</div>';
        });
    });
});
</script>
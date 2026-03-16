<?php
/**
 * Branch Hazard Review (Store Reports) - pages/store-reports.php
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];

if (!in_array($userRole, $managementRoles)) {
    header('Location: /dashboard');
    exit();
}

$userId = (int)$_SESSION['user']['id'];

$companyId = $_SESSION['user']['company_id'] ?? null;
if (!$companyId) {
    $compSql = "SELECT s.company_id FROM user_stores us JOIN stores s ON us.store_id = s.id WHERE us.user_id = ? LIMIT 1";
    $compStmt = $conn->prepare($compSql);
    $compStmt->bind_param("i", $userId);
    $compStmt->execute();
    $res = $compStmt->get_result()->fetch_assoc();
    $companyId = $res ? $res['company_id'] : 1; 
    $compStmt->close();
}

// Fetch stores mapped to this company
$stores = [];
$stmtS = $conn->prepare("SELECT id, store_name, store_number FROM stores WHERE company_id = ? ORDER BY store_name ASC");
$stmtS->bind_param("i", $companyId);
$stmtS->execute();
$stores = $stmtS->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtS->close();
?>

<div class="max-w-7xl mx-auto py-8">
    
    <div class="mb-8 border-b-2 border-primary pb-4">
        <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
            <i class="fas fa-search-location text-blue-600 mr-3"></i> Branch Hazard Review
        </h2>
        <p class="text-base text-gray-500 mt-2 font-medium">Investigate, assign corrective actions, and close out hazards reported across your branches.</p>
    </div>

    <!-- Store Selector & Filters -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mb-8 flex flex-col md:flex-row gap-4 items-center justify-between">
        <div class="w-full md:w-1/2">
            <label for="branchSelector" class="form-label text-gray-500 mb-2">Select Branch / Job Site</label>
            <!-- BUG FIX: Renamed ID to avoid conflict with legacy form logic -->
            <select id="branchSelector" class="form-input shadow-sm cursor-pointer bg-gray-50 focus:bg-white text-lg font-bold text-primary">
                <option value="">-- Select a Branch / Store --</option>
                <?php foreach ($stores as $store): ?>
                    <option value="<?php echo $store['id']; ?>"><?php echo htmlspecialchars($store['store_name']); ?> (Store #<?php echo htmlspecialchars($store['store_number']); ?>)</option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="w-full md:w-auto flex flex-wrap gap-2">
            <button class="filter-btn bg-blue-100 text-blue-800 border-blue-200 px-4 py-2 rounded-lg text-sm font-bold border transition-colors shadow-sm" data-status="All">All</button>
            <button class="filter-btn bg-white text-gray-600 border-gray-200 hover:bg-red-50 hover:text-red-700 px-4 py-2 rounded-lg text-sm font-bold border transition-colors shadow-sm" data-status="Open">Open</button>
            <button class="filter-btn bg-white text-gray-600 border-gray-200 hover:bg-yellow-50 hover:text-yellow-700 px-4 py-2 rounded-lg text-sm font-bold border transition-colors shadow-sm" data-status="In Progress">In Progress</button>
            <button class="filter-btn bg-white text-gray-600 border-gray-200 hover:bg-green-50 hover:text-green-700 px-4 py-2 rounded-lg text-sm font-bold border transition-colors shadow-sm" data-status="Closed">Closed</button>
        </div>
    </div>

    <!-- Two Column Layout: List and Viewer -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden grid grid-cols-1 lg:grid-cols-3">
        
        <!-- Left Column: Report List -->
        <div class="lg:col-span-1 border-r border-gray-200 bg-white flex flex-col relative z-10">
            <div class="p-4 bg-gray-50 border-b border-gray-200">
                <h3 class="font-bold text-primary text-sm uppercase tracking-wider"><i class="fas fa-list-ul mr-2 text-gray-400"></i> Reports Log</h3>
            </div>
            <!-- BUG FIX: Renamed IDs -->
            <div id="managerReportList" class="divide-y divide-gray-100 max-h-[600px] overflow-y-auto custom-scrollbar">
                <div class="p-6 text-center text-gray-500 font-medium">Select a branch to view reports.</div>
            </div>
        </div>

        <!-- Right Column: Report Viewer -->
        <div class="lg:col-span-2 bg-slate-50 relative overflow-y-auto custom-scrollbar min-h-[500px]">
            <div id="managerReportViewer" class="h-full relative">
                <div class="flex flex-col items-center justify-center h-full text-gray-400">
                    <i class="fas fa-search-location text-5xl mb-4 opacity-50"></i>
                    <p class="text-lg font-medium">Select a report from the list</p>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Using distinct IDs so they never conflict with the footer UI components
        const branchSelector = document.getElementById('branchSelector');
        const managerReportList = document.getElementById('managerReportList');
        const managerReportViewer = document.getElementById('managerReportViewer');

        if (branchSelector) {
            branchSelector.addEventListener('change', function() {
                const storeId = this.value;
                if (!storeId) {
                    managerReportList.innerHTML = '<div class="p-6 text-center text-gray-500 font-medium">Select a branch to view reports.</div>';
                    managerReportViewer.innerHTML = '<div class="flex flex-col items-center justify-center h-full text-gray-400"><i class="fas fa-search-location text-5xl mb-4 opacity-50"></i><p class="text-lg font-medium">Select a report from the list</p></div>';
                    return;
                }

                managerReportList.innerHTML = `<div class="p-6 text-center text-secondary"><i class="fas fa-circle-notch fa-spin text-3xl mb-2"></i><p>Loading reports...</p></div>`;

                fetch(`/api/hazard_reporting.php?action=get_reports_by_store&store_id=${storeId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.data.length > 0) {
                            let html = '';
                            data.data.forEach(r => {
                                const dateStr = new Date(r.created_at).toLocaleDateString();
                                const badgeClass = r.status === 'Open' ? 'bg-red-100 text-red-800 border-red-200 animate-pulse' : (r.status === 'In Progress' ? 'bg-yellow-100 text-yellow-800 border-yellow-200' : 'bg-green-100 text-green-800 border-green-200');
                                
                                html += `
                                    <div class="report-item p-4 hover:bg-slate-50 border-b border-gray-100 cursor-pointer transition-colors relative" onclick="loadManagerReportDetails(${r.id}, this)" data-status="${r.status}">
                                        <div class="absolute left-0 top-0 bottom-0 bg-secondary transition-all w-0"></div>
                                        <div class="flex justify-between items-start mb-2">
                                            <span class="font-bold text-primary">${r.hazard_type}</span>
                                            <span class="px-2 py-0.5 rounded text-[10px] font-bold shadow-sm border ${badgeClass}">${r.status}</span>
                                        </div>
                                        <div class="text-xs text-gray-500 font-medium flex justify-between">
                                            <span><i class="fas fa-map-marker-alt mr-1"></i> ${r.location_name}</span>
                                            <span>${dateStr}</span>
                                        </div>
                                    </div>
                                `;
                            });
                            managerReportList.innerHTML = html;
                        } else {
                            managerReportList.innerHTML = `<div class="p-6 text-center text-gray-500 font-medium">No reports found for this branch.</div>`;
                            managerReportViewer.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-gray-400"><i class="fas fa-search-location text-5xl mb-4 opacity-50"></i><p class="text-lg font-medium">Select a report from the list</p></div>`;
                        }
                    })
                    .catch(err => {
                        managerReportList.innerHTML = `<div class="p-6 text-center text-red-500 font-bold">Error loading reports.</div>`;
                    });
            });
        }

        // Function to load details
        window.loadManagerReportDetails = function(reportId, element) {
            document.querySelectorAll('.report-item').forEach(el => {
                el.classList.remove('bg-blue-50');
                const ribbon = el.querySelector('.absolute');
                if (ribbon) ribbon.classList.remove('w-1');
            });
            element.classList.add('bg-blue-50');
            const activeRibbon = element.querySelector('.absolute');
            if (activeRibbon) activeRibbon.classList.add('w-1');

            managerReportViewer.innerHTML = `<div class="flex flex-col items-center justify-center h-full text-secondary"><i class="fas fa-circle-notch fa-spin text-4xl mb-4"></i><p class="font-bold">Loading Details...</p></div>`;

            fetch(`/api/hazard_reporting.php?action=get_report_details&id=${reportId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const r = data.data;
                        const mediaHtml = r.media_path 
                            ? `<div class="mt-6"><span class="block text-gray-400 text-xs font-bold uppercase tracking-wider mb-2">Attached Media</span><img src="${r.media_path}" class="rounded-lg max-h-64 object-contain shadow-sm border border-gray-200"></div>` 
                            : '';
                        
                        // BUG FIX: Using created_at instead of hazard_observed_at to prevent "Invalid Date"
                        const observedDate = new Date(r.created_at).toLocaleString();

                        managerReportViewer.innerHTML = `
                            <div class="p-6 animate-fade-in-up w-full">
                                <div class="flex justify-between items-start border-b border-gray-100 pb-4 mb-6">
                                    <div>
                                        <h3 class="text-2xl font-bold text-primary">Report #${r.id}</h3>
                                        <p class="text-sm font-bold text-gray-500 mt-1">${r.hazard_type}</p>
                                    </div>
                                    <span class="bg-gray-100 text-gray-800 px-3 py-1 rounded-full text-xs font-bold shadow-sm border border-gray-200">${r.status}</span>
                                </div>
                                
                                <div class="grid grid-cols-2 gap-4 bg-slate-50 p-4 rounded-xl border border-slate-200 mb-6 shadow-inner text-sm">
                                    <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Reported By</span><span class="font-bold text-primary"><i class="fas fa-user-hard-hat text-gray-400 mr-1"></i> ${r.first_name} ${r.last_name}</span></div>
                                    <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Date Observed</span><span class="font-bold text-primary">${observedDate}</span></div>
                                    <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Location</span><span class="font-bold text-primary">${r.location_name}</span></div>
                                    <div><span class="block text-gray-400 text-[10px] font-bold uppercase tracking-wider mb-1">Supervisor Notified</span><span class="font-bold text-primary">${r.supervisor_first_name ? (r.supervisor_first_name + ' ' + r.supervisor_last_name) : 'None'}</span></div>
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
                                    
                                    ${r.equipment_locked_out === 'yes' ? `
                                    <div class="bg-orange-50 border border-orange-200 p-4 rounded-lg">
                                        <span class="block text-orange-800 text-xs font-bold uppercase tracking-wider mb-1"><i class="fas fa-lock mr-1"></i> Equipment Locked Out</span>
                                        <span class="font-bold text-orange-900">Key Holder: ${r.lockout_key_holder}</span>
                                    </div>
                                    ` : ''}
                                </div>
                                
                                ${mediaHtml}

                                <div class="mt-8 pt-4 border-t border-gray-100 flex justify-end">
                                    <a href="/edit-report?id=${r.id}" class="btn btn-primary shadow-md hover:shadow-lg transition"><i class="fas fa-edit mr-2"></i> Manage / Close Report</a>
                                </div>
                            </div>
                        `;
                    } else {
                        managerReportViewer.innerHTML = `<div class="text-red-500 font-bold p-8"><i class="fas fa-exclamation-triangle mr-2"></i>${data.message}</div>`;
                    }
                })
                .catch(err => {
                    managerReportViewer.innerHTML = `<div class="text-red-500 font-bold p-8">Network error retrieving details.</div>`;
                });
        };

        // Filter logic
        const filterBtns = document.querySelectorAll('.filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                filterBtns.forEach(b => b.classList.remove('bg-blue-100', 'text-blue-800', 'border-blue-200'));
                this.classList.add('bg-blue-100', 'text-blue-800', 'border-blue-200');
                
                const status = this.getAttribute('data-status');
                const items = document.querySelectorAll('.report-item');
                
                items.forEach(item => {
                    if (status === 'All' || item.getAttribute('data-status') === status) {
                        item.style.display = 'block';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    });
</script>
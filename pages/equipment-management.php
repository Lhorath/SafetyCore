<?php
/**
 * Equipment Management Dashboard - pages/equipment-management.php
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */

if (!isset($_SESSION['user'])) { header('Location: /login'); exit(); }
require_once __DIR__ . '/../includes/csrf.php';

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];
$isManager = in_array($userRole, $managementRoles);

$csrfToken = generate_csrf_token();
?>

<div class="max-w-7xl mx-auto py-8">
    
    <!-- Page Header -->
    <div class="mb-8 border-b-2 border-primary pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-truck-pickup text-secondary mr-3"></i> Equipment Hub
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Log daily pre-shift inspections, track maintenance, and manage inventory.</p>
        </div>
        <div class="flex flex-wrap gap-3 w-full md:w-auto">
            <a href="/preshift-checklist" class="btn bg-green-500 text-white hover:bg-green-600 flex-1 md:flex-none !px-4 !py-2 text-sm shadow-sm flex items-center justify-center font-bold">
                <i class="fas fa-clipboard-check mr-2"></i> Daily Pre-Shift
            </a>
            <button onclick="openInspectionModal('Maintenance')" class="btn bg-orange-500 text-white hover:bg-orange-600 flex-1 md:flex-none !px-4 !py-2 text-sm shadow-sm flex items-center justify-center font-bold">
                <i class="fas fa-wrench mr-2"></i> Log Repair
            </button>
            <?php if($isManager): ?>
            <button onclick="toggleModal('addEquipmentModal')" class="btn btn-primary flex-1 md:flex-none !px-4 !py-2 text-sm shadow-sm flex items-center justify-center font-bold">
                <i class="fas fa-plus mr-2"></i> Add Asset
            </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grid Layout: Split View -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-8">
        
        <!-- Left Column: Today's Activity -->
        <div class="xl:col-span-1 space-y-6">
            <div class="bg-slate-50 rounded-xl border border-slate-200 p-6 h-full shadow-inner">
                <h3 class="text-lg font-bold text-slate-700 border-b border-slate-200 pb-3 mb-4 flex items-center">
                    <i class="fas fa-calendar-day text-secondary mr-2"></i> Today's Pre-Shifts
                </h3>
                <div id="dailyLogsContainer" class="space-y-3">
                    <div class="text-center text-gray-400 py-6"><i class="fas fa-spinner fa-spin text-2xl mb-2"></i><br>Loading today's logs...</div>
                </div>
            </div>
        </div>

        <!-- Right Column: Full Inventory -->
        <div class="xl:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden relative">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-primary"><i class="fas fa-boxes text-gray-400 mr-2"></i> Asset Inventory</h3>
                </div>
                <div class="overflow-x-auto custom-scrollbar" id="inventoryContainer">
                    <div class="p-12 text-center text-gray-400">
                        <i class="fas fa-circle-notch fa-spin text-4xl mb-3 text-secondary"></i>
                        <p class="font-bold">Syncing Inventory...</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- 1. Maintenance / Repair Log Modal -->
<div id="logInspectionModal" class="modal hidden">
    <div class="modal-content border-t-4 border-orange-500" id="modalTopBorder">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
            <h3 class="text-xl font-bold text-primary flex items-center" id="inspectionModalTitle">
                <i class="fas fa-wrench text-orange-500 mr-2"></i> Maintenance & Repair Log
            </h3>
            <button type="button" onclick="toggleModal('logInspectionModal')" class="text-gray-400 hover:text-accent-red transition-colors focus:outline-none"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form id="logInspectionForm" class="space-y-5">
            <input type="hidden" id="insp_csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" id="inspType" value="Maintenance">

            <div>
                <label class="form-label">Select Equipment <span class="text-accent-red">*</span></label>
                <select id="equipSelect" required class="form-input shadow-sm cursor-pointer"></select>
            </div>
            
            <div>
                <label class="form-label">Maintenance Outcome <span class="text-accent-red">*</span></label>
                <select id="inspResult" required class="form-input shadow-sm cursor-pointer">
                    <option value="" disabled selected>-- Select Result --</option>
                    <option value="Pass">Repaired & Cleared (Restores to Active)</option>
                    <option value="Needs Repair">Inspected but Needs Parts</option>
                    <option value="Fail">Failed (Pulled Out of Service)</option>
                </select>
            </div>
            
            <div>
                <label class="form-label">Mechanic Notes / Work Done</label>
                <textarea id="inspComments" rows="3" class="form-input shadow-sm" placeholder="Describe work performed..."></textarea>
            </div>
            
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('logInspectionModal')" class="btn btn-secondary !px-5 !py-2.5">Cancel</button>
                <button type="submit" id="btnSaveInsp" class="btn bg-orange-500 text-white hover:bg-orange-600 !px-6 !py-2.5 flex items-center shadow-md font-bold transition-colors">
                    <i class="fas fa-save mr-2"></i> Submit Log
                </button>
            </div>
        </form>
    </div>
</div>

<?php if($isManager): ?>
<!-- 2. Add Equipment Modal -->
<div id="addEquipmentModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
            <h3 class="text-xl font-bold text-primary flex items-center">
                <i class="fas fa-truck-loading text-secondary mr-2"></i> Add Asset to Inventory
            </h3>
            <button type="button" onclick="toggleModal('addEquipmentModal')" class="text-gray-400 hover:text-accent-red transition-colors focus:outline-none"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form id="addEquipmentForm" class="space-y-5">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Equipment Name <span class="text-accent-red">*</span></label>
                    <input type="text" id="eqName" required class="form-input shadow-sm" placeholder="e.g. Forklift #4">
                </div>
                <div>
                    <label class="form-label">Category <span class="text-accent-red">*</span></label>
                    <select id="eqCat" required class="form-input shadow-sm">
                        <option value="Heavy Machinery">Heavy Machinery</option>
                        <option value="Vehicles">Vehicles</option>
                        <option value="Power Tools">Power Tools</option>
                        <option value="PPE/Harnesses">PPE/Harnesses</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
            </div>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Serial / VIN Number</label>
                    <input type="text" id="eqSerial" class="form-input shadow-sm" placeholder="Optional">
                </div>
                <div>
                    <label class="form-label">Next Formal Inspection</label>
                    <input type="date" id="eqNextInsp" class="form-input shadow-sm">
                </div>
            </div>

            <!-- Pre-Shift Template Dropdown -->
            <div class="bg-blue-50 p-4 rounded-xl border border-blue-100">
                <label class="form-label text-blue-800">Dynamic Checklist Template</label>
                <select id="eqTemplate" class="form-input shadow-sm border-blue-200 cursor-pointer template-dropdown">
                    <option value="">-- No Checklist Assigned --</option>
                </select>
                <p class="text-xs text-blue-600 mt-2 font-medium"><i class="fas fa-info-circle mr-1"></i> Assign a custom Pre-Shift Checklist template built in the Checklist Builder.</p>
            </div>
            
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('addEquipmentModal')" class="btn btn-secondary !px-5 !py-2.5">Cancel</button>
                <button type="submit" id="btnAddEq" class="btn btn-primary !px-6 !py-2.5 flex items-center shadow-md font-bold">
                    <i class="fas fa-plus mr-2"></i> Add Equipment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. Assign Template Modal -->
<div id="assignTemplateModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
            <h3 class="text-xl font-bold text-primary flex items-center">
                <i class="fas fa-link text-blue-500 mr-2"></i> Assign Checklist Template
            </h3>
            <button type="button" onclick="toggleModal('assignTemplateModal')" class="text-gray-400 hover:text-accent-red transition-colors focus:outline-none"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form id="assignTemplateForm" class="space-y-5">
            <input type="hidden" id="assignEquipId" value="">
            <p class="text-sm text-gray-500">Select which dynamic checklist operators must complete for <strong id="assignEquipName" class="text-primary">this equipment</strong> before starting their shift.</p>
            
            <div>
                <label class="form-label">Checklist Template</label>
                <select id="assignTemplateSelect" class="form-input shadow-sm cursor-pointer template-dropdown">
                    <option value="">-- No Checklist Assigned --</option>
                </select>
            </div>
            
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('assignTemplateModal')" class="btn btn-secondary !px-5 !py-2.5">Cancel</button>
                <button type="submit" id="btnSaveAssignment" class="btn btn-primary !px-6 !py-2.5 flex items-center shadow-md font-bold">
                    <i class="fas fa-save mr-2"></i> Save Assignment
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    const isManager = <?php echo $isManager ? 'true' : 'false'; ?>;

    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.getElementById('insp_csrf') ? document.getElementById('insp_csrf').value : '';
        let availableTemplates = [];
        
        window.toggleModal = function(id) {
            document.getElementById(id).classList.toggle('hidden');
        };

        window.openInspectionModal = function(type) {
            document.getElementById('inspType').value = type;
            toggleModal('logInspectionModal');
        };

        window.openAssignModal = function(equipId, equipName, currentTemplateId) {
            document.getElementById('assignEquipId').value = equipId;
            document.getElementById('assignEquipName').innerText = equipName;
            document.getElementById('assignTemplateSelect').value = currentTemplateId || '';
            toggleModal('assignTemplateModal');
        };

        function getStatusBadge(status) {
            switch(status) {
                case 'Active': return '<span class="bg-green-100 text-green-700 px-3 py-1 rounded-lg text-xs font-bold border border-green-200"><i class="fas fa-check-circle mr-1"></i> Active</span>';
                case 'Maintenance': return '<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-lg text-xs font-bold border border-yellow-300"><i class="fas fa-tools mr-1"></i> Maintenance</span>';
                case 'Out of Service': return '<span class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs font-bold border border-red-200 animate-pulse shadow-sm"><i class="fas fa-ban mr-1"></i> Out of Service</span>';
                default: return `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold">${status}</span>`;
            }
        }

        function getResultBadge(result) {
            if (result === 'Pass' || result === 'Safe') return '<span class="text-green-600 font-bold text-xs"><i class="fas fa-check-circle"></i> Safe</span>';
            if (result === 'Needs Repair') return '<span class="text-orange-500 font-bold text-xs"><i class="fas fa-exclamation-circle"></i> Needs Repair</span>';
            if (result === 'Fail' || result === 'Unsafe') return '<span class="text-red-600 font-bold text-xs"><i class="fas fa-times-circle"></i> Out of Service</span>';
            return result;
        }

        // Hardened Fetch Logic
        function loadTemplates() {
            if (!isManager) return;
            fetch('/api/equipment.php?action=get_templates')
                .then(res => res.json())
                .then(res => {
                    if (res.success) {
                        availableTemplates = res.data;
                        const dropdowns = document.querySelectorAll('.template-dropdown');
                        dropdowns.forEach(dd => {
                            dd.innerHTML = '<option value="">-- No Checklist Assigned --</option>';
                            availableTemplates.forEach(t => dd.add(new Option(t.name, t.id)));
                        });
                    } else {
                        console.error("API Template Error:", res.message);
                    }
                }).catch(err => console.error("Template Network Error:", err));
        }

        function loadDailyLogs() {
            fetch('/api/equipment.php?action=get_daily_logs')
                .then(res => res.json())
                .then(res => {
                    const container = document.getElementById('dailyLogsContainer');
                    if(res.success && res.data.length > 0) {
                        let html = '';
                        res.data.forEach(log => {
                            const timeStr = new Date(log.inspection_date).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                            html += `
                                <div class="bg-white p-4 rounded-xl border border-slate-200 shadow-sm flex flex-col gap-2 relative overflow-hidden hover:border-blue-300 transition-colors">
                                    <div class="flex justify-between items-start">
                                        <div class="flex flex-col">
                                            <span class="font-extrabold text-primary">${log.equipment_name}</span>
                                            <span class="text-[11px] font-bold text-gray-400 uppercase tracking-wider mt-0.5"><i class="fas fa-user-hard-hat mr-1"></i> ${log.first_name} ${log.last_name}</span>
                                        </div>
                                        <div class="text-right">
                                            ${getResultBadge(log.result)}
                                            <span class="block text-[10px] text-gray-400 mt-1">${timeStr}</span>
                                        </div>
                                    </div>
                                    ${log.comments ? `<div class="mt-2 text-xs text-gray-600 bg-gray-50 p-2 rounded border border-gray-100 italic">"${log.comments}"</div>` : ''}
                                </div>`;
                        });
                        container.innerHTML = html;
                    } else if (!res.success) {
                        container.innerHTML = `<div class="text-center text-red-500 py-4 font-bold"><i class="fas fa-exclamation-triangle"></i> Error: ${res.message}</div>`;
                    } else {
                        container.innerHTML = `<div class="text-center text-gray-400 py-8"><i class="fas fa-clipboard-check text-4xl mb-3 text-slate-200 block"></i><p class="text-sm font-medium">No pre-shift logs completed today yet.</p></div>`;
                    }
                }).catch(err => console.error("Logs Network Error:", err));
        }

        function loadInventory() {
            const container = document.getElementById('inventoryContainer');
            container.innerHTML = `<div class="p-12 text-center text-gray-400"><i class="fas fa-circle-notch fa-spin text-4xl mb-3 text-secondary"></i><p class="font-bold">Syncing Inventory...</p></div>`;

            fetch('/api/equipment.php?action=get_inventory')
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        const data = res.data;
                        const equipSel = document.getElementById('equipSelect');
                        equipSel.innerHTML = '<option value="" disabled selected>-- Select Equipment --</option>';

                        if (data.length === 0) {
                            container.innerHTML = `<div class="p-16 text-center text-gray-400"><i class="fas fa-truck-loading text-5xl mb-4 text-gray-300 block"></i><span class="font-bold text-lg text-gray-500">No equipment found.</span></div>`;
                            return;
                        }

                        let html = `<table class="min-w-full text-left text-sm whitespace-nowrap"><thead class="bg-gray-50 border-b border-gray-200"><tr class="text-gray-500 uppercase text-xs font-extrabold tracking-wider">`;
                        html += `<th class="px-6 py-4">Status</th><th class="px-6 py-4">Equipment Name</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Checklist Template</th><th class="px-6 py-4">Last Inspected</th></tr></thead><tbody class="divide-y divide-gray-100">`;

                        data.forEach(e => {
                            equipSel.add(new Option(`${e.name} (${e.status})`, e.id));

                            const lastInsp = e.last_inspection ? new Date(e.last_inspection).toLocaleDateString() : '<span class="text-gray-400 italic">Never</span>';
                            const rowClass = e.status === 'Out of Service' ? 'bg-red-50 hover:bg-red-100' : 'hover:bg-blue-50';
                            
                            const tplBadge = e.template_name 
                                ? `<span class="bg-indigo-50 text-indigo-700 px-2 py-1 rounded text-xs font-bold border border-indigo-200"><i class="fas fa-clipboard-list mr-1"></i> ${e.template_name}</span>`
                                : `<span class="bg-gray-100 text-gray-500 px-2 py-1 rounded text-xs font-bold border border-gray-200"><i class="fas fa-exclamation-circle mr-1"></i> Unassigned</span>`;
                            
                            // Safe String Escaping for HTML rendering
                            const safeName = e.name.replace(/'/g, "\\'").replace(/"/g, '&quot;');
                            const assignAction = isManager ? `<button onclick="openAssignModal(${e.id}, '${safeName}', ${e.checklist_template_id || 'null'})" class="ml-2 text-blue-500 hover:text-blue-700 transition-colors" title="Change Template"><i class="fas fa-edit"></i></button>` : '';

                            html += `<tr class="${rowClass} transition-colors">
                                        <td class="px-6 py-4">${getStatusBadge(e.status)}</td>
                                        <td class="px-6 py-4 font-bold text-primary">${e.name} <span class="block text-xs font-normal text-gray-500 font-mono mt-0.5">${e.serial_number || 'No Serial'}</span></td>
                                        <td class="px-6 py-4 text-gray-600">${e.category}</td>
                                        <td class="px-6 py-4">${tplBadge} ${assignAction}</td>
                                        <td class="px-6 py-4 font-medium">${lastInsp}</td>
                                     </tr>`;
                        });
                        html += `</tbody></table>`;
                        container.innerHTML = html;
                    } else {
                        // Display DB Errors securely in the UI instead of failing silently
                        container.innerHTML = `<div class="p-8 text-center text-red-500 font-bold bg-red-50 rounded-xl border border-red-200 shadow-inner">
                            <i class="fas fa-exclamation-triangle text-3xl mb-2"></i><br>${res.message}</div>`;
                    }
                }).catch(err => {
                    container.innerHTML = `<div class="p-8 text-center text-red-500 font-bold">Network/Parse Error: Check Console.</div>`;
                    console.error(err);
                });
        }

        // Submissions
        document.getElementById('logInspectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveInsp');
            const origHtml = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...'; btn.disabled = true;

            fetch('/api/equipment.php?action=log_inspection', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    equipment_id: document.getElementById('equipSelect').value,
                    inspection_type: document.getElementById('inspType').value,
                    result: document.getElementById('inspResult').value,
                    comments: document.getElementById('inspComments').value,
                    csrf_token: csrfToken
                })
            }).then(res => res.json()).then(res => {
                if(res.success) { toggleModal('logInspectionModal'); this.reset(); loadInventory(); loadDailyLogs(); } 
                else { alert('Error: ' + res.message); }
            }).finally(() => { btn.innerHTML = origHtml; btn.disabled = false; });
        });

        if (document.getElementById('assignTemplateForm')) {
            document.getElementById('assignTemplateForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnSaveAssignment');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...'; btn.disabled = true;

                fetch('/api/equipment.php?action=assign_template', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        equipment_id: document.getElementById('assignEquipId').value,
                        template_id: document.getElementById('assignTemplateSelect').value,
                        csrf_token: csrfToken
                    })
                }).then(res => res.json()).then(res => {
                    if(res.success) { toggleModal('assignTemplateModal'); loadInventory(); } 
                    else { alert('Error: ' + res.message); }
                }).finally(() => { btn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Assignment'; btn.disabled = false; });
            });
        }

        if (document.getElementById('addEquipmentForm')) {
            document.getElementById('addEquipmentForm').addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('btnAddEq');
                btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Adding...'; btn.disabled = true;

                fetch('/api/equipment.php?action=add_equipment', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        name: document.getElementById('eqName').value,
                        category: document.getElementById('eqCat').value,
                        serial_number: document.getElementById('eqSerial').value,
                        next_inspection_date: document.getElementById('eqNextInsp').value,
                        template_id: document.getElementById('eqTemplate').value,
                        csrf_token: csrfToken
                    })
                }).then(res => res.json()).then(res => {
                    if(res.success) { toggleModal('addEquipmentModal'); this.reset(); loadInventory(); } 
                    else { alert('Error: ' + res.message); }
                }).finally(() => { btn.innerHTML = '<i class="fas fa-plus mr-2"></i> Add Equipment'; btn.disabled = false; });
            });
        }

        // Init Flow
        loadTemplates();
        loadInventory();
        loadDailyLogs();
    });
</script>
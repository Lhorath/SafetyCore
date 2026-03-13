<?php
/**
 * Equipment Management Dashboard - pages/equipment-management.php
 *
 * @package   NorthPoint360
 */

if (!isset($_SESSION['user'])) { header('Location: /login'); exit(); }
require_once 'includes/csrf.php';

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];
$isManager = in_array($userRole, $managementRoles);

$csrfToken = generate_csrf_token();
?>

<div class="max-w-7xl mx-auto py-8">
    
    <div class="mb-8 border-b-2 border-primary pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-truck-pickup text-secondary mr-3"></i> Equipment Management
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Track asset inventory and log pre-use equipment inspections.</p>
        </div>
        <div class="flex gap-3 w-full md:w-auto">
            <button onclick="toggleModal('logInspectionModal')" class="btn bg-green-500 text-white hover:bg-green-600 flex-1 md:flex-none !px-4 !py-2 text-sm shadow-sm flex items-center justify-center font-bold">
                <i class="fas fa-clipboard-check mr-2"></i> Log Inspection
            </button>
            <?php if($isManager): ?>
            <button onclick="toggleModal('addEquipmentModal')" class="btn btn-primary flex-1 md:flex-none !px-4 !py-2 text-sm shadow-sm flex items-center justify-center font-bold">
                <i class="fas fa-plus mr-2"></i> Add Equipment
            </button>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden relative">
        <div class="overflow-x-auto custom-scrollbar" id="inventoryContainer">
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-circle-notch fa-spin text-4xl mb-3 text-secondary"></i>
                <p class="font-bold">Loading Inventory...</p>
            </div>
        </div>
    </div>
</div>

<div id="logInspectionModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-6 border-b border-gray-100 pb-3">
            <h3 class="text-xl font-bold text-primary flex items-center">
                <i class="fas fa-clipboard-check text-green-500 mr-2"></i> Pre-Use Inspection
            </h3>
            <button type="button" onclick="toggleModal('logInspectionModal')" class="text-gray-400 hover:text-accent-red transition-colors focus:outline-none"><i class="fas fa-times text-xl"></i></button>
        </div>
        
        <form id="logInspectionForm" class="space-y-5">
            <input type="hidden" id="insp_csrf" value="<?php echo htmlspecialchars($csrfToken); ?>">
            
            <div>
                <label class="form-label">Select Equipment <span class="text-accent-red">*</span></label>
                <select id="equipSelect" required class="form-input shadow-sm cursor-pointer"></select>
            </div>
            
            <div>
                <label class="form-label">Inspection Result <span class="text-accent-red">*</span></label>
                <select id="inspResult" required class="form-input shadow-sm cursor-pointer">
                    <option value="" disabled selected>-- Select Result --</option>
                    <option value="Pass">Pass (Safe to use)</option>
                    <option value="Needs Repair">Needs Repair (Use with caution)</option>
                    <option value="Fail">Fail (Do NOT use - Out of Service)</option>
                </select>
            </div>
            
            <div>
                <label class="form-label">Comments / Issues <span class="text-gray-400 font-normal normal-case">(Required if Failed)</span></label>
                <textarea id="inspComments" rows="3" class="form-input shadow-sm" placeholder="Describe any defects, leaks, or damages..."></textarea>
            </div>
            
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('logInspectionModal')" class="btn btn-secondary !px-5 !py-2.5">Cancel</button>
                <button type="submit" id="btnSaveInsp" class="btn bg-green-500 text-white hover:bg-green-600 !px-6 !py-2.5 flex items-center shadow-md font-bold">
                    <i class="fas fa-save mr-2"></i> Submit Inspection
                </button>
            </div>
        </form>
    </div>
</div>

<?php if($isManager): ?>
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
            
            <div class="mt-8 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('addEquipmentModal')" class="btn btn-secondary !px-5 !py-2.5">Cancel</button>
                <button type="submit" id="btnAddEq" class="btn btn-primary !px-6 !py-2.5 flex items-center shadow-md font-bold">
                    <i class="fas fa-plus mr-2"></i> Add Equipment
                </button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const csrfToken = document.getElementById('insp_csrf') ? document.getElementById('insp_csrf').value : '';
        
        window.toggleModal = function(id) {
            document.getElementById(id).classList.toggle('hidden');
        };

        function getStatusBadge(status) {
            switch(status) {
                case 'Active': return '<span class="bg-green-100 text-green-700 px-3 py-1 rounded-lg text-xs font-bold border border-green-200"><i class="fas fa-check-circle mr-1"></i> Active</span>';
                case 'Maintenance': return '<span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-lg text-xs font-bold border border-yellow-300"><i class="fas fa-tools mr-1"></i> Maintenance</span>';
                case 'Out of Service': return '<span class="bg-red-100 text-red-700 px-3 py-1 rounded-lg text-xs font-bold border border-red-200 animate-pulse"><i class="fas fa-ban mr-1"></i> Out of Service</span>';
                default: return `<span class="bg-gray-100 text-gray-600 px-3 py-1 rounded-lg text-xs font-bold">${status}</span>`;
            }
        }

        function loadInventory() {
            document.getElementById('inventoryContainer').innerHTML = `<div class="p-12 text-center text-gray-400"><i class="fas fa-circle-notch fa-spin text-4xl mb-3 text-secondary"></i><p class="font-bold">Syncing Inventory...</p></div>`;

            fetch('/api/equipment.php?action=get_inventory')
                .then(res => res.json())
                .then(res => {
                    if(res.success) {
                        const data = res.data;
                        const equipSel = document.getElementById('equipSelect');
                        equipSel.innerHTML = '<option value="" disabled selected>-- Select Equipment --</option>';

                        if (data.length === 0) {
                            document.getElementById('inventoryContainer').innerHTML = `<div class="p-16 text-center text-gray-400"><i class="fas fa-truck-loading text-5xl mb-4 text-gray-300 block"></i><span class="font-bold text-lg text-gray-500">No equipment found.</span></div>`;
                            return;
                        }

                        let html = `<table class="min-w-full text-left text-sm whitespace-nowrap"><thead class="bg-gray-50 border-b border-gray-200"><tr class="text-gray-500 uppercase text-xs font-extrabold tracking-wider">`;
                        html += `<th class="px-6 py-4">Status</th><th class="px-6 py-4">Equipment Name</th><th class="px-6 py-4">Category</th><th class="px-6 py-4">Serial / VIN</th><th class="px-6 py-4">Last Inspected</th><th class="px-6 py-4">Next Formal Insp.</th></tr></thead><tbody class="divide-y divide-gray-100">`;

                        data.forEach(e => {
                            // Populate Dropdown for Inspections
                            if(e.status !== 'Out of Service') {
                                equipSel.add(new Option(`${e.name} (${e.serial_number || e.category})`, e.id));
                            }

                            const lastInsp = e.last_inspection ? new Date(e.last_inspection).toLocaleDateString() : '<span class="text-gray-400 italic">Never</span>';
                            const nextInsp = e.next_inspection_date ? new Date(e.next_inspection_date).toLocaleDateString() : '-';

                            html += `<tr class="hover:bg-blue-50 transition-colors">
                                        <td class="px-6 py-4">${getStatusBadge(e.status)}</td>
                                        <td class="px-6 py-4 font-bold text-primary">${e.name}</td>
                                        <td class="px-6 py-4 text-gray-600">${e.category}</td>
                                        <td class="px-6 py-4 text-gray-500 text-xs font-mono">${e.serial_number || '-'}</td>
                                        <td class="px-6 py-4 font-medium">${lastInsp}</td>
                                        <td class="px-6 py-4 text-gray-600">${nextInsp}</td>
                                     </tr>`;
                        });
                        html += `</tbody></table>`;
                        document.getElementById('inventoryContainer').innerHTML = html;
                    } else {
                        document.getElementById('inventoryContainer').innerHTML = `<div class="p-8 text-center text-red-500 font-bold">${res.message}</div>`;
                    }
                })
                .catch(err => {
                    document.getElementById('inventoryContainer').innerHTML = '<div class="p-8 text-center text-red-500 font-bold">Network Error.</div>';
                });
        }

        // Log Inspection Form
        document.getElementById('logInspectionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnSaveInsp');
            btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...'; btn.disabled = true;

            fetch('/api/equipment.php?action=log_inspection', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    equipment_id: document.getElementById('equipSelect').value,
                    result: document.getElementById('inspResult').value,
                    comments: document.getElementById('inspComments').value,
                    csrf_token: csrfToken
                })
            }).then(res => res.json()).then(res => {
                if(res.success) { toggleModal('logInspectionModal'); this.reset(); loadInventory(); } 
                else { alert('Error: ' + res.message); }
            }).finally(() => { btn.innerHTML = '<i class="fas fa-save mr-2"></i> Submit Inspection'; btn.disabled = false; });
        });

        // Add Equipment Form (If present)
        const addEqForm = document.getElementById('addEquipmentForm');
        if (addEqForm) {
            addEqForm.addEventListener('submit', function(e) {
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
                        csrf_token: csrfToken
                    })
                }).then(res => res.json()).then(res => {
                    if(res.success) { toggleModal('addEquipmentModal'); this.reset(); loadInventory(); } 
                    else { alert('Error: ' + res.message); }
                }).finally(() => { btn.innerHTML = '<i class="fas fa-plus mr-2"></i> Add Equipment'; btn.disabled = false; });
            });
        }

        loadInventory();
    });
</script>
```

---

### 4. Integration Updates

**1. Update `index.php`**
Add the new route to the `allowedPages` array:
```php
    // Training & Certifications
    'training-matrix',
    
    // Equipment Management [NEW]
    'equipment-management',
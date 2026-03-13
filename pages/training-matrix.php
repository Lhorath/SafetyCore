<?php
/**
 * Training Matrix Dashboard - pages/training-matrix.php
 * This page provides a comprehensive overview of employee training and certifications.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */
?>
<?php

if (!isset($_SESSION['user'])) { header('Location: /login'); exit(); }
require_once 'includes/csrf.php';

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];
if (!in_array($userRole, $managementRoles)) { header('Location: /dashboard'); exit(); }

// Generate CSRF token for AJAX requests
$csrfToken = generate_csrf_token();
?>

<div class="max-w-7xl mx-auto py-8">
    
    <!-- Header -->
    <div class="mb-8 border-b-2 border-primary pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-certificate text-secondary mr-3"></i> Training & Certifications
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Monitor employee compliance, certifications, and upcoming expirations.</p>
        </div>
        <div class="flex gap-3">
            <button onclick="toggleModal('categoryModal')" class="btn btn-secondary !px-4 !py-2 text-sm shadow-sm flex items-center">
                <i class="fas fa-cog mr-2"></i> Manage Categories
            </button>
            <button onclick="toggleModal('logTrainingModal')" class="btn btn-primary !px-4 !py-2 text-sm shadow-sm flex items-center">
                <i class="fas fa-plus mr-2"></i> Log Training
            </button>
        </div>
    </div>

    <!-- Matrix Table -->
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto custom-scrollbar" id="matrixContainer">
            <div class="p-12 text-center text-gray-400">
                <i class="fas fa-circle-notch fa-spin text-4xl mb-3 text-secondary"></i>
                <p>Loading Training Matrix...</p>
            </div>
        </div>
    </div>
</div>

<!-- ================= MODALS ================= -->

<!-- Log Training Modal -->
<div id="logTrainingModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h3 class="text-xl font-bold text-primary">Log Employee Training</h3>
            <button onclick="toggleModal('logTrainingModal')" class="text-gray-400 hover:text-accent-red"><i class="fas fa-times"></i></button>
        </div>
        <form id="logTrainingForm" class="space-y-4">
            <input type="hidden" id="csrf_token" value="<?php echo $csrfToken; ?>">
            
            <div>
                <label class="form-label">Employee <span class="text-accent-red">*</span></label>
                <select id="empSelect" required class="form-input shadow-sm"></select>
            </div>
            
            <div>
                <label class="form-label">Certification Category <span class="text-accent-red">*</span></label>
                <select id="catSelect" required class="form-input shadow-sm"></select>
            </div>
            
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Issue Date <span class="text-accent-red">*</span></label>
                    <input type="date" id="issueDate" required class="form-input shadow-sm" max="<?php echo date('Y-m-d'); ?>">
                </div>
                <div>
                    <label class="form-label">Certificate # (Optional)</label>
                    <input type="text" id="certNum" class="form-input shadow-sm" placeholder="e.g., FA-12345">
                </div>
            </div>
            
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('logTrainingModal')" class="btn btn-secondary !px-4 !py-2">Cancel</button>
                <button type="submit" class="btn btn-primary !px-6 !py-2 flex items-center"><i class="fas fa-save mr-2"></i> Save Record</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Category Modal -->
<div id="categoryModal" class="modal hidden">
    <div class="modal-content">
        <div class="flex justify-between items-center mb-6 border-b pb-3">
            <h3 class="text-xl font-bold text-primary">Add Training Category</h3>
            <button onclick="toggleModal('categoryModal')" class="text-gray-400 hover:text-accent-red"><i class="fas fa-times"></i></button>
        </div>
        <form id="categoryForm" class="space-y-4">
            <div>
                <label class="form-label">Category Name <span class="text-accent-red">*</span></label>
                <input type="text" id="catName" required class="form-input shadow-sm" placeholder="e.g., Fall Arrest, First Aid">
            </div>
            <div>
                <label class="form-label flex justify-between">
                    <span>Validity Period (Months)</span>
                    <span class="text-xs font-normal text-gray-500 normal-case">Leave as 0 if it never expires</span>
                </label>
                <input type="number" id="catValidity" required class="form-input shadow-sm" value="0" min="0">
            </div>
            
            <div class="mt-6 flex justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" onclick="toggleModal('categoryModal')" class="btn btn-secondary !px-4 !py-2">Cancel</button>
                <button type="submit" class="btn btn-dark !px-6 !py-2 flex items-center"><i class="fas fa-plus mr-2"></i> Add Category</button>
            </div>
        </form>
    </div>
</div>

<!-- ================= SCRIPTS ================= -->
<script>
    const csrfToken = document.getElementById('csrf_token').value;

    function toggleModal(id) {
        const modal = document.getElementById(id);
        modal.classList.toggle('hidden');
    }

    // Determine status of a training record
    function getStatusBadge(record) {
        if (!record) return '<span class="text-gray-300 text-xs font-bold"><i class="fas fa-times-circle opacity-50 mr-1"></i> Missing</span>';
        
        if (!record.expiry_date) {
            return '<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap"><i class="fas fa-check-circle mr-1"></i> Valid (No Expiry)</span>';
        }

        const today = new Date();
        const expiry = new Date(record.expiry_date);
        const daysLeft = Math.ceil((expiry - today) / (1000 * 60 * 60 * 24));

        if (daysLeft < 0) {
            return `<span class="bg-red-100 text-red-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap" title="Expired: ${record.expiry_date}"><i class="fas fa-exclamation-circle mr-1"></i> Expired</span>`;
        } else if (daysLeft <= 30) {
            return `<span class="bg-yellow-100 text-yellow-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap" title="Expires: ${record.expiry_date}"><i class="fas fa-exclamation-triangle mr-1"></i> Expires Soon (${daysLeft}d)</span>`;
        } else {
            return `<span class="bg-green-100 text-green-700 px-2 py-1 rounded text-xs font-bold whitespace-nowrap" title="Expires: ${record.expiry_date}"><i class="fas fa-check-circle mr-1"></i> Valid</span>`;
        }
    }

    function loadMatrix() {
        fetch('/api/training.php?action=get_matrix')
            .then(res => res.json())
            .then(res => {
                if(res.success) {
                    const data = res.data;
                    
                    // Populate Form Dropdowns
                    const empSel = document.getElementById('empSelect');
                    const catSel = document.getElementById('catSelect');
                    empSel.innerHTML = '<option value="" disabled selected>-- Select Employee --</option>';
                    catSel.innerHTML = '<option value="" disabled selected>-- Select Category --</option>';
                    
                    data.users.forEach(u => empSel.add(new Option(`${u.first_name} ${u.last_name}`, u.id)));
                    data.categories.forEach(c => catSel.add(new Option(c.name, c.id)));

                    // Build Matrix HTML
                    if (data.categories.length === 0) {
                        document.getElementById('matrixContainer').innerHTML = `<div class="p-8 text-center text-gray-500 font-bold">No training categories defined yet. Add one above.</div>`;
                        return;
                    }

                    let html = `<table class="min-w-full text-left text-sm whitespace-nowrap"><thead class="bg-gray-50 border-b border-gray-200"><tr class="text-gray-500 uppercase text-xs font-bold tracking-wider">`;
                    html += `<th class="px-6 py-4 sticky left-0 bg-gray-50 z-10 border-r border-gray-200 shadow-sm">Employee Name</th>`;
                    data.categories.forEach(c => html += `<th class="px-6 py-4">${c.name}</th>`);
                    html += `</tr></thead><tbody class="divide-y divide-gray-100">`;

                    data.users.forEach(u => {
                        html += `<tr class="hover:bg-blue-50 transition-colors">
                                    <td class="px-6 py-4 sticky left-0 bg-white group-hover:bg-blue-50 z-10 border-r border-gray-100">
                                        <span class="font-bold text-gray-800">${u.first_name} ${u.last_name}</span>
                                        <span class="block text-[10px] text-gray-500 mt-0.5">${u.employee_position || 'Employee'}</span>
                                    </td>`;
                        data.categories.forEach(c => {
                            const record = u.records[c.id];
                            html += `<td class="px-6 py-4 text-center">${getStatusBadge(record)}</td>`;
                        });
                        html += `</tr>`;
                    });
                    
                    html += `</tbody></table>`;
                    document.getElementById('matrixContainer').innerHTML = html;
                }
            });
    }

    // Submit Log Training Form
    document.getElementById('logTrainingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const payload = {
            user_id: document.getElementById('empSelect').value,
            category_id: document.getElementById('catSelect').value,
            issue_date: document.getElementById('issueDate').value,
            certificate_number: document.getElementById('certNum').value,
            csrf_token: csrfToken
        };

        fetch('/api/training.php?action=log_training', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(res => res.json()).then(res => {
            if(res.success) {
                toggleModal('logTrainingModal');
                this.reset();
                loadMatrix();
            } else alert('Error: ' + res.message);
        });
    });

    // Submit Add Category Form
    document.getElementById('categoryForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const payload = {
            name: document.getElementById('catName').value,
            validity_months: document.getElementById('catValidity').value,
            csrf_token: csrfToken
        };

        fetch('/api/training.php?action=add_category', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(res => res.json()).then(res => {
            if(res.success) {
                toggleModal('categoryModal');
                this.reset();
                loadMatrix();
            } else alert('Error: ' + res.message);
        });
    });

    // Load Matrix on initialization
    loadMatrix();
</script>
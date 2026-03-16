<?php
/**
 * Pre-Shift Checklist - pages/preshift-checklist.php
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */
if (!isset($_SESSION['user'])) { header('Location: /login'); exit(); }
require_once 'includes/csrf.php';
$csrfToken = generate_csrf_token();
?>

<div class="max-w-4xl mx-auto py-8">
    <div class="mb-8 border-b-2 border-primary pb-4">
        <h2 class="text-3xl font-extrabold text-primary flex items-center"><i class="fas fa-clipboard-check text-green-500 mr-3"></i> Pre-Shift Checklist</h2>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden mb-8">
        <form id="dynamicPreshiftForm" class="p-6 md:p-8 space-y-8">
            <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
            <input type="hidden" id="activeTemplateId" value="">

            <div>
                <label class="form-label">Select Equipment <span class="text-accent-red">*</span></label>
                <select id="equipSelect" required class="form-input shadow-sm cursor-pointer" onchange="loadDynamicChecklist(this.value)">
                    <option value="" disabled selected>-- Select Equipment --</option>
                </select>
            </div>

            <div id="dynamicFieldsContainer" class="space-y-4 hidden">
                <h4 class="font-bold text-gray-600 border-b border-gray-200 pb-2 mb-4 uppercase tracking-wider text-sm">Required Checks</h4>
                <div id="fieldsWrapper" class="space-y-4"></div>
                
                <div>
                    <label class="form-label mt-6">General Comments</label>
                    <textarea id="generalComments" rows="3" class="form-input shadow-sm"></textarea>
                </div>

                <div class="pt-6 border-t border-gray-100 flex justify-end">
                    <button type="submit" id="btnSubmit" class="btn bg-green-500 text-white hover:bg-green-600 shadow-md font-bold px-8 py-3 text-lg">
                        <i class="fas fa-check-circle mr-2"></i> Submit Log
                    </button>
                </div>
            </div>
            <div id="statusMessage" class="text-center text-gray-500 py-4">Please select an equipment to load its checklist.</div>
        </form>
    </div>
</div>

<script>
const csrfToken = document.getElementById('csrf_token').value;

// Load Active Equipment via existing equipment.php API
fetch('/api/equipment.php?action=get_inventory')
    .then(res => res.json())
    .then(res => {
        if(res.success) {
            const sel = document.getElementById('equipSelect');
            res.data.filter(e => e.status !== 'Out of Service').forEach(e => {
                sel.add(new Option(e.name, e.id));
            });
        }
    });

function loadDynamicChecklist(equipmentId) {
    const container = document.getElementById('dynamicFieldsContainer');
    const wrapper = document.getElementById('fieldsWrapper');
    const statusMsg = document.getElementById('statusMessage');
    
    container.classList.add('hidden');
    statusMsg.innerHTML = '<i class="fas fa-spinner fa-spin text-2xl"></i> Loading checklist...';
    
    fetch(`/api/checklists.php?action=get_form&equipment_id=${equipmentId}`)
        .then(res => res.json())
        .then(res => {
            if(res.success && res.items.length > 0) {
                document.getElementById('activeTemplateId').value = res.template_id;
                wrapper.innerHTML = '';
                res.items.forEach(item => {
                    // Dynamic Field Builder
                    let inputHtml = '';
                    if(item.field_type === 'pass_fail') {
                        inputHtml = `
                        <div class="flex gap-2">
                            <label><input type="radio" name="item_${item.id}" value="Pass" required class="mr-1"> Pass</label>
                            <label><input type="radio" name="item_${item.id}" value="Fail" required class="mr-1"> Fail</label>
                            <label><input type="radio" name="item_${item.id}" value="N/A" required class="mr-1"> N/A</label>
                        </div>`;
                    } else if(item.field_type === 'yes_no') {
                        inputHtml = `
                        <div class="flex gap-4">
                            <label><input type="radio" name="item_${item.id}" value="Yes" required class="mr-1"> Yes</label>
                            <label><input type="radio" name="item_${item.id}" value="No" required class="mr-1"> No</label>
                        </div>`;
                    } else if(item.field_type === 'text') {
                        inputHtml = `<input type="text" name="item_${item.id}" required class="form-input shadow-sm">`;
                    } else if(item.field_type === 'numeric') {
                        inputHtml = `<input type="number" name="item_${item.id}" required class="form-input shadow-sm w-32">`;
                    } else if(item.field_type === 'checkbox') {
                        inputHtml = `<label><input type="checkbox" name="item_${item.id}" value="Checked" class="mr-2 h-5 w-5 text-secondary"></label>`;
                    }

                    wrapper.innerHTML += `
                        <div class="bg-slate-50 p-4 rounded-xl border border-slate-200 check-item" data-id="${item.id}">
                            <div class="font-bold text-primary mb-2">${item.label}</div>
                            ${inputHtml}
                            <input type="text" id="notes_${item.id}" placeholder="Optional notes..." class="mt-2 text-sm form-input py-1 border-gray-300">
                        </div>`;
                });
                statusMsg.innerHTML = '';
                container.classList.remove('hidden');
            } else {
                statusMsg.innerHTML = '<span class="text-red-500 font-bold"><i class="fas fa-exclamation-circle"></i> ' + (res.message || 'No checklist mapped to this equipment.') + '</span>';
            }
        });
}

document.getElementById('dynamicPreshiftForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnSubmit');
    btn.disabled = true; btn.innerHTML = 'Submitting...';

    const responses = [];
    document.querySelectorAll('.check-item').forEach(el => {
        const id = el.dataset.id;
        let val = '';
        const inputs = el.querySelectorAll(`[name="item_${id}"]`);
        if (inputs[0].type === 'radio') {
            const checked = el.querySelector(`[name="item_${id}"]:checked`);
            if(checked) val = checked.value;
        } else if (inputs[0].type === 'checkbox') {
            val = inputs[0].checked ? 'Checked' : 'Unchecked';
        } else {
            val = inputs[0].value;
        }
        
        responses.push({
            item_id: id,
            value: val,
            notes: document.getElementById(`notes_${id}`).value
        });
    });

    fetch('/api/checklists.php?action=submit_checklist', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            equipment_id: document.getElementById('equipSelect').value,
            template_id: document.getElementById('activeTemplateId').value,
            general_comments: document.getElementById('generalComments').value,
            responses: responses,
            csrf_token: csrfToken
        })
    }).then(res => res.json()).then(res => {
        if(res.success) {
            alert('Pre-Shift Logged Successfully!');
            window.location.reload();
        } else alert('Error: ' + res.message);
    });
});
</script>
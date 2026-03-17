<?php
/**
 * Checklist Builder - pages/checklist-builder.php
 *
 * Management interface to create dynamic Pre-Shift Equipment Checklists.
 *
 * @package   NorthPoint360
 * @version   10.0.0 (NorthPoint Beta 10)
 */

if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

require_once __DIR__ . '/../includes/csrf.php';

$userRole = $_SESSION['user']['role_name'] ?? '';
$managementRoles = ['Admin', 'Manager', 'Safety Manager', 'Safety Leader', 'Owner / CEO', 'Co-manager', 'JHSC Leader'];

if (!in_array($userRole, $managementRoles)) {
    // Redirect non-management users back to the dashboard
    header('Location: /dashboard');
    exit();
}

$csrfToken = generate_csrf_token();
?>

<div class="max-w-5xl mx-auto py-8">
    
    <!-- Header -->
    <div class="mb-8 border-b-2 border-primary pb-4 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-clipboard-list text-blue-600 mr-3"></i> Checklist Builder
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Create dynamic pre-shift inspection templates for your equipment.</p>
        </div>
        <div class="flex gap-3">
            <a href="/equipment-management" class="btn btn-secondary !px-4 !py-2 text-sm shadow-sm flex items-center font-bold">
                <i class="fas fa-arrow-left mr-2"></i> Back to Equipment
            </a>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        
        <!-- Left Column: Template Details & Field Toolbox -->
        <div class="lg:col-span-1 space-y-6">
            
            <!-- Template Configuration -->
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-bold text-primary mb-4 border-b border-gray-100 pb-2"><i class="fas fa-cog mr-2 text-gray-400"></i> Template Details</h3>
                <div class="space-y-4">
                    <div>
                        <label class="form-label">Template Name <span class="text-accent-red">*</span></label>
                        <input type="text" id="templateName" class="form-input shadow-sm" placeholder="e.g. Forklift Daily Check">
                    </div>
                    <div>
                        <label class="form-label">Description (Optional)</label>
                        <textarea id="templateDesc" rows="2" class="form-input shadow-sm" placeholder="Instructions for operators..."></textarea>
                    </div>
                </div>
            </div>

            <!-- Field Toolbox -->
            <div class="bg-slate-50 rounded-xl shadow-inner border border-slate-200 p-6 sticky top-6">
                <h3 class="text-lg font-bold text-slate-700 mb-4 border-b border-slate-200 pb-2"><i class="fas fa-plus-circle mr-2 text-secondary"></i> Add Fields</h3>
                <p class="text-xs text-gray-500 mb-4">Click a field type below to add it to your checklist.</p>
                
                <div class="space-y-2">
                    <button type="button" onclick="addField('pass_fail')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-left p-3 rounded-lg shadow-sm transition-all flex items-center group">
                        <div class="w-8 text-center text-blue-500 group-hover:scale-110 transition-transform"><i class="fas fa-toggle-on text-lg"></i></div>
                        <div>
                            <span class="block font-bold text-gray-700 text-sm">Pass / Fail</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">Triggers OOS on failure</span>
                        </div>
                    </button>
                    
                    <button type="button" onclick="addField('yes_no')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-left p-3 rounded-lg shadow-sm transition-all flex items-center group">
                        <div class="w-8 text-center text-indigo-500 group-hover:scale-110 transition-transform"><i class="fas fa-check-circle text-lg"></i></div>
                        <div>
                            <span class="block font-bold text-gray-700 text-sm">Yes / No</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">Triggers OOS on "No"</span>
                        </div>
                    </button>
                    
                    <button type="button" onclick="addField('checkbox')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-left p-3 rounded-lg shadow-sm transition-all flex items-center group">
                        <div class="w-8 text-center text-teal-500 group-hover:scale-110 transition-transform"><i class="fas fa-check-square text-lg"></i></div>
                        <div>
                            <span class="block font-bold text-gray-700 text-sm">Checkbox</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">Single toggle confirmation</span>
                        </div>
                    </button>

                    <button type="button" onclick="addField('numeric')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-left p-3 rounded-lg shadow-sm transition-all flex items-center group">
                        <div class="w-8 text-center text-orange-500 group-hover:scale-110 transition-transform"><i class="fas fa-hashtag text-lg"></i></div>
                        <div>
                            <span class="block font-bold text-gray-700 text-sm">Numeric Input</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">For hours, mileage, PSI, etc.</span>
                        </div>
                    </button>

                    <button type="button" onclick="addField('text')" class="w-full bg-white border border-gray-200 hover:border-blue-400 hover:bg-blue-50 text-left p-3 rounded-lg shadow-sm transition-all flex items-center group">
                        <div class="w-8 text-center text-gray-600 group-hover:scale-110 transition-transform"><i class="fas fa-font text-lg"></i></div>
                        <div>
                            <span class="block font-bold text-gray-700 text-sm">Short Text</span>
                            <span class="block text-[10px] text-gray-400 mt-0.5">Custom operator input</span>
                        </div>
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Interactive Canvas -->
        <div class="lg:col-span-2">
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 min-h-[500px] flex flex-col relative overflow-hidden">
                <div class="p-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center">
                    <h3 class="font-bold text-primary"><i class="fas fa-list text-gray-400 mr-2"></i> Form Canvas</h3>
                    <span class="text-xs font-bold text-gray-400 bg-white px-2 py-1 rounded border border-gray-200 shadow-sm" id="itemCountBadge">0 Items</span>
                </div>
                
                <!-- Dynamic Items Container -->
                <div id="canvasEmptyState" class="flex-grow flex flex-col items-center justify-center p-12 text-center text-gray-400">
                    <i class="fas fa-clipboard text-6xl text-slate-200 mb-4"></i>
                    <p class="font-medium text-lg text-gray-500">Your checklist is empty.</p>
                    <p class="text-sm mt-1">Click a field type on the left to start building.</p>
                </div>

                <div id="canvasItems" class="flex-grow p-6 space-y-4 hidden bg-slate-50/50">
                    <!-- Fields injected here via JS -->
                </div>

                <!-- Footer Save Action -->
                <div class="p-4 border-t border-gray-200 bg-white flex justify-end gap-3 mt-auto">
                    <button type="button" onclick="clearCanvas()" class="btn btn-secondary !px-5 !py-2.5 shadow-sm font-bold text-sm">Reset</button>
                    <button type="button" onclick="saveTemplate()" id="btnSaveTemplate" class="btn bg-blue-600 text-white hover:bg-blue-700 !px-8 !py-2.5 shadow-md font-bold flex items-center transition-colors">
                        <i class="fas fa-save mr-2"></i> Save Template
                    </button>
                </div>
            </div>
        </div>

    </div>
</div>

<input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

<script>
    const csrfToken = document.getElementById('csrf_token').value;
    let templateItems = [];
    let fieldCounter = 0;

    const DOM = {
        emptyState: document.getElementById('canvasEmptyState'),
        canvas: document.getElementById('canvasItems'),
        countBadge: document.getElementById('itemCountBadge'),
        nameInput: document.getElementById('templateName'),
        descInput: document.getElementById('templateDesc'),
        saveBtn: document.getElementById('btnSaveTemplate')
    };

    // Human-readable labels and icons for the UI
    const fieldTypeMap = {
        'pass_fail': { icon: 'fa-toggle-on text-blue-500', label: 'Pass / Fail' },
        'yes_no': { icon: 'fa-check-circle text-indigo-500', label: 'Yes / No' },
        'checkbox': { icon: 'fa-check-square text-teal-500', label: 'Checkbox' },
        'numeric': { icon: 'fa-hashtag text-orange-500', label: 'Numeric Input' },
        'text': { icon: 'fa-font text-gray-500', label: 'Short Text' }
    };

    /**
     * Add a new field to the state array and re-render
     */
    function addField(type) {
        fieldCounter++;
        templateItems.push({
            id: fieldCounter,
            field_type: type,
            label: '',
            order_index: templateItems.length
        });
        renderCanvas();
        
        // Auto-focus the newest input label
        setTimeout(() => {
            const inputs = document.querySelectorAll('.field-label-input');
            if(inputs.length > 0) inputs[inputs.length - 1].focus();
        }, 50);
    }

    /**
     * Update a field's label in the state array
     */
    function updateFieldLabel(id, newLabel) {
        const item = templateItems.find(i => i.id === id);
        if (item) item.label = newLabel;
    }

    /**
     * Remove a field from the state array
     */
    function removeField(id) {
        templateItems = templateItems.filter(i => i.id !== id);
        // Re-index
        templateItems.forEach((item, idx) => item.order_index = idx);
        renderCanvas();
    }

    /**
     * Move a field up or down in the order
     */
    function moveField(id, direction) {
        const index = templateItems.findIndex(i => i.id === id);
        if (index < 0) return;

        if (direction === 'up' && index > 0) {
            [templateItems[index], templateItems[index - 1]] = [templateItems[index - 1], templateItems[index]];
        } else if (direction === 'down' && index < templateItems.length - 1) {
            [templateItems[index], templateItems[index + 1]] = [templateItems[index + 1], templateItems[index]];
        }
        
        // Re-index
        templateItems.forEach((item, idx) => item.order_index = idx);
        renderCanvas();
    }

    /**
     * Render the state array to the DOM
     */
    function renderCanvas() {
        if (templateItems.length === 0) {
            DOM.emptyState.classList.remove('hidden');
            DOM.canvas.classList.add('hidden');
            DOM.countBadge.innerText = '0 Items';
            DOM.canvas.innerHTML = '';
            return;
        }

        DOM.emptyState.classList.add('hidden');
        DOM.canvas.classList.remove('hidden');
        DOM.countBadge.innerText = `${templateItems.length} Item${templateItems.length !== 1 ? 's' : ''}`;

        let html = '';
        templateItems.forEach((item, idx) => {
            const config = fieldTypeMap[item.field_type];
            const isFirst = idx === 0;
            const isLast = idx === templateItems.length - 1;

            html += `
            <div class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm flex flex-col sm:flex-row sm:items-start gap-4 group transition-all hover:border-blue-300">
                
                <!-- Drag/Order Controls -->
                <div class="flex sm:flex-col gap-1 items-center justify-center text-gray-300">
                    <button type="button" onclick="moveField(${item.id}, 'up')" class="hover:text-blue-500 transition-colors px-2 py-1 ${isFirst ? 'opacity-30 cursor-not-allowed' : ''}" ${isFirst ? 'disabled' : ''}><i class="fas fa-chevron-up"></i></button>
                    <button type="button" onclick="moveField(${item.id}, 'down')" class="hover:text-blue-500 transition-colors px-2 py-1 ${isLast ? 'opacity-30 cursor-not-allowed' : ''}" ${isLast ? 'disabled' : ''}><i class="fas fa-chevron-down"></i></button>
                </div>

                <!-- Field Content -->
                <div class="flex-grow">
                    <div class="flex items-center gap-2 mb-2 text-xs font-bold text-gray-400 uppercase tracking-wider">
                        <i class="fas ${config.icon}"></i> ${config.label}
                    </div>
                    <input type="text" value="${item.label.replace(/"/g, '&quot;')}" oninput="updateFieldLabel(${item.id}, this.value)" placeholder="Enter the question or instruction label..." class="field-label-input w-full border-0 border-b-2 border-dashed border-gray-200 bg-gray-50 px-3 py-2 text-primary font-bold focus:ring-0 focus:border-blue-500 transition-colors rounded-t-md" required>
                </div>

                <!-- Actions -->
                <div class="flex sm:flex-col justify-end pt-2 sm:pt-0">
                    <button type="button" onclick="removeField(${item.id})" class="text-gray-400 hover:text-red-500 hover:bg-red-50 p-2 rounded-lg transition-colors" title="Delete Field">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>`;
        });

        DOM.canvas.innerHTML = html;
    }

    /**
     * Clear all state
     */
    function clearCanvas() {
        if(confirm("Are you sure you want to clear the canvas? All unsaved fields will be lost.")) {
            templateItems = [];
            DOM.nameInput.value = '';
            DOM.descInput.value = '';
            renderCanvas();
        }
    }

    /**
     * Save workflow: Create Template -> Iterate & Create Items -> Redirect
     */
    async function saveTemplate() {
        const name = DOM.nameInput.value.trim();
        const desc = DOM.descInput.value.trim();

        if (!name) {
            alert("Please enter a Template Name.");
            DOM.nameInput.focus();
            return;
        }

        if (templateItems.length === 0) {
            alert("Please add at least one field to the checklist canvas.");
            return;
        }

        // Validate all labels are filled
        const emptyLabels = templateItems.filter(i => i.label.trim() === '');
        if (emptyLabels.length > 0) {
            alert("Please ensure all checklist items have a label/question filled out.");
            return;
        }

        DOM.saveBtn.disabled = true;
        DOM.saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Saving...';

        try {
            // 1. Create the parent Template record
            const tplRes = await fetch('/api/checklists.php?action=save_template', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ name: name, description: desc, csrf_token: csrfToken })
            });
            const tplData = await tplRes.json();

            if (!tplData.success) {
                throw new Error(tplData.message || "Failed to create template.");
            }

            const newTemplateId = tplData.template_id;

            // 2. Iterate and save each item mapping it to the new template_id
            // Running sequentially to ensure order is respected and to avoid overwhelming the server
            for (const item of templateItems) {
                const itemRes = await fetch('/api/checklists.php?action=save_item', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({
                        template_id: newTemplateId,
                        label: item.label.trim(),
                        field_type: item.field_type,
                        csrf_token: csrfToken
                    })
                });
                const itemData = await itemRes.json();
                if (!itemData.success) {
                    console.error("Failed to save item:", item.label);
                }
            }

            // 3. Complete
            alert("Checklist Template saved successfully! You can now assign it to equipment in the Equipment Hub.");
            window.location.href = '/equipment-management';

        } catch (error) {
            alert("An error occurred: " + error.message);
            DOM.saveBtn.disabled = false;
            DOM.saveBtn.innerHTML = '<i class="fas fa-save mr-2"></i> Save Template';
        }
    }
</script>
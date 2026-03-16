<?php
/**
 * FLHA Multi-Step Creation Form - pages/flha-form.php
 *
 * This view provides a robust, 4-step interactive wizard for field workers to 
 * complete their mandatory Field Level Hazard Assessments before starting a shift.
 * It gathers situational hazards, task details, dynamic job steps, and PPE confirmations.
 * * Updates in Beta 05:
 * - Initial creation of the FLHA Wizard.
 * - Integrated dynamic API fetching for co-workers.
 * - Added client-side validation and secure JSON payload assembly.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   10.0.0 (NorthPoint Beta 10)
 */

// --- 1. Security & Authentication ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

// --- 2. Data Definitions ---
// Pre-defined Hazard Categories for UI Generation
$hazardCategories = [
    'Environmental'      => ['Extreme Temperatures', 'Poor Visibility', 'Noise', 'Spill Potential', 'Adverse Weather', 'Dust/Fumes'],
    'Ergonomic'          => ['Repetitive Motion', 'Awkward Posture', 'Heavy Lifting', 'Vibration', 'Prolonged Standing'],
    'Access & Egress'    => ['Slips/Trips/Falls', 'Poor Lighting', 'Scaffolding/Ladders', 'Confined Space', 'Uneven Terrain'],
    'Overhead'           => ['Suspended Loads', 'Dropped Objects', 'Power Lines', 'Low Clearance'],
    'Rigging & Hoisting' => ['Blind Lifts', 'Defective Rigging', 'High Winds', 'Pinch Points'],
    'Electrical'         => ['Lockout Required', 'Exposed Wiring', 'High Voltage', 'Static Electricity'],
    'Personal'           => ['Fatigue', 'Inexperience', 'Working Alone', 'Medication Side-Effects'],
    'Other/Limitations'  => ['Rushing', 'Distractions', 'Specialized Training Required', 'Public Interference']
];

// Standard PPE Checklist
$ppeList = [
    'Hard Hat', 
    'Safety Glasses', 
    'Face Shield', 
    'Steel Toe Boots', 
    'High-Vis Vest', 
    'Hearing Protection', 
    'Fall Protection (Harness)', 
    'Respirator/Mask', 
    'Leather Gloves', 
    'Chemical Gloves', 
    'Fire Retardant Clothing'
];
?>

<div class="max-w-4xl mx-auto py-4">
    
    <!-- Header & Exit -->
    <div class="flex justify-between items-center mb-8">
        <div>
            <h2 class="text-2xl font-bold text-primary">New Field Assessment (FLHA)</h2>
            <p class="text-sm text-gray-500 mt-1">Complete all 4 steps before beginning your shift tasks.</p>
        </div>
        <a href="/flha-list" class="text-sm font-bold text-gray-400 hover:text-accent-red transition-colors flex items-center">
            <i class="fas fa-times mr-2"></i> Cancel
        </a>
    </div>

    <!-- Progress Indicator -->
    <div class="mb-8">
        <div class="flex justify-between text-xs font-bold text-gray-400 uppercase tracking-wider mb-3 px-1" id="step-indicators">
            <span class="text-secondary transition-colors duration-300" id="ind-1">1. Identify Hazards</span>
            <span class="transition-colors duration-300" id="ind-2">2. Assessment Details</span>
            <span class="transition-colors duration-300" id="ind-3">3. Daily Tasks</span>
            <span class="transition-colors duration-300" id="ind-4">4. PPE &amp; Confirm</span>
        </div>
        <div class="w-full bg-gray-200 rounded-full h-2.5 overflow-hidden shadow-inner">
            <div class="bg-secondary h-full rounded-full transition-all duration-500 ease-out relative" id="progress-bar" style="width: 25%">
                <!-- Shine effect on progress bar -->
                <div class="absolute top-0 left-0 bottom-0 right-0 bg-white opacity-20" style="background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);"></div>
            </div>
        </div>
    </div>

    <!-- Wizard Form Container -->
    <div class="card p-0 overflow-hidden shadow-xl border-0 ring-1 ring-gray-200">
        
        <!-- ==========================================
             STEP 1: Hazard Identification
             ========================================== -->
        <div class="step-panel p-6 md:p-10 block animate-fade-in-up" id="step-1">
            <h3 class="text-2xl font-bold text-primary mb-2 border-b border-gray-100 pb-4 flex items-center">
                <div class="w-10 h-10 rounded-full bg-orange-100 text-orange-500 flex items-center justify-center mr-3 text-lg"><i class="fas fa-exclamation-triangle"></i></div>
                Hazard Identification
            </h3>
            <p class="text-gray-500 text-sm mb-8">Select all potential hazards associated with your work area and tasks today. Be thorough.</p>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php foreach ($hazardCategories as $catName => $items): ?>
                    <div class="bg-gray-50 p-5 rounded-xl border border-gray-100 shadow-sm hover:shadow-md transition-shadow duration-200">
                        <h4 class="font-bold text-primary mb-4 text-sm uppercase tracking-wider border-b border-gray-200 pb-2">
                            <?php echo htmlspecialchars($catName); ?>
                        </h4>
                        <div class="space-y-3">
                            <?php foreach ($items as $item): ?>
                                <label class="flex items-start space-x-3 cursor-pointer group">
                                    <input type="checkbox" name="hazards[<?php echo htmlspecialchars($catName); ?>][]" value="<?php echo htmlspecialchars($item); ?>" class="form-checkbox h-5 w-5 text-secondary rounded border-gray-300 focus:ring-secondary cursor-pointer mt-0.5 transition-colors">
                                    <span class="text-sm text-gray-700 group-hover:text-primary font-medium transition-colors select-none"><?php echo htmlspecialchars($item); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="mt-10 flex justify-end border-t border-gray-100 pt-6">
                <button type="button" class="btn btn-primary px-8 shadow-lg transform hover:-translate-y-0.5 flex items-center group" onclick="nextStep(2)">
                    Continue to Details <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </div>
        </div>

        <!-- ==========================================
             STEP 2: Assessment Details
             ========================================== -->
        <div class="step-panel p-6 md:p-10 hidden animate-fade-in-up" id="step-2">
            <h3 class="text-2xl font-bold text-primary mb-8 border-b border-gray-100 pb-4 flex items-center">
                <div class="w-10 h-10 rounded-full bg-blue-100 text-secondary flex items-center justify-center mr-3 text-lg"><i class="fas fa-clipboard-list"></i></div>
                Assessment Details
            </h3>
            
            <div class="space-y-8">
                <div>
                    <label for="workToBeDone" class="form-label">Work to be Done <span class="text-accent-red">*</span></label>
                    <textarea id="workToBeDone" class="form-input shadow-sm" rows="3" placeholder="Describe the overall scope of work for this shift..."></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <div>
                        <label for="taskLocation" class="form-label">Specific Task Location <span class="text-accent-red">*</span></label>
                        <input type="text" id="taskLocation" class="form-input shadow-sm" placeholder="e.g., North End, Level 3, Pump House">
                    </div>
                    <div>
                        <label for="permitNumber" class="form-label">Permit / Job Number <span class="text-gray-400 font-normal normal-case ml-1">(Optional)</span></label>
                        <input type="text" id="permitNumber" class="form-input shadow-sm" placeholder="Enter associated permit #">
                    </div>
                </div>
                
                <div>
                    <label for="emergencyLocation" class="form-label">Emergency Muster / Meeting Location <span class="text-accent-red">*</span></label>
                    <input type="text" id="emergencyLocation" class="form-input shadow-sm" placeholder="Where will the crew meet in case of an evacuation?">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-slate-50 p-6 rounded-xl border border-slate-200 shadow-inner">
                    <div>
                        <label class="form-label text-slate-700">Warning Ribbon/Tape Required?</label>
                        <div class="flex gap-6 mt-3">
                            <label class="flex items-center cursor-pointer group">
                                <input type="radio" name="ribbon" value="1" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary"> 
                                <span class="ml-2 font-bold text-gray-700 group-hover:text-primary">Yes</span>
                            </label>
                            <label class="flex items-center cursor-pointer group">
                                <input type="radio" name="ribbon" value="0" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" checked> 
                                <span class="ml-2 font-bold text-gray-700 group-hover:text-primary">No</span>
                            </label>
                        </div>
                    </div>
                    <div>
                        <label class="form-label text-slate-700">Working Alone?</label>
                        <div class="flex gap-6 mt-3">
                            <label class="flex items-center cursor-pointer group">
                                <input type="radio" name="alone" value="1" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" onchange="toggleAloneDesc(true)"> 
                                <span class="ml-2 font-bold text-gray-700 group-hover:text-primary">Yes</span>
                            </label>
                            <label class="flex items-center cursor-pointer group">
                                <input type="radio" name="alone" value="0" class="w-5 h-5 text-secondary border-gray-300 focus:ring-secondary" checked onchange="toggleAloneDesc(false)"> 
                                <span class="ml-2 font-bold text-gray-700 group-hover:text-primary">No</span>
                            </label>
                        </div>
                    </div>
                </div>
                
                <div id="aloneDescContainer" class="hidden bg-red-50 p-6 rounded-xl border border-red-200 animate-fade-in-up">
                    <label for="aloneDesc" class="form-label text-accent-red flex items-center">
                        <i class="fas fa-exclamation-circle mr-2"></i> Working Alone Procedure / Check-in Plan
                    </label>
                    <input type="text" id="aloneDesc" class="form-input border-red-300 focus:ring-red-500 shadow-sm" placeholder="State how often you will check in and with whom (e.g., Dispatch every 2 hours)...">
                </div>

                <div>
                    <label for="otherWorkers" class="form-label flex justify-between items-end">
                        <span>Other Workers on this Task <span class="text-gray-400 font-normal normal-case ml-1">(Optional)</span></span>
                        <span class="text-xs font-normal text-gray-500 normal-case bg-gray-100 px-2 py-1 rounded border border-gray-200">Hold Ctrl/Cmd to select multiple</span>
                    </label>
                    <select id="otherWorkers" multiple class="form-input h-40 text-sm bg-white shadow-sm focus:ring-secondary">
                        <option value="" disabled>Loading company workforce...</option>
                    </select>
                </div>
            </div>

            <div class="mt-10 flex justify-between border-t border-gray-100 pt-6">
                <button type="button" class="btn btn-secondary px-6 flex items-center group" onclick="nextStep(1)">
                    <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i> Previous
                </button>
                <button type="button" class="btn btn-primary px-8 shadow-lg transform hover:-translate-y-0.5 flex items-center group" onclick="nextStep(3)">
                    Continue to Tasks <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </div>
        </div>

        <!-- ==========================================
             STEP 3: Daily Tasks & Mitigation
             ========================================== -->
        <div class="step-panel p-6 md:p-10 hidden animate-fade-in-up" id="step-3">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-8 border-b border-gray-100 pb-4 gap-4">
                <h3 class="text-2xl font-bold text-primary flex items-center">
                    <div class="w-10 h-10 rounded-full bg-green-100 text-green-600 flex items-center justify-center mr-3 text-lg"><i class="fas fa-tasks"></i></div>
                    Sequential Job Steps
                </h3>
                <button type="button" onclick="addTaskRow()" class="text-sm font-bold bg-green-50 text-green-700 px-4 py-2 rounded-lg border border-green-200 hover:bg-green-100 transition-colors shadow-sm flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add Job Step
                </button>
            </div>
            
            <p class="text-gray-500 text-sm mb-6">Break your work down into sequential steps. Identify the specific hazards for each step, and explain how you will mitigate or eliminate them.</p>

            <div id="tasksContainer" class="space-y-6">
                <!-- Initial Task Row -->
                <div class="task-row bg-slate-50 p-6 rounded-xl border border-slate-200 relative shadow-sm group">
                    <h4 class="font-bold text-gray-400 text-xs uppercase tracking-wider mb-4 flex items-center">
                        <span class="bg-gray-200 text-gray-600 w-5 h-5 rounded-full inline-flex items-center justify-center mr-2 text-[10px]">1</span> Job Step
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="form-label text-xs">Task Description</label>
                            <textarea class="form-input text-sm task-desc shadow-sm" rows="3" placeholder="What are you doing?"></textarea>
                        </div>
                        <div>
                            <label class="form-label text-xs">Specific Hazards</label>
                            <textarea class="form-input text-sm task-hazards shadow-sm" rows="3" placeholder="What could go wrong?"></textarea>
                        </div>
                        <div>
                            <label class="form-label text-xs">Mitigation / Elimination</label>
                            <textarea class="form-input text-sm task-mitigation shadow-sm" rows="3" placeholder="How will you fix it or protect yourself?"></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-10 flex justify-between border-t border-gray-100 pt-6">
                <button type="button" class="btn btn-secondary px-6 flex items-center group" onclick="nextStep(2)">
                    <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i> Previous
                </button>
                <button type="button" class="btn btn-primary px-8 shadow-lg transform hover:-translate-y-0.5 flex items-center group" onclick="nextStep(4)">
                    Continue to PPE <i class="fas fa-arrow-right ml-3 group-hover:translate-x-1 transition-transform"></i>
                </button>
            </div>
        </div>

        <!-- ==========================================
             STEP 4: PPE & Final Submission
             ========================================== -->
        <div class="step-panel p-6 md:p-10 hidden animate-fade-in-up" id="step-4">
            <h3 class="text-2xl font-bold text-primary mb-8 border-b border-gray-100 pb-4 flex items-center">
                <div class="w-10 h-10 rounded-full bg-purple-100 text-purple-600 flex items-center justify-center mr-3 text-lg"><i class="fas fa-hard-hat"></i></div>
                Required PPE &amp; Confirmation
            </h3>
            
            <p class="text-gray-500 text-sm mb-6">Select all Personal Protective Equipment (PPE) required for this specific task.</p>

            <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-10">
                <?php foreach ($ppeList as $ppe): ?>
                    <label class="flex items-center space-x-3 cursor-pointer bg-white p-4 rounded-xl border border-gray-200 hover:border-secondary hover:shadow-md transition-all group">
                        <input type="checkbox" name="ppe[]" value="<?php echo htmlspecialchars($ppe); ?>" class="form-checkbox h-5 w-5 text-secondary rounded border-gray-300 focus:ring-secondary">
                        <span class="text-sm font-bold text-gray-700 group-hover:text-primary"><?php echo $ppe; ?></span>
                    </label>
                <?php endforeach; ?>
            </div>

            <!-- Mandatory Compliance Confirmation -->
            <div class="bg-blue-50 p-6 md:p-8 rounded-xl border border-blue-200 shadow-inner mb-8">
                <label class="flex items-start space-x-4 cursor-pointer group">
                    <input type="checkbox" id="employerPPEConfirm" class="form-checkbox h-6 w-6 text-secondary mt-1 border-blue-300 focus:ring-secondary">
                    <div>
                        <span class="font-extrabold text-primary text-lg block mb-2 group-hover:text-secondary transition-colors">Mandatory PPE Confirmation</span>
                        <span class="text-sm text-gray-700 block leading-relaxed">By checking this box, I confirm that my employer has supplied all required Personal Protective Equipment (PPE) needed to execute this task safely, and that I have inspected it prior to use.</span>
                    </div>
                </label>
            </div>

            <div class="mt-10 flex justify-between border-t border-gray-100 pt-6">
                <button type="button" class="btn btn-secondary px-6 flex items-center group" onclick="nextStep(3)">
                    <i class="fas fa-arrow-left mr-2 group-hover:-translate-x-1 transition-transform"></i> Previous
                </button>
                <button type="button" id="submitFlhaBtn" class="btn btn-accent text-lg px-8 shadow-xl transform hover:-translate-y-1 transition-all flex items-center" onclick="submitFLHA(this)">
                    <i class="fas fa-lock mr-3"></i> Save &amp; Open Assessment
                </button>
            </div>
        </div>
    </div>
</div>

<!-- ==========================================
     Client-Side Logic (Wizard Engine)
     ========================================== -->
<script>
    let currentStep = 1;
    let taskCount = 1;

    // Initialize: Fetch Co-workers on page load
    document.addEventListener('DOMContentLoaded', () => {
        const workerSelect = document.getElementById('otherWorkers');
        
        fetch('/api/flha.php?action=get_company_workers')
            .then(response => response.json())
            .then(res => {
                if(res.success) {
                    workerSelect.innerHTML = ''; // Clear loading message
                    if (res.data.length === 0) {
                        workerSelect.add(new Option('No other employees found.', '', false, false));
                        workerSelect.disabled = true;
                    } else {
                        res.data.forEach(w => { 
                            const pos = w.employee_position ? ` - ${w.employee_position}` : '';
                            workerSelect.add(new Option(`${w.first_name} ${w.last_name}${pos}`, w.id)); 
                        });
                    }
                } else {
                    workerSelect.innerHTML = '<option disabled>Error loading workforce data.</option>';
                }
            })
            .catch(err => {
                console.error('Failed to fetch workers:', err);
                workerSelect.innerHTML = '<option disabled>Network error loading workforce.</option>';
            });
    });

    /**
     * Handles navigation between the 4 wizard steps and updates the progress bar.
     */
    function nextStep(step) {
        // Basic Step 2 Validation before allowing progression
        if (currentStep === 2 && step === 3) {
            const work = document.getElementById('workToBeDone').value.trim();
            const loc = document.getElementById('taskLocation').value.trim();
            const emerg = document.getElementById('emergencyLocation').value.trim();
            
            if (!work || !loc || !emerg) {
                alert('Please fill out all required fields (marked with *) in the Assessment Details.');
                return;
            }
        }

        // Hide all panels
        document.querySelectorAll('.step-panel').forEach(p => p.classList.add('hidden'));
        
        // Show target panel
        const targetPanel = document.getElementById(`step-${step}`);
        targetPanel.classList.remove('hidden');
        
        // Update Progress UI
        const progressPercentage = (step * 25);
        document.getElementById('progress-bar').style.width = progressPercentage + '%';
        
        // Update text indicators
        for(let i=1; i<=4; i++) {
            const ind = document.getElementById(`ind-${i}`);
            if(i <= step) { 
                ind.classList.add('text-secondary'); 
                ind.classList.remove('text-gray-400'); 
            } else { 
                ind.classList.remove('text-secondary'); 
                ind.classList.add('text-gray-400'); 
            }
        }
        
        currentStep = step;
        
        // Smooth scroll to top of wizard
        window.scrollTo({ top: targetPanel.offsetTop - 100, behavior: 'smooth' });
    }

    /**
     * Toggles the visibility of the "Working Alone" description field.
     */
    function toggleAloneDesc(show) {
        const container = document.getElementById('aloneDescContainer');
        const input = document.getElementById('aloneDesc');
        if (show) {
            container.classList.remove('hidden');
            input.setAttribute('required', 'required');
        } else {
            container.classList.add('hidden');
            input.removeAttribute('required');
            input.value = ''; // clear value
        }
    }

    /**
     * Dynamically injects a new Job Step row into Step 3.
     */
    function addTaskRow() {
        taskCount++;
        const row = document.createElement('div');
        row.className = 'task-row bg-slate-50 p-6 rounded-xl border border-slate-200 relative shadow-sm group mt-6 animate-fade-in-up';
        row.innerHTML = `
            <button type="button" class="absolute top-4 right-4 text-gray-400 hover:text-accent-red transition-colors w-8 h-8 rounded-full hover:bg-red-50 flex items-center justify-center focus:outline-none" onclick="this.parentElement.remove()" title="Remove Step">
                <i class="fas fa-trash-alt"></i>
            </button>
            <h4 class="font-bold text-gray-400 text-xs uppercase tracking-wider mb-4 flex items-center">
                <span class="bg-gray-200 text-gray-600 w-5 h-5 rounded-full inline-flex items-center justify-center mr-2 text-[10px]">${taskCount}</span> Job Step
            </h4>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div><label class="form-label text-xs">Task Description</label><textarea class="form-input text-sm task-desc shadow-sm" rows="3" placeholder="What are you doing?"></textarea></div>
                <div><label class="form-label text-xs">Specific Hazards</label><textarea class="form-input text-sm task-hazards shadow-sm" rows="3" placeholder="What could go wrong?"></textarea></div>
                <div><label class="form-label text-xs">Mitigation / Elimination</label><textarea class="form-input text-sm task-mitigation shadow-sm" rows="3" placeholder="How will you fix it or protect yourself?"></textarea></div>
            </div>
        `;
        document.getElementById('tasksContainer').appendChild(row);
    }

    /**
     * Gathers all data, performs final validation, and submits the JSON payload to the API.
     */
    function submitFLHA(btnElement) {
        // Final Validation Check
        const ppeConfirmed = document.getElementById('employerPPEConfirm').checked;
        if (!ppeConfirmed) { 
            alert('Mandatory Compliance: You must confirm that the employer supplied required PPE by checking the box.'); 
            return; 
        }

        const isWorkingAlone = document.querySelector('input[name="alone"]:checked').value === '1';
        const aloneDesc = document.getElementById('aloneDesc').value.trim();
        if (isWorkingAlone && !aloneDesc) {
            alert('You marked that you are working alone. Please return to Step 2 and provide a check-in procedure.');
            nextStep(2);
            return;
        }

        // Lock button to prevent double submission
        const originalBtnText = btnElement.innerHTML;
        btnElement.innerHTML = '<i class="fas fa-spinner fa-spin mr-3"></i> Processing...';
        btnElement.disabled = true;

        // Assemble JSON Payload
        const payload = {
            work_to_be_done: document.getElementById('workToBeDone').value.trim(),
            task_location: document.getElementById('taskLocation').value.trim(),
            emergency_location: document.getElementById('emergencyLocation').value.trim(),
            permit_number: document.getElementById('permitNumber').value.trim(),
            warning_ribbon_required: document.querySelector('input[name="ribbon"]:checked').value,
            working_alone: isWorkingAlone ? 1 : 0,
            working_alone_desc: aloneDesc,
            employer_supplied_ppe: 1, // Ensured by validation above
            hazards: {},
            ppe: [],
            other_workers: [],
            tasks: []
        };

        // Gather Checkboxes (Hazards)
        document.querySelectorAll('input[name^="hazards"]:checked').forEach(cb => {
            // Extract category dynamically from the input name attribute
            const catMatch = cb.name.match(/\[(.*?)\]/);
            const cat = catMatch ? catMatch[1] : 'General';
            if (!payload.hazards[cat]) payload.hazards[cat] = [];
            payload.hazards[cat].push(cb.value);
        });

        // Gather PPE
        document.querySelectorAll('input[name="ppe[]"]:checked').forEach(cb => payload.ppe.push(cb.value));
        
        // Gather Multi-select Workers
        const workerSelect = document.getElementById('otherWorkers');
        if (!workerSelect.disabled) {
            Array.from(workerSelect.selectedOptions).forEach(opt => {
                if (opt.value) payload.other_workers.push(opt.value);
            });
        }

        // Gather Dynamic Tasks
        let hasEmptyTasks = false;
        document.querySelectorAll('.task-row').forEach(row => {
            const desc = row.querySelector('.task-desc').value.trim();
            const haz = row.querySelector('.task-hazards').value.trim();
            const mit = row.querySelector('.task-mitigation').value.trim();
            
            // Only add if at least one field is filled, but warn if partially filled
            if (desc || haz || mit) {
                if (!desc || !haz || !mit) hasEmptyTasks = true;
                payload.tasks.push({ desc: desc, hazards: haz, mitigation: mit });
            }
        });

        if (hasEmptyTasks) {
            const proceed = confirm("One or more of your Job Steps are incomplete. Do you still want to submit?");
            if (!proceed) {
                btnElement.innerHTML = originalBtnText;
                btnElement.disabled = false;
                nextStep(3);
                return;
            }
        }

        // Submit via AJAX Fetch API
        fetch('/api/flha.php?action=save_open', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Redirect to dashboard on success
                window.location.href = '/flha-list';
            } else {
                alert('System Error: ' + (res.message || 'Could not save the assessment.'));
                btnElement.innerHTML = originalBtnText;
                btnElement.disabled = false;
            }
        })
        .catch(err => {
            console.error('Submission Error:', err);
            alert('A network error occurred while communicating with the server.');
            btnElement.innerHTML = originalBtnText;
            btnElement.disabled = false;
        });
    }
</script>
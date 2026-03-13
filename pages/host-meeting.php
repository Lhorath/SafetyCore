<?php
/**
 * Host a Meeting Form - pages/host-meeting.php
 *
 * This management view allows authorized users to host and record safety meetings, 
 * toolbox talks, and tailgate meetings. It features dynamic location-based 
 * employee fetching to accurately log attendance for compliance and training records.
 *
 * Updates in Beta 05:
 * - Initial creation of the Host Meeting form.
 * - Implemented AJAX-driven employee checklist generation based on branch selection.
 * - Added "Select All" functionality and strict client-side validation.
 * - Styled using the Beta 05 Tailwind component library.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   5.0.0 (NorthPoint Beta 05)
 */

// --- 1. Security & Access Control ---
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userRole = $_SESSION['user']['role_name'] ?? '';

// Define roles authorized to host safety meetings
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

// --- 2. Data Fetching (Store Selection) ---
$stores = [];

// Admins, Owners, and Safety Managers can host meetings at any company location.
// Standard managers are restricted to their explicitly assigned stores.
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

<div class="max-w-3xl mx-auto py-8">
    
    <!-- Page Header & Cancel Action -->
    <div class="mb-8 flex flex-col md:flex-row justify-between items-start md:items-center border-b-2 border-primary pb-4 gap-4">
        <div>
            <h2 class="text-3xl font-extrabold text-primary flex items-center tracking-tight">
                <i class="fas fa-bullhorn text-secondary mr-3"></i> Host a Safety Talk
            </h2>
            <p class="text-base text-gray-500 mt-2 font-medium">Log meeting topics, discussion notes, and record verified team attendance.</p>
        </div>
        <a href="/meetings-list" class="btn btn-secondary !px-4 !py-2 text-sm shadow-sm flex items-center hover:shadow transition-all">
            <i class="fas fa-times mr-2 text-gray-400"></i> Cancel
        </a>
    </div>

    <!-- Meeting Form -->
    <form id="hostMeetingForm" class="space-y-6">
        <div class="card p-6 md:p-8 space-y-8 shadow-md border border-gray-200 rounded-xl">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                
                <!-- Branch / Location Selection -->
                <div class="md:col-span-2">
                    <label for="meetingStore" class="form-label">Branch / Location <span class="text-accent-red">*</span></label>
                    <select id="meetingStore" required class="form-input cursor-pointer shadow-sm bg-gray-50 focus:bg-white transition-colors">
                        <option value="" disabled selected>-- Select the Meeting Location --</option>
                        <?php foreach ($stores as $store): ?>
                            <option value="<?php echo $store['id']; ?>">
                                <?php echo htmlspecialchars($store['store_name'] . ' (' . $store['store_number'] . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Meeting Topic -->
                <div class="md:col-span-2">
                    <label for="meetingTopic" class="form-label">Meeting Topic / Subject <span class="text-accent-red">*</span></label>
                    <input type="text" id="meetingTopic" required class="form-input shadow-sm" placeholder="e.g., Proper Lifting Techniques, Ladder Safety, Winter Weather Prep...">
                </div>

                <!-- Date of Meeting -->
                <div class="md:col-span-2 md:w-1/2">
                    <label for="meetingDate" class="form-label">Date of Meeting <span class="text-accent-red">*</span></label>
                    <input type="date" id="meetingDate" required class="form-input shadow-sm" value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                </div>
            </div>

            <!-- Dynamic Attendance Checklist -->
            <div class="border-t border-gray-100 pt-8 mt-2">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center mb-4 gap-2">
                    <div>
                        <label class="form-label mb-0">Record Attendance <span class="text-accent-red">*</span></label>
                        <span class="text-xs text-gray-500 font-normal normal-case">Select the employees present for this safety talk.</span>
                    </div>
                    <button type="button" id="selectAllBtn" class="text-xs font-bold bg-blue-50 text-secondary hover:bg-blue-100 px-3 py-1.5 rounded-lg border border-blue-200 transition-colors hidden shadow-sm">
                        <i class="fas fa-check-double mr-1"></i> Select All
                    </button>
                </div>
                
                <!-- Container populated by JS -->
                <div id="attendanceContainer" class="bg-gray-50 p-6 rounded-xl border border-gray-200 min-h-[120px] text-sm text-gray-500 flex items-center justify-center shadow-inner">
                    <div class="text-center">
                        <i class="fas fa-info-circle text-2xl text-gray-300 mb-2 block"></i>
                        Please select a branch location above to load the employee roster.
                    </div>
                </div>
            </div>

            <!-- Meeting Comments & Notes -->
            <div class="border-t border-gray-100 pt-8 mt-2">
                <label for="meetingComments" class="form-label flex justify-between">
                    <span>Meeting Comments &amp; Notes</span>
                    <span class="text-xs font-normal text-gray-400 normal-case">(Optional)</span>
                </label>
                <textarea id="meetingComments" class="form-input shadow-sm min-h-[120px]" placeholder="Briefly describe what was discussed, any employee concerns raised, or corrective action items assigned..."></textarea>
            </div>
            
        </div>

        <!-- Action Bar -->
        <div class="flex justify-end pt-4 pb-12">
            <button type="submit" id="submitBtn" class="btn btn-primary text-lg px-10 shadow-xl transform hover:-translate-y-1 transition-all flex items-center w-full sm:w-auto justify-center group">
                <i class="fas fa-save mr-3 group-hover:scale-110 transition-transform"></i> Save Meeting Record
            </button>
        </div>
    </form>
</div>

<!-- ==========================================
     Client-Side Logic (AJAX & Form Handling)
     ========================================== -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const storeSelect = document.getElementById('meetingStore');
    const container = document.getElementById('attendanceContainer');
    const selectAllBtn = document.getElementById('selectAllBtn');

    /**
     * Event Listener: Store Selection Change
     * Fetches the list of employees for the selected store to populate the attendance checklist.
     */
    storeSelect.addEventListener('change', function() {
        const storeId = this.value;
        
        if (!storeId) {
            container.innerHTML = `
                <div class="text-center">
                    <i class="fas fa-info-circle text-2xl text-gray-300 mb-2 block"></i>
                    Please select a branch location above to load the employee roster.
                </div>`;
            container.classList.add('items-center', 'justify-center');
            selectAllBtn.classList.add('hidden');
            return;
        }

        // Loading State
        container.innerHTML = '<div class="text-center text-secondary w-full"><i class="fas fa-circle-notch fa-spin text-3xl mb-2 block"></i> Loading roster...</div>';
        container.classList.add('items-center', 'justify-center');
        selectAllBtn.classList.add('hidden');
        
        // Fetch API Request
        fetch(`/api/meetings.php?action=get_store_employees&store_id=${storeId}`)
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                container.classList.remove('items-center', 'justify-center');
                
                if (res.data.length === 0) {
                    container.innerHTML = `
                        <div class="text-center w-full p-4 border border-dashed border-red-300 rounded-lg bg-white">
                            <i class="fas fa-exclamation-triangle text-red-400 text-2xl mb-2 block"></i>
                            <span class="text-red-500 font-bold">No employees found for this location.</span>
                        </div>`;
                    selectAllBtn.classList.add('hidden');
                    return;
                }

                // Render Employee Checkboxes
                let html = '<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 animate-fade-in-up">';
                res.data.forEach(emp => {
                    const pos = emp.employee_position ? `<span class="text-[10px] text-gray-500 block font-normal mt-0.5">${emp.employee_position}</span>` : '';
                    html += `
                        <label class="flex items-start space-x-3 p-3 bg-white rounded-xl border border-gray-200 cursor-pointer hover:border-secondary hover:shadow-md transition-all shadow-sm group">
                            <input type="checkbox" name="attendees[]" value="${emp.id}" class="form-checkbox h-5 w-5 text-secondary rounded mt-0.5 border-gray-300 focus:ring-secondary">
                            <div>
                                <span class="font-bold text-gray-800 text-sm leading-tight group-hover:text-secondary transition-colors">${emp.first_name} ${emp.last_name}</span>
                                ${pos}
                            </div>
                        </label>
                    `;
                });
                html += '</div>';
                
                container.innerHTML = html;
                selectAllBtn.classList.remove('hidden');
                selectAllBtn.textContent = 'Select All'; // Reset button text
                
            } else {
                container.innerHTML = `<div class="text-red-500 font-bold w-full text-center">Error: ${res.message}</div>`;
            }
        })
        .catch(err => {
            console.error('Fetch error:', err);
            container.innerHTML = '<div class="text-red-500 font-bold w-full text-center">Network error while loading the employee roster.</div>';
        });
    });

    /**
     * Event Listener: Select/Deselect All Checkboxes
     */
    selectAllBtn.addEventListener('click', function() {
        const checkboxes = container.querySelectorAll('input[type="checkbox"]');
        if (checkboxes.length === 0) return;
        
        // Determine current state based on if all are currently checked
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
        });
        
        // Toggle button text
        if (allChecked) {
            this.innerHTML = '<i class="fas fa-check-double mr-1"></i> Select All';
        } else {
            this.innerHTML = '<i class="fas fa-times mr-1"></i> Deselect All';
        }
    });

    /**
     * Event Listener: Form Submission
     * Validates attendance and submits the payload to the backend API.
     */
    document.getElementById('hostMeetingForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        // Gather checked attendees
        const attendees = [];
        document.querySelectorAll('input[name="attendees[]"]:checked').forEach(cb => {
            attendees.push(cb.value);
        });

        // Strict Validation
        if (attendees.length === 0) {
            alert('Validation Error: You must select at least one attendee to formally save the meeting record.');
            return;
        }

        const btn = document.getElementById('submitBtn');
        const originalBtnText = btn.innerHTML;
        
        // UI Loading State
        btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-3"></i> Processing Record...';
        btn.disabled = true;

        // Assemble JSON Payload
        const payload = {
            store_id: document.getElementById('meetingStore').value,
            topic: document.getElementById('meetingTopic').value.trim(),
            meeting_date: document.getElementById('meetingDate').value,
            comments: document.getElementById('meetingComments').value.trim(),
            attendees: attendees
        };

        // Submit to API via POST
        fetch('/api/meetings.php?action=save_meeting', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        })
        .then(response => response.json())
        .then(res => {
            if (res.success) {
                // Redirect to the meetings dashboard on success
                window.location.href = '/meetings-list';
            } else {
                alert('System Error: ' + (res.message || 'Failed to save the meeting record.'));
                btn.innerHTML = originalBtnText;
                btn.disabled = false;
            }
        })
        .catch(err => {
            console.error('Submission Error:', err);
            alert('A network error occurred while attempting to save the meeting record.');
            btn.innerHTML = originalBtnText;
            btn.disabled = false;
        });
    });
});
</script>
<?php
/**
 * User Profile Edit Page - pages/profile-edit.php
 *
 * Beta 09 Changes (Audit Fixes):
 *   F-21 — Added server-side minimum password length check (8 characters).
 *           The placeholder text already said "Min. 8 characters" but the
 *           backend never enforced it, so an empty or 1-character password
 *           could be set via a direct POST.
 *   F-02 — CSRF token verified on POST submission.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   9.0.0 (NorthPoint Beta 09)
 */

require_once 'includes/csrf.php';

// ── 1. Auth check ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['user'])) {
    header('Location: /login');
    exit();
}

$userId         = (int)$_SESSION['user']['id'];
$successMessage = '';
$errorMessage   = '';

// ── 2. Handle POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // F-02: CSRF check
    if (!csrf_check($errorMessage)) {
        goto fetch_user; // re-render form with error; skip DB writes
    }

    $firstName       = trim($_POST['first_name']      ?? '');
    $lastName        = trim($_POST['last_name']       ?? '');
    $email           = trim($_POST['email']           ?? '');
    $newPassword     = $_POST['new_password']         ?? '';
    $confirmPassword = $_POST['confirm_password']     ?? '';

    // Validation
    if (empty($firstName) || empty($lastName) || empty($email)) {
        $errorMessage = 'First Name, Last Name, and Email cannot be empty.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (!empty($newPassword) && strlen($newPassword) < 8) {
        // F-21 FIX: enforce minimum password length server-side
        $errorMessage = 'Your new password must be at least 8 characters long.';
    } elseif (!empty($newPassword) && $newPassword !== $confirmPassword) {
        $errorMessage = 'The new passwords do not match.';
    } else {
        $params = [];
        $types  = '';
        $sql    = "UPDATE users SET first_name = ?, last_name = ?, email = ?";
        $params[] = $firstName;
        $params[] = $lastName;
        $params[] = $email;
        $types   .= 'sss';

        if (!empty($newPassword)) {
            $sql     .= ", password = ?";
            $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            $types   .= 's';
        }

        $sql     .= " WHERE id = ?";
        $params[] = $userId;
        $types   .= 'i';

        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param($types, ...$params);

            try {
                if ($stmt->execute()) {
                    $successMessage = 'Your profile has been updated successfully!';
                    $_SESSION['user']['first_name'] = $firstName;
                    $_SESSION['user']['last_name']  = $lastName;
                } else {
                    $errorMessage = 'An error occurred while updating your profile.';
                }
            } catch (mysqli_sql_exception $e) {
                $errorMessage = ($e->getCode() === 1062)
                    ? 'This email address is already in use by another account.'
                    : 'Database error: ' . $e->getMessage();
            }
            $stmt->close();
        } else {
            $errorMessage = 'Failed to prepare the database statement.';
        }
    }
}

// ── 3. Fetch current user data ────────────────────────────────────────────────
fetch_user:
$stmt = $conn->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: /logout.php');
    exit();
}
?>

<div class="max-w-3xl mx-auto">

    <div class="flex justify-between items-center mb-8">
        <h2 class="text-2xl font-bold text-primary border-b-2 border-primary pb-2">Edit Your Profile</h2>
        <a href="/profile" class="text-gray-500 hover:text-primary transition flex items-center text-sm font-medium">
            <i class="fas fa-arrow-left mr-2"></i> Back to Profile
        </a>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-check-circle mr-3"></i>
            <div><p class="font-bold">Success</p><p><?php echo htmlspecialchars($successMessage); ?></p></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm flex items-center">
            <i class="fas fa-exclamation-circle mr-3"></i>
            <div><p class="font-bold">Error</p><p><?php echo htmlspecialchars($errorMessage); ?></p></div>
        </div>
    <?php endif; ?>

    <form action="/profile-edit" method="POST" class="space-y-8">

        <!-- F-02: CSRF token -->
        <?php csrf_field(); ?>

        <!-- Personal Information -->
        <div class="card">
            <h3 class="text-xl font-bold text-accent-gray mb-6 border-b border-gray-100 pb-2">Personal Information</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label" for="first_name">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="form-input"
                           value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                </div>
                <div>
                    <label class="form-label" for="last_name">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="form-input"
                           value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                </div>
                <div class="md:col-span-2">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-input"
                           value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
            </div>
        </div>

        <!-- Password Update -->
        <div class="card border-l-4 border-l-secondary">
            <h3 class="text-xl font-bold text-accent-gray mb-2">Update Password</h3>
            <p class="text-sm text-gray-500 mb-6">Leave these fields blank if you do not want to change your password.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label class="form-label" for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input"
                           autocomplete="new-password" placeholder="Min. 8 characters">
                    <!-- F-21: minlength enforced server-side; HTML attr is a UX hint only -->
                    <p class="text-xs text-gray-400 mt-1">Minimum 8 characters required.</p>
                </div>
                <div>
                    <label class="form-label" for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                           autocomplete="new-password" placeholder="Re-enter password">
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex justify-end space-x-4 pt-4">
            <a href="/profile" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all">
                Save Changes
            </button>
        </div>

    </form>
</div>

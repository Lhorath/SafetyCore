<?php
/**
 * Login Page - pages/login.php
 *
 * Beta 07: Replaced company workspace dropdown with a 4-digit Company Code
 * text input. The company list is no longer exposed to unauthenticated users,
 * closing a minor information-disclosure vector.
 *
 * Error codes (via ?error= URL param):
 *   1       — Generic failure (bad code, email, or password — deliberately vague)
 *   locked  — Too many failed attempts; lockout in effect
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// ── Error message mapping ──────────────────────────────────────────────────────
// Deliberately generic — do not distinguish between wrong code, wrong email,
// wrong password, or access denied. All map to the same message.
$errorMessage = '';
if (isset($_GET['error'])) {
    $errorMessage = match($_GET['error']) {
        'locked' => 'Too many failed attempts. Please wait 30 minutes before trying again.',
        default  => 'Invalid Company ID, email, or password.',
    };
}

// ── Preserve company code across failed attempts (UX only, not sensitive) ──────
// Repopulate the code field so the user doesn't have to retype it.
// The email is NOT repopulated to avoid browser autofill leaking it.
$prefillCode = '';
if (isset($_GET['code'])) {
    // Only accept 4 digits — sanitize before echoing
    $raw = preg_replace('/\D/', '', $_GET['code']);
    if (strlen($raw) === 4) {
        $prefillCode = $raw;
    }
}
?>

<div class="flex items-center justify-center py-12">
    <div class="card w-full max-w-md relative overflow-hidden shadow-xl">

        <!-- Decorative top gradient bar -->
        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-primary to-secondary"></div>

        <!-- Header -->
        <div class="text-center mb-8 mt-4">
            <div class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary/10 mb-4">
                <i class="fas fa-shield-alt text-2xl text-primary"></i>
            </div>
            <h2 class="text-2xl font-bold text-primary">Member Login</h2>
            <p class="text-gray-500 text-sm mt-1">Enter your Company ID and credentials to sign in</p>
        </div>

        <!-- Error Alert -->
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-50 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded text-sm shadow-sm flex items-start gap-3">
                <i class="fas fa-exclamation-circle text-lg mt-0.5 flex-shrink-0"></i>
                <span><?php echo htmlspecialchars($errorMessage); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Form -->
        <form action="/includes/login_process.php" method="POST" class="space-y-5" autocomplete="off" novalidate>
            <?php csrf_field(); ?>

            <!-- Company ID -->
            <div>
                <label for="company_code" class="form-label">
                    Company ID
                </label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                        <i class="fas fa-building text-gray-400"></i>
                    </div>
                    <input
                        type="text"
                        id="company_code"
                        name="company_code"
                        inputmode="numeric"
                        pattern="[0-9]{4}"
                        maxlength="4"
                        required
                        autocomplete="off"
                        class="form-input pl-10 tracking-[0.35em] font-mono text-lg text-center"
                        placeholder="0000"
                        value="<?php echo htmlspecialchars($prefillCode); ?>"
                        aria-describedby="company_code_hint"
                    >
                </div>
                <p id="company_code_hint" class="text-xs text-gray-400 mt-1.5">
                    Your 4-digit Company ID — provided by your administrator.
                </p>
            </div>

            <!-- Divider -->
            <div class="relative my-2">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-gray-200"></div>
                </div>
                <div class="relative flex justify-center text-xs">
                    <span class="bg-white px-3 text-gray-400 font-medium uppercase tracking-wider">Credentials</span>
                </div>
            </div>

            <!-- Email -->
            <div>
                <label for="email" class="form-label">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        required
                        autocomplete="email"
                        class="form-input pl-10"
                        placeholder="name@example.com"
                    >
                </div>
            </div>

            <!-- Password -->
            <div>
                <label for="password" class="form-label">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input
                        type="password"
                        id="password"
                        name="password"
                        required
                        autocomplete="current-password"
                        class="form-input pl-10 pr-12"
                        placeholder="Enter your password"
                    >
                    <!-- Toggle password visibility -->
                    <button
                        type="button"
                        id="togglePassword"
                        class="absolute inset-y-0 right-0 flex items-center pr-4 text-gray-400 hover:text-primary transition-colors focus:outline-none"
                        aria-label="Show or hide password"
                        tabindex="-1"
                    >
                        <i class="fas fa-eye" id="toggleIcon"></i>
                    </button>
                </div>
            </div>

            <!-- Submit -->
            <button
                type="submit"
                class="btn btn-primary w-full shadow-lg hover:shadow-xl transform hover:-translate-y-0.5 transition-all duration-200 flex justify-center items-center gap-2 mt-2"
            >
                <span>Sign In</span>
                <i class="fas fa-arrow-right"></i>
            </button>

        </form>

        <!-- Help text -->
        <p class="text-center text-xs text-gray-400 mt-6 pb-2">
            Don't know your Company ID? Contact your system administrator.
        </p>

    </div>
</div>

<script>
// ── Company code: enforce digits-only input in real time ──────────────────────
document.getElementById('company_code').addEventListener('input', function () {
    // Strip any non-digit characters as the user types
    this.value = this.value.replace(/\D/g, '').slice(0, 4);
});

// Auto-advance focus to email once 4 digits are entered
document.getElementById('company_code').addEventListener('input', function () {
    if (this.value.length === 4) {
        document.getElementById('email').focus();
    }
});

// ── Password visibility toggle ─────────────────────────────────────────────────
document.getElementById('togglePassword').addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    const show = pwd.type === 'password';
    pwd.type   = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
});
</script>

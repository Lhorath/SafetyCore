<?php
/**
 * Contact Page - pages/contact.php
 *
 * This file displays the public contact form for Sentry OHS.
 * It handles form submission, input validation, custom math CAPTCHA, and sends 
 * inquiries directly to the administration via email using the PHP mail() function.
 *
 * Features:
 * - Tailwind CSS styling consistent with the brand's modern aesthetic.
 * - Auto-population of Name/Email if the user is logged in.
 * - Secure email transmission with proper headers.
 * - Custom Math CAPTCHA to prevent automated spam without third-party dependencies.
 *
 * @package   Sentry OHS
 * @author    macweb.ca (sentryohs.com)
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   Version 11.0.0 (sentry ohs launch)
 */

// Ensure session is started for CAPTCHA tracking
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$successMessage = '';
$errorMessage = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check($errorMessage)) {
        // $errorMessage set by csrf_check; do not process form
    } else {
    // Sanitize inputs to prevent injection and whitespace issues
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    $captcha_answer = trim($_POST['captcha_answer'] ?? '');
    
    // Determine context: Is the sender a logged-in user or a guest?
    $loggedInInfo = isset($_SESSION['user']) 
        ? "User ID: " . $_SESSION['user']['id'] . " (" . $_SESSION['user']['first_name'] . ")" 
        : "Guest User";

    // --- Validation ---
    if (empty($name) || empty($email) || empty($subject) || empty($message) || empty($captcha_answer)) {
        $errorMessage = "Please fill out all fields, including the security question.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } elseif (!isset($_SESSION['captcha_result']) || (int)$captcha_answer !== $_SESSION['captcha_result']) {
        $errorMessage = "Incorrect security answer. Please try again.";
    } else {
        // --- Email Configuration ---
        $to = 'support@sentryohs.com'; 
        
        // prepend brand name to subject for easy filtering
        $email_subject = "Sentry OHS Inquiry: " . $subject;
        
        // Construct the email body
        $email_body = "You have received a new message from the Sentry OHS contact form.\n\n";
        $email_body .= "Name: $name\n";
        $email_body .= "Email: $email\n";
        $email_body .= "Status: $loggedInInfo\n";
        $email_body .= "Subject: $subject\n";
        $email_body .= "--------------------------------------------------\n\n";
        $email_body .= "Message:\n$message\n";
        
        // Headers
        // 'From' set to a noreply address on the domain to ensure delivery.
        // 'Reply-To' set to the sender's email for convenience.
        $headers = "From: Sentry OHS <noreply@safety.macweb.ca>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Send the email
        if (mail($to, $email_subject, $email_body, $headers)) {
            $successMessage = "Thank you! Your message has been sent. We will get back to you shortly.";
            // Clear form fields on success so the user can't double-submit easily
            $name = $email = $subject = $message = '';
            // Clear CAPTCHA result
            unset($_SESSION['captcha_result']);
        } else {
            $errorMessage = "An error occurred while sending your message. Please try again later.";
        }
    }
    }
}

// --- Generate New CAPTCHA on page load ---
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha_result'] = $num1 + $num2;
$captcha_question = "What is $num1 + $num2?";
?>

<!-- ==========================================
     PAGE HEADER
     ========================================== -->
<div class="relative bg-slate-900 py-16 border-b border-slate-800 overflow-hidden">
    <!-- Subtle Background Elements -->
    <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_var(--tw-gradient-stops))] from-blue-900/20 via-transparent to-transparent z-0"></div>
    
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 relative z-10 text-center">
        <h1 class="text-4xl md:text-5xl font-extrabold text-white tracking-tight mb-4">
            Contact <span class="text-blue-500">Sentry OHS</span>
        </h1>
        <p class="text-lg text-gray-300 font-light max-w-2xl mx-auto">
            Connect with our team for OHS software support, implementation questions, pricing details, or a product demo.
        </p>
    </div>
</div>

<!-- ==========================================
     MAIN CONTENT
     ========================================== -->
<div class="py-16 bg-gray-50">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Sidebar: Contact Information -->
            <div class="lg:col-span-1 space-y-6">
                
                <!-- Contact Details Card -->
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                    <h3 class="text-xl font-bold text-slate-800 mb-6 border-b border-gray-100 pb-3">Talk to Our Team</h3>
                    
                    <div class="space-y-6">
                        <!-- Address -->
                        <div class="flex items-start group">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-map-marker-alt text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800 mb-1">Head Office</p>
                                <p class="text-sm text-gray-500 leading-relaxed">Moncton, NB<br>Canada</p>
                            </div>
                        </div>

                        <!-- Email -->
                        <div class="flex items-start group">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-envelope text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800 mb-1">Email Support</p>
                                <a href="mailto:support@sentryohs.com" class="text-sm text-gray-500 hover:text-blue-600 transition-colors">support@sentryohs.com</a>
                            </div>
                        </div>

                        <!-- Phone -->
                        <div class="flex items-start group">
                            <div class="flex-shrink-0 w-10 h-10 bg-blue-50 rounded-xl flex items-center justify-center mr-4 group-hover:scale-110 transition-transform">
                                <i class="fas fa-phone text-blue-600 text-lg"></i>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-slate-800 mb-1">Sales and Support</p>
                                <p class="text-sm text-gray-500">(902) 754 1070</p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Demo Callout Box -->
                <div class="bg-gradient-to-br from-slate-800 to-primary rounded-2xl p-8 shadow-md text-white relative overflow-hidden">
                    <!-- Decorative Icon -->
                    <i class="fas fa-desktop absolute -right-4 -bottom-4 text-6xl text-white opacity-5 transform -rotate-12"></i>
                    
                    <h3 class="text-lg font-bold mb-3 flex items-center relative z-10">
                        <i class="fas fa-rocket text-blue-400 mr-2"></i> Book a Demo
                    </h3>
                    <p class="text-sm text-gray-300 leading-relaxed relative z-10">
                        See how Sentry OHS supports hazard reporting, incident management, FLHA workflows, and compliance tracking. Select <strong>"Sales / Demo Request"</strong> in the subject line.
                    </p>
                </div>
            </div>

            <!-- Main Content: Contact Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 md:p-10">
                    
                    <h2 class="text-2xl font-bold text-slate-800 mb-8 border-b border-gray-100 pb-4">Send Us a Message</h2>

                    <!-- Success Alert -->
                    <?php if (!empty($successMessage)): ?>
                        <div class="bg-green-50 border border-green-200 text-green-800 p-4 mb-8 rounded-xl flex items-start">
                            <i class="fas fa-check-circle mt-0.5 mr-3 text-green-500 text-lg flex-shrink-0"></i>
                            <div>
                                <p class="font-bold text-sm">Success</p>
                                <p class="text-sm mt-1"><?php echo htmlspecialchars($successMessage); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Error Alert -->
                    <?php if (!empty($errorMessage)): ?>
                        <div class="bg-red-50 border border-red-200 text-red-800 p-4 mb-8 rounded-xl flex items-start">
                            <i class="fas fa-exclamation-triangle mt-0.5 mr-3 text-accent-red text-lg flex-shrink-0"></i>
                            <div>
                                <p class="font-bold text-sm">Error</p>
                                <p class="text-sm mt-1"><?php echo htmlspecialchars($errorMessage); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <form action="/contact" method="POST" class="space-y-6">
                        <?php csrf_field(); ?>
                        
                        <!-- Row: Name & Email -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="name" class="form-label text-slate-700">Your Name <span class="text-accent-red">*</span></label>
                                <!-- Auto-fill logic: Safely handles missing session keys -->
                                <input type="text" id="name" name="name" required class="form-input shadow-sm bg-gray-50 focus:bg-white" 
                                       value="<?php echo htmlspecialchars($_POST['name'] ?? (isset($_SESSION['user']['first_name']) ? trim($_SESSION['user']['first_name'] . ' ' . ($_SESSION['user']['last_name'] ?? '')) : '')); ?>">
                            </div>
                            <div>
                                <label for="email" class="form-label text-slate-700">Email Address <span class="text-accent-red">*</span></label>
                                <input type="email" id="email" name="email" required class="form-input shadow-sm bg-gray-50 focus:bg-white" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ($_SESSION['user']['email'] ?? '')); ?>">
                            </div>
                        </div>
                        
                        <!-- Row: Subject -->
                        <div>
                            <label for="subject" class="form-label text-slate-700">Subject <span class="text-accent-red">*</span></label>
                            <select id="subject" name="subject" required class="form-input shadow-sm cursor-pointer bg-gray-50 focus:bg-white">
                                <option value="General Inquiry" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                <option value="Sales / Demo Request" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Sales / Demo Request') ? 'selected' : ''; ?>>Sales / Demo Request</option>
                                <option value="Technical Support" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Technical Support') ? 'selected' : ''; ?>>Technical Support</option>
                                <option value="Partnership" <?php echo (isset($_POST['subject']) && $_POST['subject'] === 'Partnership') ? 'selected' : ''; ?>>Partnership Opportunity</option>
                            </select>
                        </div>

                        <!-- Row: Message -->
                        <div>
                            <label for="message" class="form-label text-slate-700">Message <span class="text-accent-red">*</span></label>
                            <textarea id="message" name="message" required class="form-input shadow-sm min-h-[160px] bg-gray-50 focus:bg-white" placeholder="Tell us about your OHS goals, team size, and what support you need."><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                        </div>

                        <!-- Custom Math CAPTCHA -->
                        <div class="bg-blue-50/50 border border-blue-100 rounded-xl p-5 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <div>
                                <label for="captcha_answer" class="form-label text-slate-700 mb-1 flex items-center">
                                    <i class="fas fa-shield-alt text-blue-500 mr-2"></i> Security Check <span class="text-accent-red ml-1">*</span>
                                </label>
                                <p class="text-sm font-bold text-primary"><?php echo $captcha_question; ?></p>
                                <p class="text-xs text-gray-500 mt-1">Please solve the math problem to prove you are human.</p>
                            </div>
                            <div class="w-full sm:w-32">
                                <input type="number" id="captcha_answer" name="captcha_answer" required class="form-input shadow-sm text-center font-bold text-lg" placeholder="=">
                            </div>
                        </div>

                        <!-- Submit Button -->
                        <div class="text-right pt-4">
                            <button type="submit" class="btn bg-blue-600 hover:bg-blue-500 text-white font-bold text-lg px-10 shadow-lg transform hover:-translate-y-1 transition-all w-full sm:w-auto">
                                Send Message <i class="fas fa-paper-plane ml-2"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>
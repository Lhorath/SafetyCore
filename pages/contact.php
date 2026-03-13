<?php
/**
 * Contact Page - pages/contact.php
 *
 * This file displays the public contact form for NorthPoint 360.
 * It handles form submission, input validation, and sends inquiries directly
 * to the administration via email using the PHP mail() function.
 *
 * Features:
 * - Tailwind CSS styling consistent with the brand.
 * - Auto-population of Name/Email if the user is logged in.
 * - Secure email transmission with proper headers.
 *
 * @package   NorthPoint360
 * @author    macweb.ca
 * @copyright Copyright (c) 2026 macweb.ca. All Rights Reserved.
 * @version   2.2.0 (NorthPoint Beta 01)
 */

$successMessage = '';
$errorMessage = '';

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs to prevent injection and whitespace issues
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');
    
    // Determine context: Is the sender a logged-in user or a guest?
    $loggedInInfo = isset($_SESSION['user']) 
        ? "User ID: " . $_SESSION['user']['id'] . " (" . $_SESSION['user']['first_name'] . ")" 
        : "Guest User";

    // --- Validation ---
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $errorMessage = "Please fill out all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        // --- Email Configuration ---
        $to = 'support@macweb.ca'; 
        
        // prepend brand name to subject for easy filtering
        $email_subject = "NorthPoint 360 Inquiry: " . $subject;
        
        // Construct the email body
        $email_body = "You have received a new message from the NorthPoint 360 contact form.\n\n";
        $email_body .= "Name: $name\n";
        $email_body .= "Email: $email\n";
        $email_body .= "Status: $loggedInInfo\n";
        $email_body .= "Subject: $subject\n";
        $email_body .= "--------------------------------------------------\n\n";
        $email_body .= "Message:\n$message\n";
        
        // Headers
        // 'From' set to a noreply address on the domain to ensure delivery.
        // 'Reply-To' set to the sender's email for convenience.
        $headers = "From: NorthPoint 360 <noreply@safety.macweb.ca>\r\n";
        $headers .= "Reply-To: $email\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();

        // Send the email
        if (mail($to, $email_subject, $email_body, $headers)) {
            $successMessage = "Thank you! Your message has been sent. We will get back to you shortly.";
            // Clear form fields on success so the user can't double-submit easily
            $name = $email = $subject = $message = '';
        } else {
            $errorMessage = "An error occurred while sending your message. Please try again later.";
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    
    <!-- Header Section -->
    <div class="text-center mb-12">
        <h2 class="text-3xl font-bold text-primary mb-4">Contact Us</h2>
        <p class="text-gray-500 max-w-lg mx-auto">
            Interested in NorthPoint 360 for your business? Have a question or need support? Send us a message below.
        </p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
        
        <!-- Sidebar: Contact Information -->
        <div class="col-span-1 space-y-6">
            
            <!-- Contact Details Card -->
            <div class="card">
                <h3 class="text-lg font-bold text-primary mb-4 border-b border-gray-200 pb-2">Get in Touch</h3>
                
                <div class="space-y-4">
                    <!-- Address -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8">
                            <i class="fas fa-map-marker-alt text-secondary mt-1 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700">Head Office</p>
                            <p class="text-sm text-gray-500">Moncton, NB<br>Canada</p>
                        </div>
                    </div>

                    <!-- Email -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8">
                            <i class="fas fa-envelope text-secondary mt-1 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700">Email</p>
                            <p class="text-sm text-gray-500">support@macweb.ca</p>
                        </div>
                    </div>

                    <!-- Phone -->
                    <div class="flex items-start">
                        <div class="flex-shrink-0 w-8">
                            <i class="fas fa-phone text-secondary mt-1 text-lg"></i>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-gray-700">Sales & Support</p>
                            <p class="text-sm text-gray-500">(902) 754 1070</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Demo Callout Box -->
            <div class="bg-blue-50 border border-blue-100 rounded-xl p-6 shadow-sm">
                <h3 class="text-sm font-bold text-primary uppercase tracking-wide mb-2">Looking for a demo?</h3>
                <p class="text-xs text-gray-600 leading-relaxed">
                    Our team is happy to walk you through the platform. Select <strong>"Sales / Demo Request"</strong> in the subject line.
                </p>
            </div>
        </div>

        <!-- Main Content: Contact Form -->
        <div class="col-span-1 md:col-span-2">
            <div class="card">
                
                <!-- Success Alert -->
                <?php if (!empty($successMessage)): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6 rounded shadow-sm">
                        <p class="font-bold">Success</p>
                        <p><?php echo htmlspecialchars($successMessage); ?></p>
                    </div>
                <?php endif; ?>
                
                <!-- Error Alert -->
                <?php if (!empty($errorMessage)): ?>
                    <div class="bg-red-100 border-l-4 border-accent-red text-red-700 p-4 mb-6 rounded shadow-sm">
                        <p class="font-bold">Error</p>
                        <p><?php echo htmlspecialchars($errorMessage); ?></p>
                    </div>
                <?php endif; ?>

                <form action="/contact" method="POST" class="space-y-6">
                    
                    <!-- Row: Name & Email -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="form-label">Your Name</label>
                            <!-- Auto-fill logic: Checks POST data first (validation error), then Session data -->
                            <input type="text" id="name" name="name" required class="form-input" 
                                   value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : (isset($_SESSION['user']) ? htmlspecialchars($_SESSION['user']['first_name']) : ''); ?>">
                        </div>
                        <div>
                            <label for="email" class="form-label">Email Address</label>
                            <input type="email" id="email" name="email" required class="form-input" 
                                   value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                    </div>
                    
                    <!-- Row: Subject -->
                    <div>
                        <label for="subject" class="form-label">Subject</label>
                        <select id="subject" name="subject" class="form-input">
                            <option value="General Inquiry">General Inquiry</option>
                            <option value="Sales / Demo Request">Sales / Demo Request</option>
                            <option value="Technical Support">Technical Support</option>
                            <option value="Partnership">Partnership Opportunity</option>
                        </select>
                    </div>

                    <!-- Row: Message -->
                    <div>
                        <label for="message" class="form-label">Message</label>
                        <textarea id="message" name="message" required class="form-input min-h-[150px]" placeholder="How can we help you?"><?php echo isset($_POST['message']) ? htmlspecialchars($_POST['message']) : ''; ?></textarea>
                    </div>

                    <!-- Submit Button -->
                    <div class="text-right">
                        <button type="submit" class="btn btn-primary shadow-lg transform hover:-translate-y-0.5 transition-all duration-200">
                            Send Message <i class="fas fa-paper-plane ml-2"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>
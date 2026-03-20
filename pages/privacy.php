<?php
/**
 * Privacy Policy - pages/privacy.php
 *
 * Describes collection and handling of personal and operational data in
 * the Sentry OHS platform.
 *
 * Jurisdiction: Moncton, New Brunswick, Canada.
 *
 * @package   Sentry OHS
 * @version   Version 11.0.0 (sentry ohs launch)
 */

$lastUpdated = 'March 20, 2026';
?>

<div class="max-w-5xl mx-auto space-y-8">
    <div class="bg-slate-900 rounded-2xl px-8 py-10 text-white shadow-sm border border-slate-800">
        <p class="text-xs uppercase tracking-[0.2em] text-blue-300 font-bold mb-3">Legal</p>
        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-3">Privacy Policy</h1>
        <p class="text-slate-300 leading-relaxed">
            This Privacy Policy explains how Sentry OHS collects, uses, stores, and discloses personal and operational information when
            organizations and their users access our EHS management platform.
        </p>
        <p class="text-sm text-slate-400 mt-4">Last updated: <?php echo htmlspecialchars($lastUpdated); ?></p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-900 leading-relaxed">
        This policy is drafted for general platform use and operational transparency. It may require legal review to align with your
        organization's contractual, regulatory, and industry-specific obligations before production use.
    </div>

    <section class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 space-y-6">
        <article>
            <h2 class="text-xl font-bold text-primary mb-2">1. Scope and Legal Framework</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS operates from Moncton, New Brunswick, Canada. We handle personal information in line with applicable Canadian
                privacy laws, including the Personal Information Protection and Electronic Documents Act (PIPEDA), and apply privacy
                safeguards appropriate for an enterprise safety and compliance platform.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">2. Information We Collect</h2>
            <p class="text-slate-600 leading-relaxed mb-3">
                We collect information required to provide and secure the platform, including:
            </p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li><strong>Account and identity data:</strong> name, email, role, company affiliation, employee profile fields, and login metadata.</li>
                <li><strong>Organization and location data:</strong> company details, branch/store or job site records, supervisor/manager assignment data, and tenant configuration.</li>
                <li><strong>Operational safety data:</strong> hazards, incidents, FLHA records, checklist templates and submissions, meeting records, training records, equipment and inspection logs.</li>
                <li><strong>Attachments and evidence:</strong> files uploaded by users as part of hazard, incident, or operational reporting workflows.</li>
                <li><strong>Technical/security data:</strong> session activity, audit-related logs, CSRF/security tokens, and anti-abuse records such as login attempts.</li>
                <li><strong>Support and communications:</strong> contact form submissions and support correspondence.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">3. How We Use Information</h2>
            <p class="text-slate-600 leading-relaxed mb-3">We use collected information to:</p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Authenticate users, enforce role-based access, and protect tenant boundaries.</li>
                <li>Deliver EHS functions such as hazard/incident workflows, checklists, meetings, training, and reporting.</li>
                <li>Generate operational dashboards and compliance metrics for authorized users.</li>
                <li>Maintain data integrity, investigate misuse, and monitor platform security.</li>
                <li>Provide customer support, communications, and service-related notices.</li>
                <li>Meet legal, regulatory, and audit obligations where applicable.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">4. Data Sharing and Disclosure</h2>
            <p class="text-slate-600 leading-relaxed mb-3">
                Sentry OHS does not sell personal information. We may disclose information:
            </p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Within your organization according to role permissions and tenant-level access controls.</li>
                <li>To service providers acting on our behalf for hosting, security, backup, or support operations, under confidentiality and data protection obligations.</li>
                <li>Where required by law, court order, regulatory request, or to protect rights, safety, and platform integrity.</li>
                <li>In a business transaction context (such as merger or acquisition), subject to appropriate legal safeguards.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">5. Data Storage and Security</h2>
            <p class="text-slate-600 leading-relaxed">
                We apply technical and organizational safeguards appropriate to the sensitivity of EHS and employee-related data,
                including access control, session security, tenant scoping, and secure handling of form submissions and credentials.
                No system can guarantee absolute security, but Sentry OHS uses reasonable safeguards to protect against unauthorized
                access, disclosure, alteration, and loss.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">6. Cookies and Tracking</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS uses essential session cookies and related local browser mechanisms needed for login state, form security,
                and core platform operation. We do not rely on broad advertising-tracker networks for platform access. If non-essential
                analytics or tracking tools are introduced later, this policy will be updated accordingly.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">7. Data Retention</h2>
            <p class="text-slate-600 leading-relaxed">
                We retain information only as long as necessary for legitimate business purposes, contractual obligations, legal
                requirements, incident/audit traceability, and safety compliance recordkeeping. Retention periods vary by data type
                (for example, account records, investigation records, training history, and system logs). Data may be deleted or
                anonymized when no longer required, subject to legal and contractual limits.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">8. Your Privacy Rights</h2>
            <p class="text-slate-600 leading-relaxed mb-3">
                Subject to applicable law and contractual constraints, individuals may request:
            </p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Access to personal information held about them.</li>
                <li>Correction of inaccurate or incomplete personal information.</li>
                <li>Deletion, restriction, or de-identification where lawful and operationally feasible.</li>
                <li>Information about how personal information has been used or disclosed.</li>
            </ul>
            <p class="text-slate-600 leading-relaxed mt-3">
                For tenant-managed accounts, requests may first be handled through your employer or account administrator, who controls
                many operational records submitted in the course of workplace safety activities.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">9. International and Cross-Border Processing</h2>
            <p class="text-slate-600 leading-relaxed">
                Depending on infrastructure and service-provider arrangements, data may be processed outside New Brunswick or outside
                Canada. Where this occurs, we require contractual and organizational protections appropriate to the sensitivity of the
                information and applicable legal requirements.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">10. Changes to This Privacy Policy</h2>
            <p class="text-slate-600 leading-relaxed">
                We may update this Privacy Policy periodically to reflect legal, operational, or platform changes. Revised versions become
                effective upon posting unless otherwise stated. Continued use of Sentry OHS after a policy update indicates acceptance of
                the revised policy.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">11. Privacy Contact</h2>
            <p class="text-slate-600 leading-relaxed">
                For privacy requests, access/correction inquiries, or concerns about data handling, contact:
                <a class="text-secondary font-semibold hover:underline" href="mailto:support@sentryohs.com">support@sentryohs.com</a><br>
                Sentry OHS Privacy Team, Moncton, New Brunswick, Canada
            </p>
        </article>
    </section>
</div>

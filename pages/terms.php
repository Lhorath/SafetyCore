<?php
/**
 * Terms of Service - pages/terms.php
 *
 * Governs use of the Sentry OHS platform, including tenant/company data
 * ownership, user responsibilities, and liability terms.
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
        <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight mb-3">Terms of Service</h1>
        <p class="text-slate-300 leading-relaxed">
            These Terms of Service govern access to and use of the Sentry OHS platform, an Environment, Health, and Safety (EHS)
            software service used for compliance workflows, incident and hazard management, safety meetings, training records,
            checklist execution, and related reporting.
        </p>
        <p class="text-sm text-slate-400 mt-4">Last updated: <?php echo htmlspecialchars($lastUpdated); ?></p>
    </div>

    <div class="bg-amber-50 border border-amber-200 rounded-xl p-5 text-sm text-amber-900 leading-relaxed">
        These terms are provided for general business use and may not reflect all obligations specific to your organization.
        Sentry OHS recommends independent legal review before production deployment or commercial rollout.
    </div>

    <section class="bg-white border border-slate-200 rounded-2xl p-6 md:p-8 space-y-6">
        <article>
            <h2 class="text-xl font-bold text-primary mb-2">1. Acceptance of Terms</h2>
            <p class="text-slate-600 leading-relaxed">
                By creating an account, accessing, or using Sentry OHS, you agree to be bound by these Terms of Service and any
                applicable policies referenced herein, including our Privacy Policy. If you are using Sentry OHS on behalf of a company,
                contractor, or other entity, you represent that you have authority to bind that entity.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">2. Description of Service</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS is a cloud-enabled EHS management platform that supports safety and compliance operations such as:
            </p>
            <ul class="mt-3 space-y-2 text-slate-600 list-disc list-inside">
                <li>Hazard and near-miss submissions with evidence attachments and corrective action tracking.</li>
                <li>Incident reporting and investigation records.</li>
                <li>Field Level Hazard Assessment (FLHA) creation, worker participation, and closeout workflows.</li>
                <li>Digital meetings/toolbox talks, attendance records, and safety communication logs.</li>
                <li>Equipment inventory, pre-shift inspections, and checklist builder functionality.</li>
                <li>Training and certification matrix management with expiry tracking.</li>
                <li>Role-based dashboards and compliance metrics for supervisors and administrators.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">3. User Accounts and Responsibilities</h2>
            <p class="text-slate-600 leading-relaxed mb-3">
                Users are responsible for all activity under their credentials and must maintain account security.
                You agree to:
            </p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Provide accurate registration and profile information.</li>
                <li>Keep passwords confidential and notify Sentry OHS promptly of suspected unauthorized access.</li>
                <li>Use the platform only for lawful workplace safety, compliance, and operational purposes.</li>
                <li>Ensure reports, incident records, and checklist entries are complete and truthful to the best of your knowledge.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">4. Roles, Permissions, and Tenant Administration</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS uses role-based permissions (for example: employee, supervisor, company administrator, and platform
                administrator) to control access to modules and data. Company administrators are responsible for assigning permissions
                within their organization and must ensure access is limited to authorized personnel. Each company account is logically
                scoped as a separate tenant and users may access only data available within their authorized company context.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">5. Acceptable Use</h2>
            <p class="text-slate-600 leading-relaxed mb-3">You must not:</p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Attempt to access data outside your authorized tenant, location, or role permissions.</li>
                <li>Upload malicious code, interfere with system integrity, or attempt unauthorized security testing.</li>
                <li>Use the service for unlawful surveillance, discriminatory practices, or violation of employment law.</li>
                <li>Misrepresent incidents, hazards, attendance, training records, or compliance evidence.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">6. Data Ownership and Intellectual Property</h2>
            <p class="text-slate-600 leading-relaxed mb-3">
                As between the parties:
            </p>
            <ul class="space-y-2 text-slate-600 list-disc list-inside">
                <li>Your organization retains ownership of customer-submitted operational and personal data entered into the platform, including user records, incidents, hazards, FLHA records, meeting logs, checklists, and training information.</li>
                <li>Sentry OHS retains ownership of the software, codebase, interface design, documentation, trademarks, and all related intellectual property.</li>
                <li>You grant Sentry OHS a limited license to host, process, back up, and transmit your data solely to provide, secure, and improve the service.</li>
            </ul>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">7. Data Collection, Use, and Retention</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS collects account information, business configuration data, and operational safety/compliance records required
                to deliver platform functionality. Personal information is handled in accordance with applicable Canadian privacy
                requirements, including the Personal Information Protection and Electronic Documents Act (PIPEDA). Data is retained only as
                long as reasonably required for contractual service delivery, legal compliance, audit continuity, dispute resolution, and
                legitimate safety recordkeeping needs, unless a different retention period is contractually agreed.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">8. Service Availability and Modifications</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS may update, modify, or improve features from time to time, including changes to modules, interfaces, or
                workflows. While commercially reasonable efforts are made to maintain service availability, uninterrupted operation is not
                guaranteed. Planned maintenance, emergency security updates, or third-party infrastructure issues may result in temporary
                downtime.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">9. Limitation of Liability</h2>
            <p class="text-slate-600 leading-relaxed">
                To the maximum extent permitted by law, Sentry OHS and its affiliates, officers, employees, and contractors are not liable
                for indirect, incidental, consequential, special, or punitive damages, including lost profits, lost business opportunities,
                or loss of data arising from use of or inability to use the service. The platform supports safety and compliance workflows
                but does not replace professional judgment, legal advice, site supervision, or statutory obligations under occupational
                health and safety law.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">10. Termination and Suspension</h2>
            <p class="text-slate-600 leading-relaxed">
                Sentry OHS may suspend or terminate access where there is a material breach of these Terms, non-payment, unlawful use,
                security risk, or misuse that threatens other users or platform integrity. Your organization may request account closure
                subject to any active contractual commitments and lawful retention requirements. Upon termination, access to the service may
                be disabled and data handling will proceed in accordance with applicable agreements and retention obligations.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">11. Governing Law and Jurisdiction</h2>
            <p class="text-slate-600 leading-relaxed">
                These Terms are governed by the laws of the Province of New Brunswick and the federal laws of Canada applicable therein,
                without regard to conflict of law principles. The parties attorn to the courts located in or serving Moncton, New
                Brunswick, Canada, for disputes arising from or related to these Terms or the use of Sentry OHS.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">12. Changes to These Terms</h2>
            <p class="text-slate-600 leading-relaxed">
                We may revise these Terms from time to time. Updated versions become effective when posted on this page unless stated
                otherwise. Continued use of Sentry OHS after updates are posted constitutes acceptance of the revised Terms.
            </p>
        </article>

        <article>
            <h2 class="text-xl font-bold text-primary mb-2">13. Contact</h2>
            <p class="text-slate-600 leading-relaxed">
                Questions about these Terms may be directed to:
                <a class="text-secondary font-semibold hover:underline" href="mailto:support@sentryohs.com">support@sentryohs.com</a><br>
                Sentry OHS, Moncton, New Brunswick, Canada
            </p>
        </article>
    </section>
</div>

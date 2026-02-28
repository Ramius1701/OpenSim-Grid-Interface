<?php
$title = "DMCA";
include 'include/header.php';
?>

<main class="content-card">

        <section>
            <h1>DMCA Policy for <?php echo SITE_NAME; ?></h1>
            <p><strong>Last Updated:</strong> <?php echo date("F d, Y"); ?></p>
            <p>
                This DMCA policy describes how <strong><?php echo SITE_NAME; ?></strong> (hereinafter "we", "us", "our grid")
                responds to complaints regarding copyright infringement under the <strong>Digital Millennium Copyright Act (DMCA)</strong>.
            </p>
            <p>
                If you believe that content within our OpenSimulator grid violates your copyright,
                you can file a <strong>DMCA complaint</strong> by following the steps below.
            </p>
        </section>

        <section>
            <h2>1. Submitting a DMCA Complaint (Takedown Request)</h2>
            <p>
                If you believe that your copyrighted material has been used without permission in <strong><?php echo SITE_NAME; ?></strong>,
                please send a written complaint to our <strong>DMCA Agent</strong> at:
            </p>
            <p>
                📩 <strong>Email:</strong> support@<?php echo strtolower(SITE_NAME); ?>.com<br>
                📬 <strong>Postal Address:</strong> Sample Street 123, 12345 Sample City<br>
                📞 <strong>Phone:</strong> <br>
                <strong>Discord:</strong> GridSupport <?php echo BASE_URL; ?>
            </p>

            <h3>Your DMCA complaint must include the following information:</h3>
            <ol>
                <li><strong>Identification of the copyrighted work:</strong><br>
                    A detailed description of the copyrighted work (e.g., a screenshot, link, or documentation).</li>

                <li><strong>Location of the infringing content:</strong><br>
                    The exact location of the content in our grid, including:
                    <ul>
                        <li>Region name</li>
                        <li>Coordinates (if possible)</li>
                        <li>UUID or asset ID of the affected object</li>
                        <li>Screenshot or description</li>
                    </ul>
                </li>

                <li><strong>Contact information:</strong><br>
                    Your name, address, email address, and phone number.</li>

                <li><strong>Statement of infringement:</strong><br>
                    A statement that you have a <strong>good faith belief</strong> that the use is not authorized by the copyright owner,
                    their agent, or the law (e.g., fair use).</li>

                <li><strong>Sworn statement:</strong><br>
                    A statement that the information in your complaint is accurate and that you are the copyright owner or authorized to act on their behalf.</li>

                <li><strong>Digital or physical signature:</strong><br>
                    An electronic or handwritten signature of the copyright owner or their authorized representative.</li>
            </ol>
        </section>

        <section>
            <h2>2. Our Response to a DMCA Complaint</h2>
            <p>Upon receiving a valid DMCA complaint, we will:</p>
            <ul>
                <li><strong>Temporarily remove or disable access</strong> to the allegedly infringing content.</li>
                <li>Notify the user who provided the content about the complaint.</li>
                <li>If the affected user submits a <strong>counter-notice</strong> (see Section 3), inform the copyright owner.</li>
            </ul>
            <p>
                If the copyright owner does not take legal action within <strong>10 business days</strong> of our notification,
                we may restore the removed content.
            </p>
        </section>

        <section>
            <h2>3. Submitting a Counter-Notice</h2>
            <p>
                If you believe that the removed content does not infringe copyright or you had permission to use it,
                you can submit a <strong>counter-notice</strong>.
            </p>
            <p>Please send your counter-notice to <strong>support@<?php echo strtolower(SITE_NAME); ?>.com</strong> with the following information:</p>
            <ol>
                <li><strong>Identification of the removed content:</strong><br>
                    The original location of the content (region, coordinates, UUID, screenshots).</li>

                <li><strong>Statement of good faith:</strong><br>
                    A statement that you have a <strong>good faith belief</strong> that the removal was due to a mistake or misidentification.</li>

                <li><strong>Consent to jurisdiction:</strong><br>
                    If you are outside the USA, a statement that you consent to the jurisdiction of US federal courts.</li>

                <li><strong>Sworn statement:</strong><br>
                    A statement that the information in your counter-notice is accurate and that you are responsible for any legal consequences.</li>

                <li><strong>Digital or physical signature:</strong><br>
                    Your signature or that of your authorized representative.</li>
            </ol>
            <p>
                Upon receiving a valid counter-notice, the removed content may be restored within <strong>10–14 days</strong>,
                unless the original complainant files a lawsuit.
            </p>
        </section>

        <section>
            <h2>4. Consequences for Repeated Violations</h2>
            <p>Users who repeatedly infringe copyright may:</p>
            <ul>
                <li>Be <strong>warned</strong>,</li>
                <li><strong>Temporarily suspended</strong>,</li>
                <li>Or <strong>permanently banned</strong> from the grid.</li>
            </ul>
            <p>We reserve the right to suspend accounts without prior warning in cases of severe violations.</p>
        </section>

        <section>
            <h2>5. No Liability for User-Generated Content</h2>
            <p>
                Our grid provides a platform for virtual interactions and hosts user-generated content.
                We are not liable for copyrighted materials uploaded by users.
                All users are responsible for the content they upload.
            </p>
            <p>
                However, we actively work to <strong>remove infringing content</strong> upon receiving a <strong>valid DMCA complaint</strong>.
            </p>
        </section>

        <section>
            <h2>6. Contact for Further Questions</h2>
            <p>If you have questions about this DMCA policy, you can reach us at <strong>contact@<?php echo strtolower(SITE_NAME); ?>.com</strong>.</p>
        </section>

        <section>
            <h3>Legal Notice:</h3>
            <p>
                This template is for <strong>informational purposes only</strong> and does <strong>not constitute legal advice</strong>.
                If you need a legally reviewed DMCA policy, please consult an attorney.
            </p>
        </section>

</main>

<?php include_once "include/footer.php"; ?>

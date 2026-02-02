<?php
$title = "TOS";
include_once 'include/header.php';

/**
 * Safe fallbacks so this page renders even if some constants are missing.
 */
if (!defined('SITE_NAME')) define('SITE_NAME', 'Casperia');
if (!defined('BASE_URL'))  define('BASE_URL', '/');

$site       = SITE_NAME;
$site_html  = htmlspecialchars($site, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$host = parse_url(BASE_URL, PHP_URL_HOST);
if (!$host) { $host = 'example.com'; } // fallback for relative BASE_URL

// Allow central overrides in config
$minAge     = defined('TOS_MIN_AGE') ? TOS_MIN_AGE : '16';
$inactive   = defined('ACCOUNT_INACTIVITY_WINDOW') ? ACCOUNT_INACTIVITY_WINDOW : '6 months';

$supportEmail = defined('SUPPORT_EMAIL') ? SUPPORT_EMAIL : "support@{$host}";
$contactEmail = defined('CONTACT_EMAIL') ? CONTACT_EMAIL : "contact@{$host}";
$dmcaEmail    = defined('DMCA_EMAIL')    ? DMCA_EMAIL    : $supportEmail;
?>

<main class="content-card content-card-read">

  <section>
    <h1><i class="bi bi-file-earmark-text me-2"></i> Terms of Service for <?php echo $site_html; ?></h1>
    <p><strong>Last Updated:</strong> <?php echo date("F d, Y"); ?></p>
    <p>
      Welcome to <strong><?php echo $site_html; ?></strong>! These Terms of Service govern your use of our virtual world,
      which is based on OpenSimulator. By registering and using our service, you agree to these terms.
      If you do not agree, you may not use our grid.
    </p>
  </section>

  <section>
    <h2>1. General Provisions</h2>
    <p><strong>1.1 Scope:</strong> These terms apply to all users of our OpenSimulator grid, including guests and registered users.</p>
    <p><strong>1.2 Changes:</strong> We reserve the right to change these terms at any time. Changes take effect upon publication on our website.</p>
    <p><strong>1.3 Consent:</strong> By using our grid, you agree to the current terms of service.</p>
  </section>

  <section>
    <h2>2. User Accounts and Access</h2>
    <strong>2.1 Registration:</strong>
    <ul>
      <li>You must be at least <strong><?php echo htmlspecialchars($minAge); ?> years old</strong> to create an account.</li>
      <li>Providing accurate and complete information is required.</li>
      <li>Only one account per person is allowed unless we explicitly permit multiple accounts.</li>
    </ul>

    <strong>2.2 Account Security:</strong>
    <ul>
      <li>You are responsible for the security of your account and password.</li>
      <li>If you suspect unauthorized access to your account, notify us immediately.</li>
    </ul>

    <strong>2.3 Account Suspension &amp; Deletion:</strong>
    <ul>
      <li>We reserve the right to suspend or delete your account if you violate these TOS.</li>
      <li>Inactive accounts may be deleted after <strong><?php echo htmlspecialchars($inactive); ?></strong> without prior notice.</li>
    </ul>
  </section>

  <section>
    <h2>3. Virtual Content and Economy</h2>
    <strong>3.1 Ownership of Content:</strong>
    <ul>
      <li>Content you create or upload remains your intellectual property.</li>
      <li>However, you grant us the right to use, host, and manage your content within the grid.</li>
    </ul>

    <strong>3.2 Copyright and Licensing:</strong>
    <ul>
      <li>Uploading copyrighted content without permission is prohibited.</li>
      <li>If a user violates copyright, we reserve the right to remove the content and suspend the account.</li>
    </ul>

    <strong>3.3 Virtual Currency &amp; Transactions:</strong>
    <ul>
      <li>If our grid uses a virtual currency (e.g., Gloebits, Podex, OMC), you acknowledge that it has no real monetary value.</li>
      <li>We are not responsible for losses or technical issues related to virtual currencies or transactions.</li>
    </ul>
  </section>

  <section>
    <h2>4. Code of Conduct</h2>
    <strong>4.1 Allowed and Prohibited Content:</strong>
    <ul>
      <li>No <strong>harassment, hate speech, racist, or sexually explicit content</strong> that violates applicable law.</li>
      <li>No <strong>spam, fraud, or impersonation</strong> of other users/admins.</li>
      <li>No <strong>hacking, griefing, or disrupting other users</strong> through scripts, exploits, or attacks.</li>
    </ul>

    <strong>4.2 Regional and Parcel Rules:</strong>
    <ul>
      <li>Region/parcel owners may set their own rules as long as they do not violate these TOS.</li>
      <li>Admins reserve the right to remove disruptive content or regions.</li>
    </ul>
  </section>

  <section>
    <h2>5. Privacy and Data Storage</h2>
    <strong>5.1 Stored Data:</strong>
    <ul>
      <li>We store your account information, IP address, avatar data, and in-world interactions for administrative purposes.</li>
      <li>Personal data will not be shared with third parties unless required by law.</li>
    </ul>

    <strong>5.2 Use of Third Parties:</strong>
    <ul>
      <li>If our grid uses external services like <strong>Hypergrid teleports, Gloebits, or PayPal</strong>, their privacy policies apply additionally.</li>
      <li>When leaving our grid via Hypergrid teleports, your data may be processed by other grids.</li>
    </ul>
  </section>

  <section>
    <h2>6. DMCA Policy (Copyright Protection)</h2>
    <p>If you believe that content on our grid violates your copyright, you can file a <strong>DMCA complaint</strong>:</p>

    <strong>6.1 How to Submit a DMCA Notice:</strong>
    <p>Send a written complaint to <strong><?php echo htmlspecialchars($dmcaEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong> with the following information:</p>
    <ol>
      <li>A description of the copyrighted work that has been infringed.</li>
      <li>The exact location of the infringing content (region, coordinates, UUID, screenshots).</li>
      <li>Your contact details (name, email, address, phone number).</li>
      <li>A statement that you are acting in good faith and that the content is being used unlawfully.</li>
      <li>A sworn statement that your information is accurate.</li>
    </ol>

    <strong>6.2 Response to DMCA Notices:</strong>
    <ul>
      <li>We will remove verified violations.</li>
      <li>If you have been falsely accused of copyright infringement, you can submit a counter-notice.</li>
    </ul>
  </section>

  <section>
    <h2>7. Disclaimer and Termination of Service</h2>
    <strong>7.1 Disclaimer:</strong>
    <ul>
      <li>Our grid is provided as-is. We do not guarantee <strong>uninterrupted, error-free, or continuous use</strong>.</li>
      <li>We are not liable for <strong>data loss, hacks, or technical issues</strong>.</li>
    </ul>

    <strong>7.2 Termination of Service:</strong>
    <ul>
      <li>We reserve the right to terminate the grid or parts of it at any time.</li>
      <li>In the event of closure, there is no entitlement to refunds of virtual balances or content.</li>
    </ul>
  </section>

  <section>
    <h2>8. Contact</h2>
    <p>If you have questions about these terms of service, you can reach us at
      <strong><?php echo htmlspecialchars($contactEmail, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); ?></strong>.
    </p>
  </section>

  <section>
    <strong>Acceptance of Terms</strong>
    <p>
      By using <strong><?php echo $site_html; ?></strong>, you agree to these Terms of Service.
      If you do not agree, please discontinue use of the grid and contact us to close your account.
    </p>
  </section>

</main>

<?php include_once "include/" . FOOTER_FILE; ?>

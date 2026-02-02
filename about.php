<?php
$title = "About";
include_once 'include/header.php';
include_once 'include/viewer_context.php';

if (!empty($IS_VIEWER)) {
    echo '';
}
?>

<main class="content-card content-card-read">

        <section>
            <h1><i class="bi bi-info-circle me-2"></i> About Us</h1>
            <p>Welcome to the <?php echo SITE_NAME; ?> website! Here you will find information about our grid and the services we provide to our community.</p>
        </section>

        <section>
            <h2><i class="bi bi-exclamation-triangle me-2"></i> Disclaimer</h2>
            <p>Use of this website and our virtual grid is at your own risk. We do not guarantee the accuracy, completeness, or timeliness of the information provided on this site.</p>
        </section>

        <section>
            <h3>Legal Disclaimer</h3>
            <p>
                The information on this website is provided "as is" without any warranties, express or implied. We disclaim all liability for damages arising directly or indirectly from the use of this website or our services.
            </p>
            <p>
                This includes, without limitation, damages due to lost data, lost profits, business interruption, or any other commercial damages or losses, regardless of whether we have been advised of the possibility of such damages.
            </p>
            <p>
                This website may contain links to external websites operated by third parties, over whose content we have no control. We assume no responsibility for the content, accuracy, or availability of information on these external websites. The respective provider or operator of the linked pages is solely responsible for their content.
            </p>
        </section>

        <section>
            <h3>Virtual Worlds with OpenSimulator</h3>
            <p>
                OpenSimulator is an open-source software platform that enables the creation and operation of virtual 3D worlds. These worlds can be used for various purposes, including education, social interaction, creative expression, and business activities.
            </p>
            <p>
                OpenSimulator is compatible with the Second Life protocol, which means that users can access our virtual worlds using Second Life-compatible viewers such as Firestorm, Singularity, and others.
            </p>
            <p>
                OpenSimulator provides a flexible and powerful platform that enables users to design and customize their own virtual environments. This includes the creation of landscapes, buildings, objects, avatars, and even complex simulations. The software also supports scripting languages (LSL and OSSL), allowing users to implement interactive elements, automation, and advanced functionality in their worlds.
            </p>
            <p>
                The virtual worlds hosted on our grid are user-generated, and the content is created and managed by the users themselves. This means that the responsibility for the content shared in these worlds lies with the respective users. We are not responsible for user-generated content created or shared in these virtual worlds, though we reserve the right to remove content that violates our Terms of Service.
            </p>
            <p>
                The use of OpenSimulator and our virtual grid is at your own risk. We recommend that users carefully read and follow our Terms of Service, DMCA Policy, and Privacy Policy. Additionally, users should be aware that virtual worlds may contain content not suitable for all age groups, and that they should take appropriate measures to protect their privacy and safety in such environments.
            </p>
            <p>
                For more information about OpenSimulator, please visit the official website: <a href="http://opensimulator.org/" target="_blank">OpenSimulator.org</a> or the <a href="http://opensimulator.org/wiki/Main_Page" target="_blank">OpenSimulator Wiki</a>.
            </p>
        </section>

        <section>
            <h3>Community and Support</h3>
            <p>
                Our grid is built on the principles of community collaboration, creativity, and open standards. We welcome residents from all backgrounds who wish to explore, create, and connect in our virtual world.
            </p>
            <p>
                If you have questions or need assistance, please don't hesitate to contact us at <strong>support@<?php echo strtolower(SITE_NAME); ?>.com</strong> or visit our support resources.
            </p>
        </section>

</main>

<?php include_once "include/" . FOOTER_FILE; ?>

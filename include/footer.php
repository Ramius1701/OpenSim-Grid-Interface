    </div> <!-- End main-container -->

    <!-- Footer (hide in embedded viewer mode / splash screens) -->
    <?php if (empty($IS_VIEWER)): ?>
    <footer class="footer-modern">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; 2008 - <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                        Powered by <i class="bi bi-box-arrow-up-right"></i> <a href="http://opensimulator.org/" target="_blank" class="text-white-50">OpenSimulator </a>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-links">
                        <a href="tos.php" class="text-white-50 me-3"><i class="bi bi-file-text"></i> ToS</a>
                        <a href="dmca.php" class="text-white-50 me-3"><i class="bi bi-shield"></i> DMCA</a>
                        <a href="gridstatusrss.php" class="text-white-50 me-3"><i class="bi bi-rss"></i> RSS</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Additional JavaScript for enhanced functionality -->
    <script>
        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add Bootstrap validation to all forms
            const forms = document.querySelectorAll('form.needs-validation');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (window.bootstrap && bootstrap.Alert) { bootstrap.Alert.getOrCreateInstance(alert).close(); }
                }, 5000);
            });
            
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
        
        // Show loading overlay for navigation
        function showLoading() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255,255,255,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                overlay.remove();
            }, 3000);
        }
        
        // Add loading to navigation links
        document.querySelectorAll('a:not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"]):not([target="_blank"])').forEach(link => {
            link.addEventListener('click', showLoading);
        });
    </script>
    <?php if (defined('THEME_LATE_OVERRIDES') && THEME_LATE_OVERRIDES && defined('THEME_BOOTSTRAP_COMPAT') && THEME_BOOTSTRAP_COMPAT): ?>
<style id="theme-late-overrides">
/* Late overrides to beat per-page inline styles */
:root{
  --muted-color: color-mix(in srgb, var(--primary-color), transparent 35%);
}

/* Text utilities */
.is-web .text-dark, .is-viewer .text-dark,
.is-web .text-black, .is-viewer .text-black,
.is-web .text-body, .is-viewer .text-body,
.is-web .text-body-emphasis, .is-viewer .text-body-emphasis,
.is-web .link-dark, .is-viewer .link-dark,
.is-web .link-body-emphasis, .is-viewer .link-body-emphasis{
  color: var(--primary-color) !important;
}
.is-web .text-muted, .is-viewer .text-muted,
.is-web .text-secondary, .is-viewer .text-secondary,
.is-web .link-secondary, .is-viewer .link-secondary{
  color: var(--muted-color) !important;
}

/* Light backgrounds */
.is-web .bg-light, .is-viewer .bg-light,
.is-web .bg-white, .is-viewer .bg-white,
.is-web .bg-body-secondary, .is-viewer .bg-body-secondary,
.is-web .bg-body-tertiary, .is-viewer .bg-body-tertiary{
  background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
  color: var(--primary-color) !important;
}

/* Buttons */
<?php if (defined('THEME_PILL_BUTTONS') && THEME_PILL_BUTTONS): ?>
.is-web .btn, .is-viewer .btn,
.is-web .btn-theme, .is-viewer .btn-theme,
.is-web .btn-theme-outline, .is-viewer .btn-theme-outline{
  border-radius: 9999px !important;
}
.is-web .btn-light, .is-viewer .btn-light{
  background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
  border-color: color-mix(in srgb, var(--primary-color), transparent 80%) !important;
  color: var(--primary-color) !important;
}
<?php endif; ?>

/* Tables */
.is-web .table > :not(caption) > * > *, .is-viewer .table > :not(caption) > * > *{
  color: var(--primary-color) !important;
}
</style>
<?php endif; ?>
</body>
</html>    </div> <!-- End main-container -->

    <!-- Footer (hide in embedded viewer mode / splash screens) -->
    <?php if (empty($IS_VIEWER)): ?>
    <footer class="footer-modern">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; 2008 - <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                        Powered by <a href="http://opensimulator.org/" target="_blank" class="text-white-50"> <i class="bi bi-box-arrow-up-right"></i> OpenSimulator </a>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-links">
                        <a href="tos.php" class="text-white-50 me-3"><i class="bi bi-file-text"></i> ToS</a>
                        <a href="dmca.php" class="text-white-50 me-3"><i class="bi bi-shield"></i> DMCA</a>
                        <a href="gridstatusrss.php" class="text-white-50 me-3"><i class="bi bi-rss"></i> RSS</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>
    <?php endif; ?>

    <!-- Additional JavaScript for enhanced functionality -->
    <script>
        // Enhanced form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Add Bootstrap validation to all forms
            const forms = document.querySelectorAll('form.needs-validation');
            forms.forEach(function(form) {
                form.addEventListener('submit', function(event) {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                });
            });
            
            // Auto-hide alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    if (window.bootstrap && bootstrap.Alert) { bootstrap.Alert.getOrCreateInstance(alert).close(); }
                    }, 5000);
            });
            
            // Add smooth scrolling
            document.querySelectorAll('a[href^="#"]').forEach(anchor => {
                anchor.addEventListener('click', function (e) {
                    e.preventDefault();
                    const target = document.querySelector(this.getAttribute('href'));
                    if (target) {
                        target.scrollIntoView({
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                });
            });
        });
        
        // Show loading overlay for navigation
        function showLoading() {
            const overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div>';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(255,255,255,0.8);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;
            document.body.appendChild(overlay);
            
            setTimeout(() => {
                overlay.remove();
            }, 3000);
        }
        
        // Add loading to navigation links
        document.querySelectorAll('a:not([href^="#"]):not([href^="mailto:"]):not([href^="tel:"]):not([target="_blank"])').forEach(link => {
            link.addEventListener('click', showLoading);
        });
    </script>
    <?php if (defined('THEME_LATE_OVERRIDES') && THEME_LATE_OVERRIDES && defined('THEME_BOOTSTRAP_COMPAT') && THEME_BOOTSTRAP_COMPAT): ?>
<style id="theme-late-overrides">
/* Late overrides to beat per-page inline styles */
:root{
  --muted-color: color-mix(in srgb, var(--primary-color), transparent 35%);
}

/* Text utilities */
.is-web .text-dark, .is-viewer .text-dark,
.is-web .text-black, .is-viewer .text-black,
.is-web .text-body, .is-viewer .text-body,
.is-web .text-body-emphasis, .is-viewer .text-body-emphasis,
.is-web .link-dark, .is-viewer .link-dark,
.is-web .link-body-emphasis, .is-viewer .link-body-emphasis{
  color: var(--primary-color) !important;
}
.is-web .text-muted, .is-viewer .text-muted,
.is-web .text-secondary, .is-viewer .text-secondary,
.is-web .link-secondary, .is-viewer .link-secondary{
  color: var(--muted-color) !important;
}

/* Light backgrounds */
.is-web .bg-light, .is-viewer .bg-light,
.is-web .bg-white, .is-viewer .bg-white,
.is-web .bg-body-secondary, .is-viewer .bg-body-secondary,
.is-web .bg-body-tertiary, .is-viewer .bg-body-tertiary{
  background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
  color: var(--primary-color) !important;
}

/* Buttons */
<?php if (defined('THEME_PILL_BUTTONS') && THEME_PILL_BUTTONS): ?>
.is-web .btn, .is-viewer .btn,
.is-web .btn-theme, .is-viewer .btn-theme,
.is-web .btn-theme-outline, .is-viewer .btn-theme-outline{
  border-radius: 9999px !important;
}
.is-web .btn-light, .is-viewer .btn-light{
  background-color: color-mix(in srgb, var(--card-bg), var(--primary-color) 5%) !important;
  border-color: color-mix(in srgb, var(--primary-color), transparent 80%) !important;
  color: var(--primary-color) !important;
}
<?php endif; ?>

/* Tables */
.is-web .table > :not(caption) > * > *, .is-viewer .table > :not(caption) > * > *{
  color: var(--primary-color) !important;
}
</style>
<?php endif; ?>
</body>
</html>
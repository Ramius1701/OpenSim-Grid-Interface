    </div> <!-- End main-container -->

    <!-- Footer -->
    <footer class="footer-modern">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <span class="text-muted">
                        &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. 
                        Powered by <a href="http://opensimulator.org/" target="_blank" class="text-white-50">OpenSimulator</a>
                    </span>
                </div>
                <div class="col-md-6 text-md-end">
                    <div class="footer-links">
                        <a href="include/tos.php" class="text-white-50 me-3"><i class="bi bi-file-text"></i> ToS</a>
                        <a href="include/dmca.php" class="text-white-50 me-3"><i class="bi bi-shield"></i> DMCA</a>
                        <a href="gridstatusrss.php" class="text-white-50" target="_blank"><i class="bi bi-rss"></i> RSS</a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

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
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
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
</body>
</html>
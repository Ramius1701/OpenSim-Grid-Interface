<?php
// OpenSim Webinterface Setup Assistant
// This file helps with the initial configuration
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OpenSim Webinterface Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .setup-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .setup-card {
            background: rgba(255,255,255,0.95);
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #2c3e50, #34495e);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .setup-content {
            padding: 2rem;
        }
        .step {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
            padding: 1rem;
            margin-bottom: 1rem;
            border-radius: 0 8px 8px 0;
        }
        .code-block {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 1rem;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            margin: 1rem 0;
            overflow-x: auto;
        }
        .success-icon {
            color: #28a745;
            font-size: 4rem;
        }
        .warning-icon {
            color: #ffc107;
            font-size: 4rem;
        }
    </style>
</head>
<body>
    <div class="setup-container">
        <div class="setup-card">
            <div class="setup-header">
                <i class="bi bi-gear-fill" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <h1>OpenSim Webinterface Setup</h1>
                <p class="mb-0">Welcome! Let's configure your OpenSimulator web interface.</p>
            </div>
            
            <div class="setup-content">
                <?php
                $setup_status = [
                    'env_exists' => file_exists('include/env.php'),
                    'config_exists' => file_exists('include/config.php'),
                    'images_dir' => is_dir('images'),
                    'cache_dir' => is_dir('cache'),
                    'writable' => is_writable('include/')
                ];
                
                $all_good = array_reduce($setup_status, function($carry, $item) {
                    return $carry && $item;
                }, true);
                ?>
                
                <?php if ($all_good): ?>
                    <!-- Setup Complete -->
                    <div class="text-center mb-4">
                        <i class="bi bi-check-circle-fill success-icon"></i>
                        <h2 class="text-success mt-3">Setup Complete!</h2>
                        <p class="text-muted">Your OpenSim Webinterface is ready to use.</p>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <a href="welcome.php" class="btn btn-success btn-lg">
                            <i class="bi bi-house-fill"></i> Go to Homepage
                        </a>
                        <a href="welcomesplashpage_modern.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-star-fill"></i> Try Modern Interface
                        </a>
                    </div>
                    
                <?php else: ?>
                    <!-- Setup Required -->
                    <div class="text-center mb-4">
                        <i class="bi bi-exclamation-triangle-fill warning-icon"></i>
                        <h2 class="text-warning mt-3">Configuration Needed</h2>
                        <p class="text-muted">Please complete the following steps to finish setup.</p>
                    </div>
                    
                    <!-- Configuration Steps -->
                    <div class="step">
                        <h5>
                            <?php if ($setup_status['env_exists']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            <?php endif; ?>
                            Step 1: Database Configuration
                        </h5>
                        
                        <?php if (!$setup_status['env_exists']): ?>
                            <p>Create the database configuration file:</p>
                            <div class="code-block">
cp include/env.example.php include/env.php</div>
                            <p>Then edit <code>include/env.php</code> with your database credentials:</p>
                            <div class="code-block">
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'your_opensim_db_user');
define('DB_PASSWORD', 'your_opensim_db_password');
define('DB_NAME', 'your_opensim_database');</div>
                        <?php else: ?>
                            <p class="text-success mb-0">✓ Database configuration file exists</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="step">
                        <h5>
                            <?php if ($setup_status['config_exists']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-x-circle-fill text-danger"></i>
                            <?php endif; ?>
                            Step 2: Website Configuration
                        </h5>
                        
                        <?php if (!$setup_status['config_exists']): ?>
                            <p>Create the main configuration file:</p>
                            <div class="code-block">
cp include/config.example.php include/config.php</div>
                            <p>Edit <code>include/config.php</code> and update:</p>
                            <ul>
                                <li><code>BASE_URL</code> - Your website URL</li>
                                <li><code>SITE_NAME</code> - Your grid name</li>
                                <li><code>HEADER_FILE</code> - Choose your template (use 'headerModern.php' for the new design)</li>
                            </ul>
                        <?php else: ?>
                            <p class="text-success mb-0">✓ Main configuration file exists</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="step">
                        <h5>
                            <?php if ($setup_status['images_dir']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                            <?php endif; ?>
                            Step 3: Images Directory
                        </h5>
                        
                        <?php if (!$setup_status['images_dir']): ?>
                            <p>Create the images directory for slideshow:</p>
                            <div class="code-block">mkdir images</div>
                            <p>Add your slideshow images to this directory (JPG, PNG, GIF formats supported).</p>
                        <?php else: ?>
                            <p class="text-success mb-0">✓ Images directory exists</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="step">
                        <h5>
                            <?php if ($setup_status['cache_dir']): ?>
                                <i class="bi bi-check-circle-fill text-success"></i>
                            <?php else: ?>
                                <i class="bi bi-exclamation-triangle-fill text-warning"></i>
                            <?php endif; ?>
                            Step 4: Cache Directory
                        </h5>
                        
                        <?php if (!$setup_status['cache_dir']): ?>
                            <p>Create the cache directory:</p>
                            <div class="code-block">mkdir cache
chmod 755 cache</div>
                        <?php else: ?>
                            <p class="text-success mb-0">✓ Cache directory exists</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="alert alert-info">
                        <h6><i class="bi bi-info-circle"></i> Additional Notes:</h6>
                        <ul class="mb-0">
                            <li>Make sure your web server has read/write permissions to the cache directory</li>
                            <li>For security, ensure your database user has only necessary permissions</li>
                            <li>The new modern interface is available at <code>*_modern.php</code> files</li>
                            <li>Test database connectivity after configuration</li>
                        </ul>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button onclick="window.location.reload()" class="btn btn-primary">
                            <i class="bi bi-arrow-clockwise"></i> Check Setup Again
                        </button>
                    </div>
                <?php endif; ?>
                
                <!-- OpenSimulator Configuration -->
                <div class="mt-4 p-3 bg-light rounded">
                    <h6><i class="bi bi-server"></i> OpenSimulator Configuration</h6>
                    <p class="mb-2">Add these lines to your <code>Robust.HG.ini</code> file:</p>
                    <div class="code-block" style="font-size: 0.85rem;">
Tip: Casperia Prime also includes compatibility shims for older filenames (maptile.php, searchservice.php, createavatar.php, etc.) if you still have old configs.

MapTileURL = "${Const|BaseURL}:${Const|PublicPort}/oswebinterface/gridmap.php"
SearchURL = "${Const|BaseURL}:${Const|PublicPort}/oswebinterface/ossearch.php"
DestinationGuide = "${Const|BaseURL}/oswebinterface/guide.php"
AvatarPicker = "${Const|BaseURL}/oswebinterface/avatarpicker.php"
welcome = ${Const|BaseURL}/oswebinterface/welcomesplashpage.php
about = ${Const|BaseURL}/oswebinterface/about.php
register = ${Const|BaseURL}/oswebinterface/register.php
help = ${Const|BaseURL}/oswebinterface/help.php</div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
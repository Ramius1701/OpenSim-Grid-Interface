<?php
$title = "UI Test";
include_once 'include/header.php';
?>

<div class="content-card">
    <h1>UI Test - This should be displayed with Bootstrap 5.</h1>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> If you see this message with a green background and icon, the modern layout is working.
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Test Card</h5>
        </div>
        <div class="card-body">
            <p>This is a test for the modern Bootstrap 5 layout.</p>
            <button class="btn btn-primary">Test Button</button>
        </div>
    </div>
</div>

<?php include_once "include/" . FOOTER_FILE; ?>
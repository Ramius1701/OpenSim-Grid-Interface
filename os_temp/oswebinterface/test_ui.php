<?php
$title = "UI Test";
include_once 'include/headerModern.php';
?>

<div class="content-card">
    <h1>UI Test - Dies sollte mit Bootstrap 5 angezeigt werden</h1>
    <div class="alert alert-success">
        <i class="bi bi-check-circle"></i> Wenn Sie diese Meldung mit grünem Hintergrund und Icon sehen, funktioniert das moderne Layout.
    </div>
    
    <div class="card">
        <div class="card-header bg-primary text-white">
            <h5 class="mb-0">Test Card</h5>
        </div>
        <div class="card-body">
            <p>Dies ist ein Test für das moderne Bootstrap 5 Layout.</p>
            <button class="btn btn-primary">Test Button</button>
        </div>
    </div>
</div>

<?php include_once 'include/footerModern.php'; ?>
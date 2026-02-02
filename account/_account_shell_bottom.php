                </div>
            </div>

        </div>
    </div>
</div>

<script>
// Fix header navigation links when this page is served from /account/*
// This keeps header/footer nav (which uses root-relative file names) working from /account/.
// It will NOT touch account section links because those links add data-no-rewrite="1".
document.addEventListener('DOMContentLoaded', function () {
    var base = '../';
    document.querySelectorAll('a[href]').forEach(function (a) {
        if (a.hasAttribute('data-no-rewrite')) return;
        var href = a.getAttribute('href');
        if (!href) return;
        // Ignore absolute URLs, root paths, anchors, javascript:, and already-prefixed relative paths
        if (/^(?:[a-z]+:|\/|#|javascript:|\.\/|\.\.\/)/i.test(href)) return;
        a.setAttribute('href', base + href);
    });
});
</script>

<?php
// Shared footer closes HTML/body and scripts
require_once __DIR__ . '/../include/' . FOOTER_FILE;
?>

Casperia Prime - Support/Tickets Schema Fix v2
Fixes fatal error when ws_tickets table is missing contact_email.
What it does:
 - On page load, checks INFORMATION_SCHEMA for ws_tickets.contact_email.
 - If missing, attempts ALTER TABLE to add it.
 - If ALTER fails (no permissions), falls back safely:
   * Admin list shows blank email
   * Guest ticket email is stored inside the message body.
 - Adds px-0 to container-fluid on these pages for better alignment.

Install:
 - Extract into your Casperia web root (same folder that contains support.php and admin/)
 - Overwrite existing files.
 - If you still see the old error, restart Apache/PHP (Laragon) once.

Confirm:
 - View Source on support.php and search for 'CASPERIA_SUPPORT_TICKETS_SCHEMA_FIX v2'

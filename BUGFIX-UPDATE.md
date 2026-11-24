# Pawfee bug-fix patch

Apply over the existing pawfee-customer-flow-update installation. Back up project and database first. Extract this ZIP into C:\xampp\htdocs\pawfee, overwrite only matching files; keep .git, vendor, uploads and includes/mail.local.php. No schema reimport is required. If your DB settings differ, preserve them in config.php.

Customer: http://localhost/pawfee/index.php?page=login
Admin: http://localhost/pawfee/index.php?page=login&workspace=admin
The customer and admin now use separate named cookies, so both can be open in normal browser tabs. Multiple customer accounts still require different profiles or a private window. Sign in again after patching.

Test: Ctrl+F5, choose Large and Oat milk for a drink with those price deltas. Check the price before adding, then verify the cart, quantity controls and removal. Confirm logout hides the cart, login restores it, and admin remains signed in. Place a test order with mock payment/COD, then advance through permitted statuses. Return refunds require Item Received, and are treated as a single final refund (partial amount allowed, no subsequent refund).

Changes include CSRF-safe quantity controls, regular form submission for add/remove, recalculated variant prices, preservation of variant IDs on product edits, unavailable option detection, cart locking and checkout stock validation, session isolation, POST logout, password reset token invalidation, per-session verification attempt limits and resend cooldown, order transitions and refund checks.

Validation: 45 PHP files linted; JavaScript syntax checked; isolated PHP checks for variant pricing and order transitions passed. No live MySQL/XAMPP or browser testing performed.

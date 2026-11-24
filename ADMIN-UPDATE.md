# Pawfee admin UI update

Based on the most recent working pawfee.zip supplied by the team. Customer pages and the shared storefront stylesheet are preserved (cream #F5F2E8). The admin-only stylesheet is loaded by the admin shell. All seven navigation groups use the existing front controller and role checks. Products, categories, inventory and fulfillment views are enhanced; dashboard, customer, return, employee, reporting and coupon functions use the existing database. No database migration is required. The separate courier screen is retired; administrator and staff manage shipping updates. Existing courier database roles/records are retained for compatibility.

## Installation
1. Back up the current project and database. Preserve `includes/mail.local.php`, `vendor/`, and any uploaded product images locally; do not share SMTP credentials.
2. Extract this archive outside htdocs first, then copy the updated files into `C:\xampp\htdocs\pawfee`. Remove the old `courier/` folder if present. Do not overwrite local SMTP credentials or uploaded images.
3. Run `composer install` if vendor is absent. Open http://localhost/pawfee/index.php?page=admin and sign in with an existing admin account.
4. Verify sender `pawfeeshop123@gmail.com` in the local mail configuration. Test using `C:\xampp\php\php.exe test-mail.php recipient@example.com`.

## Scope and limitations
This is a functional admin UI iteration, not a pixel-perfect reproduction of all prototype screens. Conversion/cart analytics, variant-level stock and granular employee permissions are not implemented. Existing report tables, refund review forms and coupon settings are retained. Payment/refund processing remains simulated where the previous system simulated it. The cart/session, checkout and other previously identified issues are intentionally deferred. PHP syntax checked; live database, email, browser and end-to-end operation still need testing on XAMPP.

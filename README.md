# Pawfee Shop — PHP + MySQL e-commerce (XAMPP)

## 1. Setup (5 minutes)
1. Install XAMPP, open the **XAMPP Control Panel**, start **Apache** and **MySQL**.
2. Copy the `pawfee` folder into `C:\xampp\htdocs\` (so you have `C:\xampp\htdocs\pawfee\index.php`).
3. Open `config.php` — defaults work for a fresh XAMPP (user `root`, empty password, folder `/pawfee`).
4. Visit **http://localhost/pawfee/install.php** and click **Install / reset database**.
5. Open **http://localhost/pawfee/index.php**. Open the project folder in VS Code to edit.

| Role     | Email                  | Password       |
|----------|------------------------|----------------|
| Admin    | admin@pawfee.test      | Admin@123      |
| Staff    | staff@pawfee.test      | Staff@123      |
| Courier  | courier@pawfee.test    | Courier@123    |
| Customer | customer@pawfee.test   | Customer@123   |

Delete `install.php` (or set `APP_DEBUG` to `false`) before sharing the site.

## 2. Replacing images (no code changes)
See `assets/images/README.txt`. In short, drop files in with these names:
- `assets/images/logo.png`, `assets/images/hero.jpg`
- `assets/images/products/<product-slug>.jpg` (gallery: `<slug>-2.jpg` … `-4.jpg`)
- `assets/images/categories/<category-slug>.jpg`

Admin > Products also supports uploads (stored in `/uploads/products`, take priority).

## 3. Folder map
```
index.php            front controller / router  (?page=shop, ?page=admin&section=orders)
config.php           DB, currency, delivery fee, store info, mail driver
database.sql         schema + catalog seed       install.php  installer + demo data
includes/            db, helpers, auth, mailer, cart, orders (services), layout
pages/               storefront + auth + account pages
admin/               dashboard, products, categories, inventory, orders, customers, returns, reports, staff, settings(coupons)
courier/index.php    courier deliveries
assets/css|js|images stylesheet, script, replaceable images
```

## 4. What is implemented
Register (2-step) + email code verification, login (email or username, throttled), forgot/reset password, shop search/filter/sort/pagination,
product variants with live price, DB-backed cart (30-day cookie), checkout with transaction + stock locking + single-use token,
mock payment (success / failure / cancel), order timeline, cancel/return requests, refunds, inventory log with negative-stock guard,
admin CRUD + reports, courier workflow, notifications stored in DB (preview at `?page=mail`).

Notes: "Inventory" is `products.stock` + `inventory_transactions`. SMTP email is implemented using Brevo SMTP and PHPMailer; see [EMAIL-SETUP.md](EMAIL-SETUP.md) for dependency installation, private credentials, and testing. Passwords use `password_hash`; all queries use prepared statements; output is escaped; forms use CSRF tokens.

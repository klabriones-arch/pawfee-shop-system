-- Pawfee Shop schema + catalog seed. Run via install.php (recommended) or phpMyAdmin import.
CREATE DATABASE IF NOT EXISTS pawfee_shop CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE pawfee_shop;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS refunds, return_requests, order_status_history, shipments, payments, order_items, orders,
  coupons, cart_items, carts, inventory_transactions, product_variants, product_images, products, categories,
  addresses, password_resets, email_verifications, notifications, user_roles, roles, users;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(30) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  first_name VARCHAR(60) NOT NULL,
  last_name VARCHAR(60) NOT NULL,
  username VARCHAR(60) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  phone VARCHAR(30) NOT NULL,
  dob DATE NULL,
  gender VARCHAR(30) NULL,
  password_hash VARCHAR(255) NOT NULL,
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login DATETIME NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_roles (
  user_id INT UNSIGNED NOT NULL,
  role_id TINYINT UNSIGNED NOT NULL,
  PRIMARY KEY (user_id, role_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE email_verifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_resets (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  token_hash CHAR(64) NOT NULL,
  expires_at DATETIME NOT NULL,
  used TINYINT(1) NOT NULL DEFAULT 0,
  INDEX (token_hash),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE addresses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  label VARCHAR(40) NOT NULL DEFAULT 'Home',
  recipient VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  street VARCHAR(200) NOT NULL,
  barangay VARCHAR(100) NOT NULL,
  city VARCHAR(100) NOT NULL,
  province VARCHAR(100) NOT NULL,
  postal_code VARCHAR(10) NOT NULL,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  INDEX (user_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  description VARCHAR(255) NULL,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  sort_order INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category_id INT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  slug VARCHAR(170) NOT NULL UNIQUE,
  tagline VARCHAR(200) NULL,
  description TEXT NULL,
  sku VARCHAR(50) NOT NULL UNIQUE,
  type ENUM('drink','food','merch') NOT NULL DEFAULT 'food',
  badge VARCHAR(40) NULL,
  price DECIMAL(10,2) NOT NULL,
  cost DECIMAL(10,2) NULL,
  stock INT NOT NULL DEFAULT 0,
  low_stock_threshold INT NOT NULL DEFAULT 5,
  featured TINYINT(1) NOT NULL DEFAULT 0,
  status ENUM('active','archived') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (category_id), INDEX (status), INDEX (price),
  FOREIGN KEY (category_id) REFERENCES categories(id),
  CONSTRAINT chk_stock CHECK (stock >= 0)
) ENGINE=InnoDB;

CREATE TABLE product_images (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  path VARCHAR(255) NOT NULL,
  is_primary TINYINT(1) NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE product_variants (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  group_name VARCHAR(40) NOT NULL,
  label VARCHAR(80) NOT NULL,
  price_delta DECIMAL(10,2) NOT NULL DEFAULT 0,
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  sort_order INT NOT NULL DEFAULT 0,
  INDEX (product_id),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE inventory_transactions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  change_qty INT NOT NULL,
  previous_stock INT NOT NULL,
  new_stock INT NOT NULL,
  reason VARCHAR(150) NOT NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (product_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE carts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  token CHAR(32) NOT NULL UNIQUE,
  user_id INT UNSIGNED NULL,
  coupon_code VARCHAR(40) NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE cart_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  variant_ids VARCHAR(100) NOT NULL DEFAULT '',
  option_label VARCHAR(200) NOT NULL DEFAULT '',
  unit_price DECIMAL(10,2) NOT NULL,
  qty INT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_cart_line (cart_id, product_id, variant_ids),
  FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE coupons (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('percent','fixed') NOT NULL,
  value DECIMAL(10,2) NOT NULL,
  min_purchase DECIMAL(10,2) NOT NULL DEFAULT 0,
  expires_at DATE NULL,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE orders (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_no VARCHAR(30) NOT NULL UNIQUE,
  user_id INT UNSIGNED NOT NULL,
  customer_name VARCHAR(150) NOT NULL,
  customer_email VARCHAR(150) NOT NULL,
  customer_phone VARCHAR(30) NOT NULL,
  billing_address TEXT NULL,
  shipping_address TEXT NULL,
  delivery_method ENUM('standard','pickup') NOT NULL DEFAULT 'standard',
  subtotal DECIMAL(10,2) NOT NULL,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL,
  coupon_code VARCHAR(40) NULL,
  status ENUM('Pending','Confirmed','Processing','Packed','Shipped','Out for Delivery','Delivered','Cancelled','Failed','Returned','Refunded') NOT NULL DEFAULT 'Pending',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id), INDEX (status), INDEX (created_at),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE order_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NULL,
  product_name VARCHAR(150) NOT NULL,
  sku VARCHAR(50) NOT NULL,
  variant_label VARCHAR(200) NOT NULL DEFAULT '',
  unit_price DECIMAL(10,2) NOT NULL,
  qty INT UNSIGNED NOT NULL,
  line_total DECIMAL(10,2) NOT NULL,
  INDEX (order_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  method ENUM('cod','mock_online') NOT NULL,
  status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending',
  amount DECIMAL(10,2) NOT NULL,
  reference VARCHAR(60) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE shipments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL UNIQUE,
  courier_id INT UNSIGNED NULL,
  status ENUM('Assigned','Out for Delivery','Delivered','Failed') NOT NULL DEFAULT 'Assigned',
  attempts INT NOT NULL DEFAULT 0,
  remarks VARCHAR(255) NULL,
  delivered_at DATETIME NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (courier_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (courier_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  status VARCHAR(30) NOT NULL,
  remarks VARCHAR(255) NULL,
  updated_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE return_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  order_id INT UNSIGNED NOT NULL,
  user_id INT UNSIGNED NOT NULL,
  type ENUM('cancellation','return') NOT NULL,
  reason VARCHAR(100) NOT NULL,
  description TEXT NULL,
  status ENUM('Requested','Under Review','Approved','Rejected','Item Received','Refunded','Closed') NOT NULL DEFAULT 'Requested',
  admin_remarks VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX (order_id), INDEX (status),
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE refunds (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  return_request_id INT UNSIGNED NULL,
  order_id INT UNSIGNED NOT NULL,
  amount DECIMAL(10,2) NOT NULL,
  reason VARCHAR(150) NULL,
  processed_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (order_id),
  FOREIGN KEY (return_request_id) REFERENCES return_requests(id) ON DELETE SET NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  recipient_email VARCHAR(150) NOT NULL,
  type VARCHAR(40) NOT NULL,
  subject VARCHAR(200) NOT NULL,
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX (user_id), INDEX (type)
) ENGINE=InnoDB;

-- ---------- Seed: roles, categories, products, variants, coupons ----------
INSERT INTO roles (name) VALUES ('customer'),('admin'),('staff'),('courier');

INSERT INTO categories (name, slug, description, sort_order) VALUES
 ('Coffee & coolers','coffee-coolers','Hot and iced coffee favorites',1),
 ('Tea & matcha','tea-matcha','Teas, matcha and chai',2),
 ('Fresh bakes','fresh-bakes','Baked fresh every morning',3),
 ('Sandwiches','sandwiches','Hearty lunch favorites',4),
 ('Desserts','desserts','Sweet treats',5),
 ('Merchandise','merchandise','Pawfee goodies',6);

INSERT INTO products (category_id,name,slug,tagline,description,sku,type,badge,price,cost,stock,low_stock_threshold,featured) VALUES
 (1,'Pawfee latte','pawfee-latte','Espresso meets silky steamed milk.','Our signature espresso, balanced with velvety steamed milk and a soft layer of foam. A comforting café classic, made just the way you like it.','PF-LAT',  'drink','Bestseller',145,45,50,10,1),
 (1,'Caramel cold brew','caramel-cold-brew','Slow-steeped coffee, a caramel swirl.','Cold brew steeped for 18 hours and finished with a ribbon of house caramel.','PF-CCB','drink','Café favorite',130,40,40,10,1),
 (1,'Flat white','flat-white','Bold, smooth and velvety.','A double ristretto with microfoam steamed milk.','PF-FLW','drink',NULL,140,42,40,10,0),
 (1,'Iced americano','iced-americano','Crisp espresso over ice.','Two shots of espresso over cold filtered water and ice.','PF-IAM','drink',NULL,110,25,40,10,0),
 (2,'Cloud nine matcha','cloud-nine-matcha','Ceremonial matcha, creamy oat milk.','Whisked ceremonial-grade matcha with creamy oat milk.','PF-MAT','drink','Plant-based',165,60,30,8,1),
 (2,'Honey chai latte','honey-chai-latte','Spiced tea, steamed milk, honey.','Slow-simmered chai with honey and steamed milk.','PF-CHA','drink',NULL,150,50,30,8,0),
 (3,'Purrfect choc-chip cookie','purrfect-choc-chip-cookie','Golden edges. A soft, gooey center.','Baked fresh daily with dark chocolate chunks and sea salt.','PF-COK','food','Freshly baked',95,28,60,10,1),
 (3,'Butter croissant','butter-croissant','Flaky, buttery layers.','Laminated by hand, baked until golden.','PF-CRO','food',NULL,110,35,25,6,0),
 (4,'Garden club sandwich','garden-club-sandwich','Fresh greens, tomato & herby cream cheese.','Toasted sourdough, crisp greens, tomato and herby cream cheese.','PF-GCS','food','Lunch favorite',185,70,20,5,1),
 (4,'Ham & cheese melt','ham-cheese-melt','Warm, gooey and golden.','Smoked ham and melted cheddar on toasted bread.','PF-HCM','food',NULL,175,65,20,5,0),
 (5,'Paw-print brownie','paw-print-brownie','Rich chocolate with a playful paw top.','Fudgy dark chocolate brownie finished with a paw print.','PF-BRO','food','Sweet treat',105,30,45,8,1),
 (5,'Basque cheesecake slice','basque-cheesecake-slice','Creamy with a caramelized top.','Burnt-top Basque cheesecake, served by the slice.','PF-BCS','food',NULL,160,55,3,5,0),
 (6,'Pawfee tote bag','pawfee-tote-bag','Carry your coffee, carry the cuddles.','Heavy cotton tote printed with the Pawfee logo.','PF-TOT','merch',NULL,350,120,15,5,0);

INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Size','Small · 8 oz',-20,0,1 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Size','Regular · 12 oz',0,1,2 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Size','Large · 16 oz',30,0,3 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Milk','Whole milk',0,1,1 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Milk','Oat milk',25,0,2 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Milk','Almond milk',25,0,3 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Serve it','Hot',0,1,1 FROM products WHERE type='drink';
INSERT INTO product_variants (product_id,group_name,label,price_delta,is_default,sort_order)
  SELECT id,'Serve it','Iced',0,0,2 FROM products WHERE type='drink';

INSERT INTO coupons (code,type,value,min_purchase,expires_at,active) VALUES
 ('PAWS10','percent',10,200,'2030-12-31',1),
 ('WELCOME50','fixed',50,300,'2030-12-31',1),
 ('EXPIRED5','percent',5,0,'2020-01-01',1);

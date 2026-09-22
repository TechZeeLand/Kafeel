-- ============================================================
-- Store database schema
-- ============================================================
SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- customers
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  phone VARCHAR(30) DEFAULT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  email_verified TINYINT(1) NOT NULL DEFAULT 0,
  email_verify_token VARCHAR(64) DEFAULT NULL,
  email_verify_sent_at DATETIME DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS addresses (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  label VARCHAR(50) NOT NULL DEFAULT 'Home',
  full_name VARCHAR(120) NOT NULL,
  phone VARCHAR(30) NOT NULL,
  line1 VARCHAR(200) NOT NULL,
  city VARCHAR(100) NOT NULL,
  state VARCHAR(100) DEFAULT NULL,
  zip VARCHAR(20) DEFAULT NULL,
  country VARCHAR(100) NOT NULL DEFAULT 'Bangladesh',
  is_default TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- admins
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admins (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  name VARCHAR(120) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('owner','staff') NOT NULL DEFAULT 'staff',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  phone VARCHAR(40) DEFAULT NULL,
  email VARCHAR(160) DEFAULT NULL,
  address VARCHAR(500) DEFAULT NULL,
  blood_group VARCHAR(4) DEFAULT NULL,
  gender VARCHAR(10) DEFAULT NULL,
  id_number VARCHAR(20) DEFAULT NULL,
  id_type VARCHAR(20) NOT NULL DEFAULT 'nid',
  date_of_birth DATE DEFAULT NULL,
  facebook_url VARCHAR(255) DEFAULT NULL,
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_change_password TINYINT(1) NOT NULL DEFAULT 0,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_admin_email (email),
  UNIQUE KEY uniq_admin_nid (id_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One optional private document per staff member. Kept in the database (not in the public
-- uploads folder) and only served through admin/staff_document.php to owners and to that person.
CREATE TABLE IF NOT EXISTS admin_documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  mime VARCHAR(100) NOT NULL,
  size INT NOT NULL,
  data LONGBLOB NOT NULL,
  uploaded_by INT DEFAULT NULL,
  uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_doc_admin (admin_id),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One profile picture per staff member (resized, metadata stripped). Private: served only to owners and to that person.
CREATE TABLE IF NOT EXISTS admin_photos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  mime VARCHAR(50) NOT NULL,
  size INT NOT NULL,
  data MEDIUMBLOB NOT NULL,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_photo_admin (admin_id),
  FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- ---------------------------------------------------------------
-- catalog
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL,
  slug VARCHAR(120) NOT NULL UNIQUE,
  description TEXT,
  image VARCHAR(255) DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT DEFAULT NULL,
  name VARCHAR(180) NOT NULL,
  slug VARCHAR(200) NOT NULL UNIQUE,
  sku VARCHAR(60) DEFAULT NULL,
  short_desc VARCHAR(255) DEFAULT NULL,
  tags VARCHAR(600) DEFAULT NULL,
  description TEXT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  compare_price DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  weight_grams INT NOT NULL DEFAULT 500,
  height_mm INT DEFAULT NULL,
  width_mm INT DEFAULT NULL,
  depth_mm INT DEFAULT NULL,
  color VARCHAR(60) DEFAULT NULL,
  image_main VARCHAR(255) DEFAULT NULL,
  youtube_url VARCHAR(255) DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  is_featured TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  FULLTEXT KEY ft_search (name, short_desc, description)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS product_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  image_path VARCHAR(255) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Optional per-product variants (e.g. color / size combinations). A product
-- with no rows here is sold as-is; price_delta is added to the base price.
CREATE TABLE IF NOT EXISTS product_variants (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  color VARCHAR(60) DEFAULT NULL,
  size VARCHAR(60) DEFAULT NULL,
  sku VARCHAR(60) DEFAULT NULL,
  price_delta DECIMAL(10,2) NOT NULL DEFAULT 0,
  stock INT NOT NULL DEFAULT 0,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  sort_order INT NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Selectable colors and sizes of a product (one row each). Colors carry a
-- swatch + preview image; sizes carry dimensions/weight overrides (NULL =
-- inherit the product's own value) and may carry a preview image too.
-- Stock and price adjustments live per combination in product_variants.
CREATE TABLE IF NOT EXISTS product_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  kind ENUM('color','size') NOT NULL,
  name VARCHAR(60) NOT NULL,
  swatch VARCHAR(7) DEFAULT NULL,
  image VARCHAR(255) DEFAULT NULL,
  weight_grams INT DEFAULT NULL,
  height_mm INT DEFAULT NULL,
  width_mm INT DEFAULT NULL,
  depth_mm INT DEFAULT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_option (product_id, kind, name),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- cart / favorites (support both logged-in users and guest sessions)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  session_id VARCHAR(64) DEFAULT NULL,
  product_id INT NOT NULL,
  variant_id INT DEFAULT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  product_id INT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_fav (user_id, product_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- coupons (percentage or fixed-amount discount codes)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS coupons (
  id INT AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE,
  type ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
  value DECIMAL(10,2) NOT NULL DEFAULT 0,
  max_discount DECIMAL(10,2) DEFAULT NULL,
  min_subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  starts_at DATETIME DEFAULT NULL,
  expires_at DATETIME DEFAULT NULL,
  usage_limit INT DEFAULT NULL,
  per_customer_limit INT DEFAULT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  note VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- orders
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  user_id INT DEFAULT NULL,
  status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cod','bank_transfer') NOT NULL DEFAULT 'cod',
  delivery_area ENUM('inside_dhaka','suburbs','outside_dhaka') NOT NULL DEFAULT 'inside_dhaka',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  discount DECIMAL(10,2) NOT NULL DEFAULT 0,
  coupon_id INT DEFAULT NULL,
  coupon_code VARCHAR(40) DEFAULT NULL,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_name VARCHAR(120) NOT NULL,
  shipping_phone VARCHAR(30) NOT NULL,
  customer_email VARCHAR(160) DEFAULT NULL,
  shipping_line1 VARCHAR(200) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_state VARCHAR(100) DEFAULT NULL,
  shipping_zip VARCHAR(20) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_orders_coupon (coupon_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_orders_coupon FOREIGN KEY (coupon_id) REFERENCES coupons(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  variant_id INT DEFAULT NULL,
  variant_label VARCHAR(150) DEFAULT NULL,
  product_name VARCHAR(180) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
  FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timestamped log of every status an order has passed through, so the
-- storefront and admin can show a real "22 Feb 2026, 3:00 AM: Shipped"
-- style timeline instead of just the current status. from_status and
-- changed_by(_name) say what it changed from and which admin did it; those
-- are only ever shown in the admin portal.
CREATE TABLE IF NOT EXISTS order_status_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  from_status VARCHAR(20) DEFAULT NULL,
  status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL,
  note VARCHAR(255) DEFAULT NULL,
  changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  changed_by INT DEFAULT NULL,
  changed_by_name VARCHAR(120) DEFAULT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Simple key/value store for admin-editable site settings (theme colors,
-- seasonal effects, etc.) that don't need their own dedicated columns.
CREATE TABLE IF NOT EXISTS settings (
  setting_key VARCHAR(60) PRIMARY KEY,
  setting_value TEXT,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- product reviews (customers who received the product can review it)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS product_reviews (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  user_id INT DEFAULT NULL,
  author_name VARCHAR(120) NOT NULL,
  rating TINYINT NOT NULL,
  title VARCHAR(120) DEFAULT NULL,
  body TEXT NOT NULL,
  status ENUM('published','hidden') NOT NULL DEFAULT 'published',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uniq_review (user_id, product_id),
  KEY idx_product_status (product_id, status, created_at),
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- admin activity log: one row per admin action (append-only, no UI to edit or delete)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT DEFAULT NULL,
  admin_name VARCHAR(120) NOT NULL,
  admin_username VARCHAR(60) DEFAULT NULL,
  action VARCHAR(60) NOT NULL,
  target_type VARCHAR(40) DEFAULT NULL,
  target_id INT DEFAULT NULL,
  summary VARCHAR(255) NOT NULL,
  details TEXT DEFAULT NULL,
  ip VARCHAR(45) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_created (created_at),
  KEY idx_admin (admin_id, created_at),
  KEY idx_action (action, created_at),
  KEY idx_target (target_type, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- login throttling (brute-force guard for storefront + admin login)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS login_attempts (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bucket VARCHAR(20) NOT NULL,        -- 'customer' | 'admin'
  identifier VARCHAR(160) NOT NULL,   -- email or username attempted, lowercased
  ip VARCHAR(45) NOT NULL,
  attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_bucket_identifier (bucket, identifier, attempted_at),
  KEY idx_bucket_ip (bucket, ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- Seed data
-- ============================================================

-- Default admin login -> username: admin  password: ChangeMe123! (CHANGE THIS before going live)
INSERT INTO admins (username, name, password_hash, role) VALUES
('admin', 'admin', '$2b$10$t65uPHFANoBh6cQGF5pB9Ow1R6T2bR2JaMqJsWE1TNgjilZdgl5Wq', 'owner');

INSERT INTO categories (name, slug, description, sort_order) VALUES
('EDC Gear', 'edc-gear', 'Everyday carry tools, pocket knives, keychains and organizers.', 1),
('Bags & Carry', 'bags-carry', 'Slings, backpacks and pouches built for daily use.', 2),
('Leather Goods', 'leather-goods', 'Wallets, cardholders and full-grain leather accessories.', 3),
('Customized', 'customized', 'Engraved, monogrammed and made-to-order pieces.', 4);

INSERT INTO products (category_id, name, slug, sku, short_desc, description, price, compare_price, stock, is_active, is_featured) VALUES
(1, 'Titanium Pocket Pry Bar', 'titanium-pocket-pry-bar', 'EDC-001', 'Compact titanium multi-tool for keychain carry.', 'A compact titanium pry bar with bottle opener, flathead and box-cutter notch. Fits any keychain and weighs under 15g.', 950.00, 1100.00, 60, 1, 1),
(1, 'Brass Keychain Organizer', 'brass-keychain-organizer', 'EDC-002', 'Keeps keys quiet and organized.', 'A solid brass keychain clip that keeps your keys organized and silent in your pocket. Ages beautifully with a natural patina.', 720.00, NULL, 45, 1, 0),
(1, 'Mini EDC Flashlight', 'mini-edc-flashlight', 'EDC-003', '400 lumen rechargeable pocket light.', 'A rechargeable 400-lumen pocket flashlight with pocket clip, three brightness modes and USB-C charging.', 1250.00, 1450.00, 40, 1, 1),
(2, 'Waxed Canvas Sling Bag', 'waxed-canvas-sling-bag', 'BAG-001', 'Compact crossbody sling with leather trim.', 'A weatherproof waxed-canvas sling bag with leather trim, padded strap and organized interior pockets for daily carry.', 2450.00, 2800.00, 40, 1, 1),
(2, 'Leather Tech Pouch', 'leather-tech-pouch', 'BAG-002', 'Full-grain leather pouch for EDC & cables.', 'A full-grain leather pouch for carrying EDC gear, cables and chargers. Ages with a rich patina over time.', 1350.00, 1550.00, 35, 1, 1),
(2, 'Canvas Travel Pouch Set', 'canvas-travel-pouch-set', 'BAG-003', 'Three-piece packing pouch set.', 'A set of three durable canvas packing pouches in graduated sizes, with brass zippers and leather pulls.', 980.00, NULL, 55, 1, 0),
(3, 'Full-Grain Bifold Wallet', 'full-grain-bifold-wallet', 'LTH-001', 'Hand-stitched leather bifold wallet.', 'A hand-stitched full-grain leather bifold wallet with six card slots, a bill compartment and a slim profile that ages beautifully.', 1650.00, 1900.00, 70, 1, 1),
(3, 'Slim Leather Cardholder', 'slim-leather-cardholder', 'LTH-002', 'Minimalist front-pocket cardholder.', 'A minimalist front-pocket cardholder in vegetable-tanned leather, holding up to six cards with a central pull-tab.', 850.00, NULL, 90, 1, 0),
(3, 'Leather Belt, Classic Brown', 'leather-belt-classic-brown', 'LTH-003', 'Full-grain leather belt with brass buckle.', 'A full-grain leather belt in classic brown with a solid brass buckle, stitched edges and a break-in that only gets better.', 1200.00, 1400.00, 50, 1, 0),
(4, 'Personalized Engraved Keychain', 'personalized-engraved-keychain', 'CUS-001', 'Custom name or initials, laser engraved.', 'A solid brass or leather keychain laser-engraved with your choice of name, initials or a short message. Ships in 3-5 days.', 550.00, NULL, 200, 1, 1);

INSERT INTO settings (setting_key, setting_value) VALUES
('theme_primary', '#a97c34'),
('theme_secondary', '#5f7d5b'),
('seasonal_enabled', '0'),
('seasonal_effect', 'snow'),
('topbar_enabled', '0'),
('topbar_text', ''),
('topbar_link', ''),
('schema_version', '7');

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
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
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
  description TEXT,
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  compare_price DECIMAL(10,2) DEFAULT NULL,
  stock INT NOT NULL DEFAULT 0,
  weight_grams INT NOT NULL DEFAULT 500,
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

-- ---------------------------------------------------------------
-- cart / favorites (support both logged-in users and guest sessions)
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS cart_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT DEFAULT NULL,
  session_id VARCHAR(64) DEFAULT NULL,
  product_id INT NOT NULL,
  quantity INT NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
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
-- orders
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_number VARCHAR(30) NOT NULL UNIQUE,
  user_id INT DEFAULT NULL,
  status ENUM('pending','processing','shipped','completed','cancelled') NOT NULL DEFAULT 'pending',
  payment_method ENUM('cod','bank_transfer') NOT NULL DEFAULT 'cod',
  delivery_area ENUM('inside_dhaka','outside_dhaka') NOT NULL DEFAULT 'inside_dhaka',
  subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
  total DECIMAL(10,2) NOT NULL DEFAULT 0,
  shipping_name VARCHAR(120) NOT NULL,
  shipping_phone VARCHAR(30) NOT NULL,
  shipping_line1 VARCHAR(200) NOT NULL,
  shipping_city VARCHAR(100) NOT NULL,
  shipping_state VARCHAR(100) DEFAULT NULL,
  shipping_zip VARCHAR(20) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT DEFAULT NULL,
  product_name VARCHAR(180) NOT NULL,
  price DECIMAL(10,2) NOT NULL,
  quantity INT NOT NULL,
  subtotal DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
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

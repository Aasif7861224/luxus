-- LuxLut presets-only conversion migration
-- Run this once on `wedding_studio` database

START TRANSACTION;

-- 1) Products: before/after support
ALTER TABLE products
  ADD COLUMN IF NOT EXISTS before_image VARCHAR(255) NULL AFTER description,
  ADD COLUMN IF NOT EXISTS after_image VARCHAR(255) NULL AFTER before_image;

-- Optional backfill for old data (uses same image when before/after absent)
UPDATE products p
SET
  p.before_image = COALESCE(p.before_image, (
    SELECT pm.file_name
    FROM product_media pm
    WHERE pm.product_id = p.id AND pm.media_type = 'image'
    ORDER BY pm.id DESC
    LIMIT 1
  )),
  p.after_image = COALESCE(p.after_image, (
    SELECT pm.file_name
    FROM product_media pm
    WHERE pm.product_id = p.id AND pm.media_type = 'image'
    ORDER BY pm.id DESC
    LIMIT 1
  ));

-- 2) Digital product delivery table
CREATE TABLE IF NOT EXISTS digital_products (
  id INT(11) NOT NULL AUTO_INCREMENT,
  product_id INT(11) NOT NULL,
  drive_link VARCHAR(500) NOT NULL,
  is_active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_digital_products_product (product_id),
  CONSTRAINT fk_digital_products_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 3) Users: password for email login
ALTER TABLE users
  ADD COLUMN IF NOT EXISTS password VARCHAR(255) NULL AFTER email;

-- 4) Remove service-order data before enum tightening
DELETE oi
FROM order_items oi
INNER JOIN orders o ON o.id = oi.order_id
WHERE o.order_type = 'service' OR oi.item_type = 'service';

DELETE FROM orders WHERE order_type = 'service';

-- 5) Orders for product-only flow + payment metadata
UPDATE orders SET order_type = 'product' WHERE order_type IS NULL;
UPDATE orders SET order_status = 'paid' WHERE payment_status = 'paid' AND (order_status IS NULL OR order_status = 'payment_received');
UPDATE orders SET order_status = 'pending' WHERE order_status IS NULL;

ALTER TABLE orders
  MODIFY COLUMN order_type ENUM('product') DEFAULT 'product',
  MODIFY COLUMN order_status ENUM('pending','paid','payment_received','assigned_to_editor','editing','review','delivered','closed') DEFAULT 'pending',
  ADD COLUMN IF NOT EXISTS customer_phone VARCHAR(20) NULL AFTER user_id,
  ADD COLUMN IF NOT EXISTS razorpay_order_id VARCHAR(100) NULL AFTER payment_status,
  ADD COLUMN IF NOT EXISTS razorpay_payment_id VARCHAR(100) NULL AFTER razorpay_order_id,
  ADD COLUMN IF NOT EXISTS paid_at DATETIME NULL AFTER created_at;

ALTER TABLE order_items
  MODIFY COLUMN item_type ENUM('product') DEFAULT 'product';

-- 6) Product media: image-only gallery
DELETE FROM product_media WHERE media_type <> 'image';
ALTER TABLE product_media
  MODIFY COLUMN media_type ENUM('image') NOT NULL;

-- 7) Delivery logs for WhatsApp dispatch
CREATE TABLE IF NOT EXISTS delivery_logs (
  id INT(11) NOT NULL AUTO_INCREMENT,
  order_id INT(11) NOT NULL,
  channel VARCHAR(30) NOT NULL,
  status ENUM('sent','failed','skipped') NOT NULL,
  response_payload TEXT NULL,
  sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_delivery_logs_order (order_id),
  CONSTRAINT fk_delivery_logs_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 8) Feedback collection
CREATE TABLE IF NOT EXISTS feedback (
  id INT(11) NOT NULL AUTO_INCREMENT,
  user_id INT(11) DEFAULT NULL,
  name VARCHAR(100) NOT NULL,
  email VARCHAR(120) NOT NULL,
  phone VARCHAR(20) NOT NULL,
  message TEXT NOT NULL,
  status ENUM('new','reviewed') NOT NULL DEFAULT 'new',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_feedback_user (user_id),
  KEY idx_feedback_status (status),
  KEY idx_feedback_created (created_at),
  KEY idx_feedback_email (email),
  CONSTRAINT fk_feedback_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- 9) Category cleanup (Preset/LUT only)
INSERT IGNORE INTO categories (id, name) VALUES (1, 'Preset');
INSERT IGNORE INTO categories (id, name) VALUES (2, 'LUT');

-- Re-map service category products to Preset before removing service category
UPDATE products p
INNER JOIN categories c ON c.id = p.category_id
SET p.category_id = 1
WHERE LOWER(c.name) = 'service';

-- Re-map duplicate category references to the lowest id of the same name
UPDATE products p
INNER JOIN categories c ON c.id = p.category_id
INNER JOIN (
  SELECT LOWER(name) AS category_key, MIN(id) AS keep_id
  FROM categories
  GROUP BY LOWER(name)
) x ON x.category_key = LOWER(c.name)
SET p.category_id = x.keep_id
WHERE p.category_id <> x.keep_id;

DELETE FROM categories WHERE LOWER(name) = 'service';
DELETE c1
FROM categories c1
INNER JOIN categories c2
  ON c1.name = c2.name
 AND c1.id > c2.id;

-- 10) Drop wedding-specific tables
DROP TABLE IF EXISTS wedding_bookings;
DROP TABLE IF EXISTS services;
DROP TABLE IF EXISTS service_prices;

-- 11) Drop unused legacy tables
DROP TABLE IF EXISTS files;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;

COMMIT;

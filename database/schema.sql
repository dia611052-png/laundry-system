-- ============================================================
-- FreshTrack — Web-Based Laundry Service Booking and Order
-- Tracking System
-- Import this file via phpMyAdmin (or `mysql -u root < schema.sql`)
-- before opening the site. Matches config/database.php defaults:
-- host localhost, user root, no password, database "freshtrack".
-- ============================================================

CREATE DATABASE IF NOT EXISTS freshtrack CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE freshtrack;

-- ------------------------------------------------------------
-- Users: one table, one role column. The role is what the
-- authentication middleware checks on every protected page.
-- ------------------------------------------------------------
CREATE TABLE users (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  email      VARCHAR(150) NOT NULL UNIQUE,
  password   VARCHAR(255) NOT NULL,               -- bcrypt hash via password_hash()
  role       ENUM('admin', 'staff', 'customer') NOT NULL DEFAULT 'customer',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Services offered, with the pricing shown on the public site.
-- ------------------------------------------------------------
CREATE TABLE services (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  price       DECIMAL(8,2) NOT NULL,
  unit        VARCHAR(30) NOT NULL,                -- e.g. "per kg", "per piece"
  eta         VARCHAR(60) NOT NULL,                -- e.g. "Same day (6 hrs)"
  description TEXT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Orders: one row per booking. tracking_code is the customer-
-- facing ID (FT-10021, etc.) shown throughout the UI.
-- ------------------------------------------------------------
CREATE TABLE orders (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  tracking_code     VARCHAR(20) NOT NULL UNIQUE,
  customer_id       INT NOT NULL,
  service_id        INT NOT NULL,
  qty               INT NOT NULL DEFAULT 1,
  notes             TEXT,
  preferred_dropoff DATE NULL,
  status            ENUM('Pending','Received','Washing','Drying','Ready for Pickup','Completed','Cancelled')
                    NOT NULL DEFAULT 'Pending',
  created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (service_id) REFERENCES services(id)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Status history: one row per status change, so a customer or
-- staff member can see the full timeline of an order.
-- ------------------------------------------------------------
CREATE TABLE order_status_history (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  order_id   INT NOT NULL,
  status     VARCHAR(30) NOT NULL,
  changed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Seed data
-- ------------------------------------------------------------

INSERT INTO services (name, price, unit, eta, description) VALUES
('Wash & Fold', 65.00, 'per kg', 'Same day (6 hrs)', 'Everyday laundry, washed, dried, and folded. Good for weekly loads.'),
('Wash & Iron', 90.00, 'per kg', '24 hrs', 'Washed, dried, and pressed. For office wear and uniforms.'),
('Dry Cleaning', 180.00, 'per piece', '48 hrs', 'Delicate fabrics, coats, and formal wear cleaned without water.'),
('Comforter / Beddings', 250.00, 'per item', '48 hrs', 'Blankets, comforters, and large beddings. Requires bulk drying.'),
('Express Wash', 120.00, 'per kg', '3 hrs', 'Rush service for same-day pickup needs.');

-- Demo accounts — passwords below are the bcrypt hash of the plaintext
-- shown in the comment, generated with PHP's password_hash(). Log in
-- with the plaintext; change or remove these before going live.
INSERT INTO users (name, email, password, role) VALUES
('System Administrator', 'admin@freshtrack.test', '$2y$10$FD/3d8G8JVLncBXdSut0kefKdY0lHqeKyjKiK7nn30Wr7nyuRlFLS', 'admin'),      -- admin123
('Marisol Reyes',        'staff@freshtrack.test', '$2y$10$qnVavzqDdjkOAnxutM4U..gQuOy.3fg4jCgfY5cywIhGYd.lw5dqO', 'staff'),      -- staff123
('Juan Dela Cruz',       'customer@freshtrack.test', '$2y$10$FU7gknxnNKkhe7pRT3TylOXgkxuYnWn65cb0x2OymHSm3oUEFhOUm', 'customer'); -- customer123

-- A few demo orders for the customer account above (user id 3),
-- with matching status-history rows so tracking has something to show.
INSERT INTO orders (tracking_code, customer_id, service_id, qty, notes, status, created_at) VALUES
('FT-10018', 3, 2, 6, '', 'Completed', NOW() - INTERVAL 6 DAY),
('FT-10021', 3, 1, 4, 'Fabric softener please.', 'Ready for Pickup', NOW() - INTERVAL 2 DAY),
('FT-10022', 3, 3, 2, 'One blazer, one coat.', 'Washing', NOW() - INTERVAL 1 DAY);

INSERT INTO order_status_history (order_id, status, changed_at) VALUES
(1, 'Pending',          NOW() - INTERVAL 6 DAY),
(1, 'Received',         NOW() - INTERVAL 6 DAY + INTERVAL 4 HOUR),
(1, 'Washing',          NOW() - INTERVAL 5 DAY),
(1, 'Drying',           NOW() - INTERVAL 5 DAY + INTERVAL 6 HOUR),
(1, 'Ready for Pickup', NOW() - INTERVAL 4 DAY),
(1, 'Completed',        NOW() - INTERVAL 4 DAY + INTERVAL 8 HOUR),

(2, 'Pending',          NOW() - INTERVAL 2 DAY),
(2, 'Received',         NOW() - INTERVAL 2 DAY + INTERVAL 2 HOUR),
(2, 'Washing',          NOW() - INTERVAL 1 DAY - INTERVAL 12 HOUR),
(2, 'Drying',           NOW() - INTERVAL 1 DAY - INTERVAL 4 HOUR),
(2, 'Ready for Pickup', NOW() - INTERVAL 12 HOUR),

(3, 'Pending',          NOW() - INTERVAL 1 DAY),
(3, 'Received',         NOW() - INTERVAL 18 HOUR),
(3, 'Washing',          NOW() - INTERVAL 5 HOUR);

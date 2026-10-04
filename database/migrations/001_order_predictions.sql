-- ============================================================
-- Migration 001 — saved, staff-controlled finish-time estimates
--
-- Run this ONCE against an existing `freshtrack` database that was
-- imported from an older schema.sql (fresh imports of the current
-- schema.sql already include this table).
--
--   phpMyAdmin: select the freshtrack database -> SQL tab -> paste -> Go
--   CLI:        mysql -u root freshtrack < database/migrations/001_order_predictions.sql
--
-- Safe to re-run: it only creates the table if it's missing.
-- Orders that are already in progress won't have an estimate until a
-- staff member next clicks their "Mark ..." button.
-- ============================================================

USE freshtrack;

CREATE TABLE IF NOT EXISTS order_predictions (
  id                   INT AUTO_INCREMENT PRIMARY KEY,
  order_id             INT NOT NULL,
  status_at_prediction VARCHAR(30) NOT NULL,
  remaining_hours      DECIMAL(7,2) NULL,
  progress_percent     DECIMAL(5,2) NULL,
  finish_at            DATETIME NULL,
  message              TEXT NOT NULL,
  source               ENUM('gemini','fallback','system') NOT NULL DEFAULT 'gemini',
  created_by           INT NULL,
  created_at           TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_order_predictions_order (order_id, id)
) ENGINE=InnoDB;

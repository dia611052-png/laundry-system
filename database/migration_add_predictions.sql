-- Run this once against an existing freshtrack database to add the
-- columns that persist each order's last Gemini "finish time" prediction.
-- (Not needed on a fresh install — schema.sql already includes these.)

ALTER TABLE orders
  ADD COLUMN predicted_remaining_hours   DECIMAL(6,2) NULL,
  ADD COLUMN predicted_progress_percent  TINYINT UNSIGNED NULL,
  ADD COLUMN predicted_finish_at         DATETIME NULL,
  ADD COLUMN predicted_message           VARCHAR(255) NULL,
  ADD COLUMN predicted_at                DATETIME NULL;

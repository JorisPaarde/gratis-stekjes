-- 0002_aanbod_expiry.up.sql — verloop na 60 dagen + herinnering/verlenging
ALTER TABLE aanbod
  ADD COLUMN expires_at DATETIME NULL AFTER updated_at,
  ADD COLUMN expiry_reminder_sent_at DATETIME NULL AFTER expires_at,
  ADD COLUMN renew_token VARCHAR(128) NULL AFTER expiry_reminder_sent_at,
  ADD COLUMN renew_token_expires DATETIME NULL AFTER renew_token;

UPDATE aanbod
SET expires_at = DATE_ADD(created_at, INTERVAL 60 DAY)
WHERE expires_at IS NULL;

ALTER TABLE aanbod
  MODIFY expires_at DATETIME NOT NULL;

ALTER TABLE aanbod
  ADD KEY idx_aanbod_expires (expires_at),
  ADD UNIQUE KEY uq_aanbod_renew_token (renew_token);

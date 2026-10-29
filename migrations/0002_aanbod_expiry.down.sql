-- 0002_aanbod_expiry.down.sql
ALTER TABLE aanbod
  DROP INDEX uq_aanbod_renew_token,
  DROP INDEX idx_aanbod_expires,
  DROP COLUMN renew_token_expires,
  DROP COLUMN renew_token,
  DROP COLUMN expiry_reminder_sent_at,
  DROP COLUMN expires_at;

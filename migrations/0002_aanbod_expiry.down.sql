-- 0002_aanbod_expiry.down.sql
-- MySQL: scripts/migrate.php gebruikt stekjes_mysql_revert_0002() (idempotent).

ALTER TABLE aanbod DROP INDEX uq_aanbod_renew_token;
ALTER TABLE aanbod DROP INDEX idx_aanbod_expires;
ALTER TABLE aanbod DROP COLUMN renew_token_expires;
ALTER TABLE aanbod DROP COLUMN renew_token;
ALTER TABLE aanbod DROP COLUMN expiry_reminder_sent_at;
ALTER TABLE aanbod DROP COLUMN expires_at;

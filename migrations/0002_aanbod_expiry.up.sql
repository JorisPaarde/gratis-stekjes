-- 0002_aanbod_expiry.up.sql — verloop na 60 dagen + herinnering/verlenging
-- Op MySQL voert scripts/migrate.php dit idempotent uit via stekjes_mysql_apply_0002()
-- (statement-voor-statement; veilig na half-gelukte deploy). Dit bestand blijft de bron van waarheid.

ALTER TABLE aanbod ADD COLUMN expires_at DATETIME NULL;
ALTER TABLE aanbod ADD COLUMN expiry_reminder_sent_at DATETIME NULL;
ALTER TABLE aanbod ADD COLUMN renew_token VARCHAR(128) NULL;
ALTER TABLE aanbod ADD COLUMN renew_token_expires DATETIME NULL;

UPDATE aanbod
SET expires_at = DATE_ADD(created_at, INTERVAL 60 DAY)
WHERE expires_at IS NULL;

ALTER TABLE aanbod MODIFY expires_at DATETIME NOT NULL;

ALTER TABLE aanbod ADD KEY idx_aanbod_expires (expires_at);
ALTER TABLE aanbod ADD UNIQUE KEY uq_aanbod_renew_token (renew_token);

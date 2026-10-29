-- 0003_partner_marker_fields.down.sql (MySQL)
ALTER TABLE partner_markers
  DROP INDEX idx_partner_markers_type_visible,
  DROP COLUMN beschrijving,
  DROP COLUMN plaats,
  DROP COLUMN type;

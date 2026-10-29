-- 0003_partner_marker_fields.up.sql — type, plaats, korte beschrijving voor partnermarkers (MySQL)
ALTER TABLE partner_markers
  ADD COLUMN type ENUM('partner','voedselbos','plantenasiel','plantenbieb','ruilkast') NOT NULL DEFAULT 'partner' AFTER naam,
  ADD COLUMN plaats VARCHAR(120) NOT NULL DEFAULT '' AFTER type,
  ADD COLUMN beschrijving VARCHAR(500) NOT NULL DEFAULT '' AFTER plaats,
  ADD KEY idx_partner_markers_type_visible (type, visible);

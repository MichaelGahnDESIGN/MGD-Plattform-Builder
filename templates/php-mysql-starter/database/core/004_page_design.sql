-- GrapesJS-Vollspeicherung: Format "grapesjs" plus bereinigtes, auf .page-content begrenztes CSS.
-- content_source enthält bei "grapesjs" die Projektdaten (JSON) des Editors.
--
-- Die CHECK-Bedingung chk_pages_format (001_core.sql) wird portabel ersetzt:
-- MySQL >= 8.0.16 und MariaDB >= 10.2 führen sie in information_schema.TABLE_CONSTRAINTS und
-- verstehen "DROP CONSTRAINT" (MySQL ab 8.0.19). Ältere MySQL-Versionen ignorieren CHECK-Bedingungen
-- vollständig; dort wird nichts gelöscht (DO 0) und das erneute Anlegen ist wirkungslos.

SET @mgd_has_format_check = (
    SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE CONSTRAINT_SCHEMA = DATABASE() AND TABLE_NAME = 'pages' AND CONSTRAINT_NAME = 'chk_pages_format'
);
SET @mgd_drop_format_check = IF(@mgd_has_format_check > 0, 'ALTER TABLE pages DROP CONSTRAINT chk_pages_format', 'DO 0');
PREPARE mgd_statement FROM @mgd_drop_format_check;
EXECUTE mgd_statement;
DEALLOCATE PREPARE mgd_statement;

ALTER TABLE pages
    ADD CONSTRAINT chk_pages_format CHECK (content_format IN ('html', 'markdown', 'grapesjs'));

ALTER TABLE pages ADD COLUMN content_css MEDIUMTEXT NULL AFTER content_html;

ALTER TABLE page_revisions ADD COLUMN content_css MEDIUMTEXT NULL AFTER content_html;

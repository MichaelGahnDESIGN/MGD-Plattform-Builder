-- Medienbibliothek: Metadaten hochgeladener Dateien. Die Dateien liegen unter paths.uploads/YYYY/MM/.
-- uploaded_by bewusst ohne Fremdschlüssel: Medien bleiben erhalten, wenn ein Konto gelöscht wird.

CREATE TABLE media (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    path VARCHAR(300) NOT NULL,
    original_name VARCHAR(190) NOT NULL,
    mime VARCHAR(100) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    width INT UNSIGNED NULL,
    height INT UNSIGNED NULL,
    alt_text VARCHAR(300) NOT NULL DEFAULT '',
    uploaded_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_media_path (path),
    KEY idx_media_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

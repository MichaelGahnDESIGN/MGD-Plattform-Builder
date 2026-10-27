<?php

declare(strict_types=1);

namespace MGD\Starter\Media;

use InvalidArgumentException;
use PDO;

/**
 * Metadaten der Medienbibliothek und Verwendungsprüfung vor dem Löschen.
 */
final class MediaRepository
{
    private const MAX_ALT_LENGTH = 300;
    private const MAX_LIST = 500;

    public function __construct(private readonly PDO $database)
    {
    }

    public function list(string $query = '', bool $imagesOnly = false, int $limit = self::MAX_LIST): array
    {
        $where = [];
        $params = [];

        if ($query !== '') {
            $where[] = '(original_name LIKE :q_name OR alt_text LIKE :q_alt OR path LIKE :q_path)';
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $params = ['q_name' => $like, 'q_alt' => $like, 'q_path' => $like];
        }

        if ($imagesOnly) {
            $where[] = "mime LIKE 'image/%'";
        }

        $statement = $this->database->prepare(
            'SELECT id, path, original_name, mime, size_bytes, width, height, alt_text, uploaded_by, created_at FROM media'
            . ($where === [] ? '' : ' WHERE ' . implode(' AND ', $where))
            . ' ORDER BY id DESC LIMIT ' . max(1, min(self::MAX_LIST, $limit))
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    /**
     * @return list<string>
     */
    public function imagePaths(): array
    {
        return array_map('strval', array_column($this->list('', true), 'path'));
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM media WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    /**
     * @param array{path: string, original_name: string, mime: string, size_bytes: int, width: ?int, height: ?int} $data
     */
    public function create(array $data, ?int $uploadedBy): int
    {
        $this->database->prepare(
            'INSERT INTO media (path, original_name, mime, size_bytes, width, height, alt_text, uploaded_by, created_at)
             VALUES (:path, :original_name, :mime, :size_bytes, :width, :height, \'\', :uploaded_by, UTC_TIMESTAMP())'
        )->execute([...$data, 'uploaded_by' => $uploadedBy]);

        return (int) $this->database->lastInsertId();
    }

    public function updateAlt(int $id, string $alt): void
    {
        $alt = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $alt));

        if (mb_strlen($alt) > self::MAX_ALT_LENGTH) {
            throw new InvalidArgumentException('Alternativtext ist länger als ' . self::MAX_ALT_LENGTH . ' Zeichen.');
        }

        $this->database->prepare('UPDATE media SET alt_text = :alt WHERE id = :id')->execute(['alt' => $alt, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->database->prepare('DELETE FROM media WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Wo wird der Pfad noch verwendet? Gesucht wird ohne führenden Schrägstrich, damit auch
     * Pfade mit Basis-Pfad (/cms/uploads/…) und JSON-escapte Werte ("\/uploads\/…") gefunden werden.
     *
     * @return list<string>
     */
    public function references(string $path): array
    {
        $needle = ltrim($path, '/');
        $plain = '%' . addcslashes($needle, '%_\\') . '%';
        $escaped = '%' . addcslashes(str_replace('/', '\\/', $needle), '%_\\') . '%';
        $found = [];

        $pages = $this->database->prepare(
            'SELECT slug FROM pages WHERE content_html LIKE :html OR content_css LIKE :css OR content_source LIKE :source OR content_source LIKE :source_json LIMIT 10'
        );
        $pages->execute(['html' => $plain, 'css' => $plain, 'source' => $plain, 'source_json' => $escaped]);

        foreach ($pages->fetchAll(PDO::FETCH_COLUMN) as $slug) {
            $found[] = 'CMS-Seite "' . $slug . '"';
        }

        $credits = $this->database->prepare('SELECT name FROM credit_components WHERE logo_path LIKE :path LIMIT 10');
        $credits->execute(['path' => $plain]);

        foreach ($credits->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $found[] = 'Credit-Komponente "' . $name . '"';
        }

        $settings = $this->database->prepare('SELECT setting_key FROM settings WHERE value_json LIKE :plain OR value_json LIKE :escaped LIMIT 10');
        $settings->execute(['plain' => $plain, 'escaped' => $escaped]);

        foreach ($settings->fetchAll(PDO::FETCH_COLUMN) as $key) {
            $found[] = 'Einstellung ' . $key;
        }

        return $found;
    }
}

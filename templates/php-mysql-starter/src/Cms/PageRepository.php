<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\User;
use PDO;
use RuntimeException;
use Throwable;

/**
 * CMS-Seiten inkl. Revisionen und Papierkorb (Soft Delete).
 * Erwartet bereits normalisierte Daten aus PageInput.
 */
final class PageRepository
{
    private const COLUMNS = 'id, slug, title, page_type, status, content_format, content_source, content_html,
        meta_description, current_revision, created_at, updated_at, deleted_at';

    public function __construct(private readonly PDO $database)
    {
    }

    public function list(string $query = '', string $type = '', bool $trashed = false): array
    {
        $where = [$trashed ? 'deleted_at IS NOT NULL' : 'deleted_at IS NULL'];
        $params = [];

        if ($query !== '') {
            $where[] = '(title LIKE :q_title OR slug LIKE :q_slug)';
            $like = '%' . addcslashes($query, '%_\\') . '%';
            $params['q_title'] = $like;
            $params['q_slug'] = $like;
        }

        if ($type !== '' && array_key_exists($type, PageInput::TYPES)) {
            $where[] = 'page_type = :type';
            $params['type'] = $type;
        }

        $statement = $this->database->prepare(
            'SELECT id, slug, title, page_type, status, current_revision, updated_at, deleted_at
               FROM pages WHERE ' . implode(' AND ', $where) . ' ORDER BY page_type, title'
        );
        $statement->execute($params);

        return $statement->fetchAll();
    }

    public function find(int $id, bool $includeDeleted = false): ?array
    {
        $statement = $this->database->prepare(
            'SELECT ' . self::COLUMNS . ' FROM pages WHERE id = :id' . ($includeDeleted ? '' : ' AND deleted_at IS NULL')
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function findBySlug(string $slug, bool $publishedOnly = true, bool $includeDeleted = false): ?array
    {
        $sql = 'SELECT ' . self::COLUMNS . ' FROM pages WHERE slug = :slug';
        $sql .= $includeDeleted ? '' : ' AND deleted_at IS NULL';
        $sql .= $publishedOnly ? " AND status = 'published'" : '';
        $statement = $this->database->prepare($sql);
        $statement->execute(['slug' => $slug]);

        return $statement->fetch() ?: null;
    }

    /**
     * @return list<array{slug: string, title: string, updated_at: string}>
     */
    public function publishedForSitemap(): array
    {
        return $this->database->query(
            "SELECT slug, title, updated_at FROM pages
              WHERE deleted_at IS NULL AND status = 'published' AND page_type IN ('page', 'legal')
              ORDER BY slug"
        )->fetchAll();
    }

    /**
     * @param list<string> $slugs
     */
    public function publishedTitles(array $slugs): array
    {
        $slugs = array_values(array_filter($slugs, static fn (string $s): bool => preg_match(PageInput::SLUG_PATTERN, $s) === 1));

        if ($slugs === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($slugs), '?'));
        $statement = $this->database->prepare(
            "SELECT slug, title FROM pages WHERE deleted_at IS NULL AND status = 'published' AND slug IN ($placeholders)"
        );
        $statement->execute($slugs);
        $titles = array_column($statement->fetchAll(), 'title', 'slug');

        return array_intersect_key(array_replace(array_flip($slugs), $titles), $titles);
    }

    public function create(array $data, ?User $author, string $note = 'Erstellt'): int
    {
        if ($this->findBySlug($data['slug'], false, true) !== null) {
            throw new InvalidArgumentException('Der Slug "' . $data['slug'] . '" ist bereits vergeben.');
        }

        return $this->transaction(function () use ($data, $author, $note): int {
            $this->database->prepare(
                'INSERT INTO pages (slug, title, page_type, status, content_format, content_source, content_html,
                                    meta_description, created_by, updated_by, created_at, updated_at)
                 VALUES (:slug, :title, :page_type, :status, :content_format, :content_source, :content_html,
                         :meta_description, :created_by, :updated_by, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
            )->execute([...$this->pageParams($data), 'created_by' => $author?->id, 'updated_by' => $author?->id]);
            $id = (int) $this->database->lastInsertId();
            $this->addRevision($id, $data, $author, $note);

            return $id;
        });
    }

    /**
     * Speichert eine neue Fassung und legt dafür eine Revision an. Gibt die Revisionsnummer zurück.
     */
    public function update(int $id, array $data, ?User $author, string $note = ''): int
    {
        $existing = $this->find($id, true) ?? throw new InvalidArgumentException('Seite nicht gefunden.');
        $other = $this->findBySlug($data['slug'], false, true);

        if ($other !== null && (int) $other['id'] !== $id) {
            throw new InvalidArgumentException('Der Slug "' . $data['slug'] . '" ist bereits vergeben.');
        }

        return $this->transaction(function () use ($existing, $data, $author, $note): int {
            $this->database->prepare(
                'UPDATE pages SET slug = :slug, title = :title, page_type = :page_type, status = :status,
                        content_format = :content_format, content_source = :content_source, content_html = :content_html,
                        meta_description = :meta_description, updated_by = :updated_by, updated_at = UTC_TIMESTAMP()
                  WHERE id = :id'
            )->execute([...$this->pageParams($data), 'updated_by' => $author?->id, 'id' => (int) $existing['id']]);

            return $this->addRevision((int) $existing['id'], $data, $author, $note);
        });
    }

    public function softDelete(int $id, ?User $actor): void
    {
        $this->database->prepare(
            'UPDATE pages SET deleted_at = UTC_TIMESTAMP(), deleted_by = :actor WHERE id = :id AND deleted_at IS NULL'
        )->execute(['actor' => $actor?->id, 'id' => $id]);
    }

    public function restore(int $id): void
    {
        $this->database->prepare('UPDATE pages SET deleted_at = NULL, deleted_by = NULL WHERE id = :id')
            ->execute(['id' => $id]);
    }

    /**
     * Endgültiges Löschen – nur aus dem Papierkorb. Revisionen werden per FK mitgelöscht.
     */
    public function purge(int $id): void
    {
        $statement = $this->database->prepare('DELETE FROM pages WHERE id = :id AND deleted_at IS NOT NULL');
        $statement->execute(['id' => $id]);

        if ($statement->rowCount() !== 1) {
            throw new InvalidArgumentException('Nur Seiten im Papierkorb können endgültig gelöscht werden.');
        }
    }

    public function revisions(int $pageId): array
    {
        $statement = $this->database->prepare(
            'SELECT id, page_id, revision_no, title, status, author_label, note, created_at
               FROM page_revisions WHERE page_id = :page_id ORDER BY revision_no DESC'
        );
        $statement->execute(['page_id' => $pageId]);

        return $statement->fetchAll();
    }

    public function revision(int $revisionId): ?array
    {
        $statement = $this->database->prepare(
            'SELECT r.*, p.slug, p.page_type
               FROM page_revisions r JOIN pages p ON p.id = r.page_id WHERE r.id = :id'
        );
        $statement->execute(['id' => $revisionId]);

        return $statement->fetch() ?: null;
    }

    /**
     * Stellt eine alte Revision wieder her, indem ihr Inhalt als neue Revision gespeichert wird.
     */
    public function restoreRevision(int $revisionId, ?User $author): int
    {
        $revision = $this->revision($revisionId) ?? throw new InvalidArgumentException('Revision nicht gefunden.');
        $data = [
            'slug' => (string) $revision['slug'],
            'title' => (string) $revision['title'],
            'page_type' => (string) $revision['page_type'],
            'status' => (string) $revision['status'],
            'content_format' => (string) $revision['content_format'],
            'content_source' => $revision['content_source'],
            'content_html' => (string) $revision['content_html'],
            'meta_description' => (string) $revision['meta_description'],
        ];

        return $this->update((int) $revision['page_id'], $data, $author, 'Wiederhergestellt aus Revision ' . $revision['revision_no']);
    }

    private function addRevision(int $pageId, array $data, ?User $author, string $note): int
    {
        $statement = $this->database->prepare(
            'SELECT COALESCE(MAX(revision_no), 0) + 1 FROM page_revisions WHERE page_id = :page_id FOR UPDATE'
        );
        $statement->execute(['page_id' => $pageId]);
        $revisionNo = (int) $statement->fetchColumn();

        $this->database->prepare(
            'INSERT INTO page_revisions (page_id, revision_no, title, status, content_format, content_source, content_html,
                                         meta_description, author_id, author_label, note, created_at)
             VALUES (:page_id, :revision_no, :title, :status, :content_format, :content_source, :content_html,
                     :meta_description, :author_id, :author_label, :note, UTC_TIMESTAMP())'
        )->execute([
            'page_id' => $pageId,
            'revision_no' => $revisionNo,
            'title' => $data['title'],
            'status' => $data['status'],
            'content_format' => $data['content_format'],
            'content_source' => $data['content_source'],
            'content_html' => $data['content_html'],
            'meta_description' => $data['meta_description'],
            'author_id' => $author?->id,
            'author_label' => $author !== null ? mb_substr($author->displayName, 0, 120) : 'System',
            'note' => mb_substr(trim($note), 0, 300),
        ]);
        $this->database->prepare('UPDATE pages SET current_revision = :no WHERE id = :id')
            ->execute(['no' => $revisionNo, 'id' => $pageId]);

        return $revisionNo;
    }

    private function pageParams(array $data): array
    {
        return [
            'slug' => $data['slug'],
            'title' => $data['title'],
            'page_type' => $data['page_type'],
            'status' => $data['status'],
            'content_format' => $data['content_format'],
            'content_source' => $data['content_source'],
            'content_html' => $data['content_html'],
            'meta_description' => $data['meta_description'],
        ];
    }

    private function transaction(callable $callback): int
    {
        $this->database->beginTransaction();

        try {
            $result = $callback();
            $this->database->commit();

            return $result;
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception instanceof InvalidArgumentException
                ? $exception
                : new RuntimeException('Speichern fehlgeschlagen.', 0, $exception);
        }
    }
}

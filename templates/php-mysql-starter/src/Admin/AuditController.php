<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class AuditController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $type = preg_replace('/[^a-z_]/', '', $request->query('type')) ?? '';
        $rows = [];

        foreach ($this->app->audit()->recent(200, $type) as $entry) {
            $rows[] = [
                View::e(View::date($entry['created_at'], 'd.m.Y H:i:s')),
                View::e($entry['actor_label']),
                '<code>' . View::e($entry['action_name']) . '</code>',
                View::e($entry['resource_type'] . ($entry['resource_id'] !== null ? ' #' . $entry['resource_id'] : '')),
                '<code class="audit-meta">' . View::e(mb_substr((string) $entry['metadata_json'], 0, 300)) . '</code>',
            ];
        }

        return $this->page(
            'Audit-Log',
            Ui::pageHeader('Audit-Log', '', 'Die letzten 200 Änderungen an Seiten, Einstellungen, Credits, Release Notes und Code.')
                . ($rows === [] ? Ui::emptyState('Noch keine Einträge.') : Ui::table(['Zeit (UTC)', 'Wer', 'Aktion', 'Objekt', 'Details'], $rows)),
            $user,
            '/admin/audit'
        );
    }
}

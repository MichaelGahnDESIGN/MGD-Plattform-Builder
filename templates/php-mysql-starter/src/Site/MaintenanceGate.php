<?php

declare(strict_types=1);

namespace MGD\Starter\Site;

use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\SiteLayout;
use MGD\Starter\Core\View\View;

/**
 * Wartungsmodus: öffentliche Seiten liefern HTTP 503. Login, Backoffice und Installer bleiben erreichbar.
 */
final class MaintenanceGate
{
    private const ALWAYS_ALLOWED = ['/login', '/logout', '/install', '/robots.txt', '/passwort-vergessen', '/passwort-zuruecksetzen'];

    public function __construct(private readonly App $app)
    {
    }

    public function check(Request $request): ?Response
    {
        if (in_array($request->path, self::ALWAYS_ALLOWED, true) || str_starts_with($request->path, '/admin')) {
            return null;
        }

        $settings = $this->app->settings();

        if (!$settings->bool('maintenance.enabled')) {
            return null;
        }

        $user = $this->app->currentUser();

        if ($settings->bool('maintenance.admin_bypass') && $user !== null && $user->can(Role::Admin)) {
            return null;
        }

        $body = '<div class="container"><article class="page maintenance"><h1>Wartungsarbeiten</h1><p>'
            . nl2br(View::e($settings->string('maintenance.message'))) . '</p></article></div>';

        return Response::html((new SiteLayout($this->app))->render('Wartungsarbeiten', $body), 503)
            ->withHeader('Retry-After', '3600');
    }
}

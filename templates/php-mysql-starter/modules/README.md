# Module

Module erweitern den Starter um eigene Routen, Backoffice-Seiten und Datenbanktabellen.
Sie werden **per FTP oder Git** nach `modules/<id>/` kopiert und unter **Backoffice › Einstellungen › Module**
aktiviert. Ein Upload ausführbaren Codes über das Web ist aus Sicherheitsgründen nicht vorgesehen.

```text
modules/
└── mein-modul/
    ├── module.json
    ├── module.php
    └── migrations/
        └── 001_mein_modul.sql
```

## module.json

```json
{
  "id": "mein-modul",
  "name": "Mein Modul",
  "version": "1.0.0",
  "description": "Kurze Beschreibung",
  "vendor": "Dein Name",
  "license": "free",
  "requires": { "starter": ">=0.6.0" },
  "entry": "module.php",
  "migrations": "migrations",
  "menu": [
    { "href": "/admin/mein-modul", "label": "Mein Modul", "role": "editor" }
  ]
}
```

- `id` muss dem Ordnernamen entsprechen (`a-z`, `0-9`, `-`).
- `license`: `free` oder `paid`. Kostenpflichtige Module lassen sich nur mit einem gültigen,
  von Michael Gahn DESIGN signierten Modul-Schlüssel aktivieren.
- `role`: `moderator`, `editor` oder `admin`.
- Migrationen laufen beim Aktivieren (Scope `module/<id>`), bereits ausgeführte Dateien nie ändern.
- Schema: `schema/module-package.schema.json` im MGD-Plattform-Builder.

## module.php

```php
<?php

declare(strict_types=1);

use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\Http\Router;
use MGD\Starter\Core\Module\ModuleManifest;

return static function (Router $router, App $app, ModuleManifest $module): void {
    $router->get('/admin/mein-modul', static function (Request $request) use ($app): Response {
        $app->requireRole(Role::Editor);

        return Response::html('<h1>Mein Modul</h1>');
    });
};
```

Regeln für Module: Rollen serverseitig prüfen (`requireRole`), bei POST `requireCsrf`, alle Ausgaben
escapen (`View::e`), nur vorbereitete SQL-Statements, Änderungen mit `$app->audit()` protokollieren.
Ein fehlerhaftes Modul wird protokolliert und übersprungen, die Website läuft weiter.

Kostenpflichtige Module und Templates von Michael Gahn DESIGN: https://michael-gahn.de

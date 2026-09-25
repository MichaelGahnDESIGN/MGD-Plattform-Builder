<?php

declare(strict_types=1);

/*
 * Projekt-Hooks. Wird bei jedem Aufruf nach dem Bootstrap geladen.
 * Im Backoffice nur bearbeitbar, wenn security.allow_php_editor = true (config.php)
 * UND die Einstellung "PHP-Editor" aktiv ist. Vor jedem Speichern wird eine Sicherung angelegt.
 *
 * Verfügbar: $app (MGD\Starter\Core\App)
 */

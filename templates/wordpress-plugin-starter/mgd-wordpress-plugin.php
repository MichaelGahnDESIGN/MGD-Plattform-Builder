<?php
/**
 * Plugin Name: MGD WordPress Plugin Starter
 * Description: Sicheres Startgerüst für ein WordPress-Plugin von Michael Gahn DESIGN.
 * Version: 0.0.1
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: LicenseRef-MGD
 * Text Domain: mgd-wordpress-plugin
 */

defined('ABSPATH') || exit;

/** Das Gerüst registriert bewusst nur eine geschützte, lokale Einstiegsseite. */
final class MGD_WordPress_Plugin_Starter {
    public static function boot(): void {
        add_action('admin_menu', [self::class, 'register_menu']);
    }

    public static function register_menu(): void {
        add_options_page(
            __('MGD Plugin Starter', 'mgd-wordpress-plugin'),
            __('MGD Plugin Starter', 'mgd-wordpress-plugin'),
            'manage_options',
            'mgd-wordpress-plugin',
            [self::class, 'render_settings_page']
        );
    }

    public static function render_settings_page(): void {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Du darfst diese Seite nicht öffnen.', 'mgd-wordpress-plugin'));
        }
        echo '<div class="wrap"><h1>' . esc_html__('MGD Plugin Starter', 'mgd-wordpress-plugin') . '</h1>';
        echo '<p>' . esc_html__('Erst Funktionen, Datenminimierung und Berechtigungen festlegen – dann Einstellungen ergänzen.', 'mgd-wordpress-plugin') . '</p></div>';
    }
}

MGD_WordPress_Plugin_Starter::boot();

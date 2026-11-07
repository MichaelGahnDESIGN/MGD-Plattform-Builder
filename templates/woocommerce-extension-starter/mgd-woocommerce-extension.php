<?php
/**
 * Plugin Name: MGD WooCommerce Extension Starter
 * Description: Sicheres Startgerüst für eine WooCommerce-Erweiterung.
 * Version: 0.0.1
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * License: LicenseRef-MGD
 */

defined('ABSPATH') || exit;

final class MGD_WooCommerce_Extension_Starter {
    public static function boot(): void {
        add_action('admin_notices', [self::class, 'dependency_notice']);
    }

    public static function dependency_notice(): void {
        if (class_exists('WooCommerce')) {
            return;
        }
        echo '<div class="notice notice-warning"><p>' . esc_html__('MGD WooCommerce Extension Starter benötigt WooCommerce.', 'mgd-woocommerce-extension') . '</p></div>';
    }
}

MGD_WooCommerce_Extension_Starter::boot();

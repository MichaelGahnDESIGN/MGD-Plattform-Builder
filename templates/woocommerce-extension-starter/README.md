# MGD WooCommerce Extension Starter

Ein bewusst kleiner Plugin-Start für WooCommerce. Er prüft nur, ob WooCommerce aktiv ist; er verarbeitet keine Bestellung und speichert keine Kundendaten.

Vor dem ersten WooCommerce-Hook festlegen: Auslöser, Berechtigung, minimal benötigte Felder, Speicherort, Aufbewahrungs- und Löschfrist. Gastbestellungen nie stillschweigend mit einem Kundenkonto verknüpfen.

Prüfen: `php -l mgd-woocommerce-extension.php`, Aktivierung mit und ohne WooCommerce sowie ein manueller Test der späteren Bestell-/Rechtegrenze in einer Testinstanz.

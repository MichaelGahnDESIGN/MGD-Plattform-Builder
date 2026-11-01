<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

/**
 * Optionale Faehigkeit eines Ziels: Die Referenz einer Teildatei am Ziel ergibt sich vorab aus dem Teilnamen (FTP:
 * <base_path>/<ordner>/<teil>). Der Runner speichert sie in backup_uploads.remote_ref, BEVOR der erste Abschnitt hochgeladen
 * wird. So bleibt auch ein halb hochgeladener Teil nach Abbruch oder Aufgabe des Ziels fuer die Aufbewahrung loeschbar
 * (Loeschen nur ueber gespeicherte Referenzen). Ziele, bei denen erst der Anbieter die Referenz vergibt, setzen sie nach
 * dem Upload und implementieren diese Schnittstelle nicht.
 */
interface PredictsPartRef
{
    /** Referenz der Teildatei am Ziel; $fileName ist der lokale Teilname (wird gegen die Namens-Regexe geprueft). */
    public function partRef(string $fileName): string;
}

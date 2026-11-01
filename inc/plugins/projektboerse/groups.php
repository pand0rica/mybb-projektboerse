<?php
/**
 * Projektboerse – Brücke zum Plugin "plottinggroups"
 *
 * Gruppen-Definitionen und Mitgliedschaften gehören dem eigenständigen Plugin
 * plottinggroups. Die Projektbörse speichert nur die Verknüpfung Projekt <-> gid
 * (Tabelle projektboerse_project_groups) und holt alles Weitere über die
 * öffentliche Fassade Plottinggroups_Api.
 *
 * plottinggroups ist eine OPTIONALE Abhängigkeit: fehlt es oder ist es
 * deaktiviert, liefern alle Methoden neutrale Werte ([] bzw. ''), sodass die
 * Projektbörse ohne Fehler weiterläuft – nur ohne Gruppenzuweisung.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

class ProjektboerseGroups
{
    /**
     * Ist das plottinggroups-Plugin installiert UND aktiv?
     *
     * MyBB bindet die Hauptdatei eines Plugins nur ein solange es aktiv ist –
     * die Existenz von plottinggroups_load_core() ist daher der zuverlässige
     * Gesamtcheck. Ergebnis wird pro Request gemerkt.
     */
    public static function available(): bool
    {
        static $checked = null;

        if ($checked !== null) {
            return $checked;
        }

        if (function_exists('plottinggroups_load_core')) {
            plottinggroups_load_core();
        }
        $checked = class_exists('Plottinggroups_Api');

        return $checked;
    }

    /** @return array gid => name, nur freigegebene Gruppen */
    public static function approvedGroups(): array
    {
        return self::available() ? Plottinggroups_Api::approvedGroups() : [];
    }

    /** @return array gid => name für die übergebenen gids (jeder Freigabestatus) */
    public static function groupNames(array $gids): array
    {
        return self::available() ? Plottinggroups_Api::groupNames($gids) : [];
    }

    /** @return int[] uids aller Mitglieder der übergebenen Gruppen */
    public static function memberUids(array $gids): array
    {
        return self::available() ? Plottinggroups_Api::memberUids($gids) : [];
    }

    /** @return int[] die freigegebenen gids aus der übergebenen Liste */
    public static function filterApprovedGids(array $gids): array
    {
        return self::available() ? Plottinggroups_Api::filterApprovedGids($gids) : [];
    }

    /** @return string URL der Gruppenübersicht, '' wenn plottinggroups fehlt */
    public static function groupsUrl(): string
    {
        return self::available() ? Plottinggroups_Api::groupsUrl() : '';
    }

    /**
     * Schreibt ein Ereignis in die "Neueste Ereignisse"-Historie einer Gruppe.
     *
     * Dünne Kapselung von Plottinggroups_Api::logEvent(). Ohne plottinggroups
     * (deaktiviert/deinstalliert) passiert nichts – der Aufrufer muss nicht
     * prüfen, ob die Historie existiert.
     *
     * Der $type-Slug wird in der Anzeige über den Sprachschlüssel
     * plottinggroups_event_type_<type> aufgelöst; die passenden Keys liegen in
     * der Projektbörse-Sprachdatei (via global_start auf jeder Seite geladen),
     * damit hier keine Änderung am plottinggroups-Plugin nötig ist.
     *
     * @param int    $gid      Ziel-Gruppe.
     * @param string $type     Typ-Slug (z.B. 'projektboerse_approved').
     * @param string $title    Klartext-Titel (roh; wird beim Rendern escaped).
     * @param string $link     relative/http(s)-URL zur Quelle.
     * @param int    $uid      auslösende:r User (0 = System).
     * @param int    $dateline Unix-Zeit des Ereignisses; 0 = jetzt. Wird für den
     *                         Backfill genutzt, um historische Zeitpunkte zu setzen.
     */
    public static function logEvent(int $gid, string $type, string $title, string $link, int $uid = 0, int $dateline = 0): void
    {
        // available() bestätigt nur die Klasse. logEvent() kam erst mit
        // plottinggroups 1.1.0 (Events-Feature) dazu – auf älteren Installationen
        // fehlt die Methode UND die Tabelle plottinggroups_events. Ohne diesen
        // method_exists-Check wäre der Aufruf dort ein Fatal ("undefined method"),
        // und zwar erst NACH dem Status-Update. Version-Skew => sauberer No-Op.
        if (!self::available() || !method_exists('Plottinggroups_Api', 'logEvent')) {
            return;
        }
        Plottinggroups_Api::logEvent($gid, $type, $title, $link, $uid, $dateline);
    }

    /**
     * Existiert bereits ein Ereignis mit dieser (gid, type, link)-Kombination?
     *
     * Wird ausschließlich vom Backfill zur Dedup-Prüfung genutzt, damit ein
     * mehrfaches Nachtragen – und die Überschneidung mit bereits live
     * geschriebenen Events – keine Dubletten erzeugt. (gid, type, link) ist
     * eindeutig, weil der Link die pid enthält und der Typ Freigabe/Abschluss
     * unterscheidet.
     *
     * Bewusster, eng begrenzter Direktzugriff auf plottinggroups_events: die
     * öffentliche Api bietet keine Existenz-Abfrage. Gegen fehlende Tabelle
     * (Version < 1.1.0) abgesichert, damit hier nie ein Fehler entsteht.
     */
    public static function eventExists(int $gid, string $type, string $link): bool
    {
        global $db;

        if (!self::available() || !method_exists('Plottinggroups_Api', 'logEvent')) {
            return false;
        }
        if (!$db->table_exists('plottinggroups_events')) {
            return false;
        }

        $query = $db->simple_select('plottinggroups_events', 'eid',
            "gid = " . (int)$gid .
            " AND type = '" . $db->escape_string($type) . "'" .
            " AND link = '" . $db->escape_string($link) . "'",
            ['limit' => 1]);

        return (bool)$db->fetch_field($query, 'eid');
    }

    /**
     * Bereitet eine übermittelte gid-Liste zum Speichern auf.
     *
     * Der Rückgabewert unterscheidet bewusst zwei Fälle, die sonst beide als
     * leeres Array ankämen:
     *
     *   null  = "Zuordnungen nicht anfassen" (plottinggroups nicht verfügbar)
     *   int[] = die zu speichernde Menge (darf leer sein = alles abwählen)
     *
     * Ohne diese Unterscheidung würde ein Speichervorgang bei deaktiviertem
     * plottinggroups sämtliche Zuordnungen löschen, weil filterApprovedGids()
     * dann nichts mehr durchlässt.
     *
     * @param array $raw rohe Formulareingabe (gids[])
     * @param int   $pid Projekt, dessen Bestand geprüft wird
     * @return int[]|null
     */
    public static function normalizeSubmittedGids(array $raw, int $pid): ?array
    {
        if (!self::available()) {
            return null;
        }

        $submitted = array_values(array_unique(array_filter(
            array_map('intval', $raw),
            function ($gid) { return $gid > 0; }
        )));

        $valid = self::filterApprovedGids($submitted);

        // Bereits verknüpfte Gruppen, die inzwischen nicht mehr freigegeben
        // sind, stehen nicht im Formular und würden beim Speichern still
        // verschwinden. Sie bleiben deshalb erhalten.
        $existing = ProjektboerseProject::get_group_ids($pid);
        $stale    = array_diff($existing, array_keys(self::approvedGroups()));

        return array_values(array_unique(array_merge($valid, $stale)));
    }
}

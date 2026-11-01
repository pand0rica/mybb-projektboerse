<?php
/**
 * Projektboerse – Datenbank-Schema
 *
 * Wird einmalig beim install() der Hauptdatei eingebunden. Legt alle
 * benötigten Tabellen an und füllt die Stamm-Tabellen (types/categories/
 * communication) mit sinnvollen Default-Einträgen.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Legt die Verknüpfungstabelle Projekt <-> Plotting-Gruppe an.
 *
 * Eigene Funktion, weil sowohl create_tables() (Neuinstallation) als auch
 * upgrade_schema() (Bestandsinstallation) sie brauchen und die beiden DDLs
 * nicht auseinanderlaufen dürfen.
 *
 * `gid` referenziert plottinggroups_groups.gid bewusst OHNE Foreign Key – die
 * Zieltabelle gehört einem anderen, optionalen Plugin.
 *
 * @param object $db MyBB-Datenbank-Objekt.
 */
function projektboerse_db_create_project_groups($db): void
{
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_project_groups` (
        `pgid`     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid`      INT(10) UNSIGNED NOT NULL,
        `gid`      INT(10) UNSIGNED NOT NULL,
        `dateline` INT(10) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (`pgid`),
        KEY `pid` (`pid`),
        KEY `gid` (`gid`),
        UNIQUE KEY `unique_group` (`pid`,`gid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");
}

/**
 * Schema-Nachzug für Bestandsinstallationen.
 *
 * install() läuft nur ein einziges Mal, activate() dagegen bei jeder
 * Aktivierung – deshalb muss der Umstieg von Tags auf Plotting-Gruppen hier
 * passieren. Idempotent: die Tabelle wird nur angelegt wenn sie fehlt, die
 * Alt-Tabellen nur gelöscht wenn sie noch da sind.
 *
 * @param object $db MyBB-Datenbank-Objekt.
 */
function projektboerse_db_upgrade_schema($db): void
{
    projektboerse_db_create_project_groups($db);

    // Tags-Feature (bis 2.2.0) – ersatzlos entfernt, keine Migration.
    foreach (["projektboerse_project_tags", "projektboerse_tags"] as $legacy) {
        if ($db->table_exists($legacy)) {
            $db->drop_table($legacy);
        }
    }
}

/**
 * Legt alle Plugin-Tabellen an (idempotent über CREATE TABLE IF NOT EXISTS).
 *
 * @param object $db MyBB-Datenbank-Objekt.
 */
function projektboerse_db_create_tables($db): void
{
    // Haupttabelle: Projekte.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_projects` (
        `pid`                     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `uid`                     INT(10) UNSIGNED NOT NULL,
        `title`                   VARCHAR(255) NOT NULL,
        `description`             TEXT NOT NULL,
        `reward`                  VARCHAR(500) DEFAULT NULL,
        `contact_person_uid`      INT(10) UNSIGNED DEFAULT NULL,
        `feedback_deadline`       DATETIME DEFAULT NULL,
        `implementation_deadline` DATETIME DEFAULT NULL,
        `communication_id`        INT(10) UNSIGNED DEFAULT NULL,
        `project_type_id`         INT(10) UNSIGNED DEFAULT NULL,
        `category_id`             INT(10) UNSIGNED DEFAULT NULL,
        `wiki_links`              TEXT DEFAULT NULL,
        `status` ENUM('pending','active','open','implementation','completed','rejected','archived','inactive') NOT NULL DEFAULT 'pending',
        `created_at`              DATETIME NOT NULL,
        `updated_at`              DATETIME NOT NULL,
        `approved_at`             DATETIME DEFAULT NULL,
        `approved_by_uid`         INT(10) UNSIGNED DEFAULT NULL,
        `completed_at`            DATETIME DEFAULT NULL,
        PRIMARY KEY (`pid`),
        KEY `uid` (`uid`),
        KEY `status` (`status`),
        KEY `category_id` (`category_id`),
        KEY `project_type_id` (`project_type_id`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Bewerbungen.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_applications` (
        `aid`              INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid`              INT(10) UNSIGNED NOT NULL,
        `uid`              INT(10) UNSIGNED NOT NULL,
        `application_text` TEXT NOT NULL,
        `status` ENUM('submitted','accepted','rejected') NOT NULL DEFAULT 'submitted',
        `submitted_at`     DATETIME NOT NULL,
        `reviewed_at`      DATETIME DEFAULT NULL,
        `reviewed_by_uid`  INT(10) UNSIGNED DEFAULT NULL,
        `review_notes`     TEXT DEFAULT NULL,
        PRIMARY KEY (`aid`),
        KEY `pid` (`pid`),
        KEY `uid` (`uid`),
        KEY `status` (`status`),
        UNIQUE KEY `unique_application` (`pid`,`uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Kommentare (Feedbackphase).
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_comments` (
        `cid`        INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid`        INT(10) UNSIGNED NOT NULL,
        `uid`        INT(10) UNSIGNED NOT NULL,
        `comment`    TEXT NOT NULL,
        `created_at` DATETIME NOT NULL,
        `updated_at` DATETIME DEFAULT NULL,
        PRIMARY KEY (`cid`),
        KEY `pid` (`pid`),
        KEY `uid` (`uid`),
        KEY `created_at` (`created_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Projekt <-> Plotting-Gruppe (n:m).
    projektboerse_db_create_project_groups($db);

    // Projekt-Typen.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_types` (
        `type_id`     INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(100) NOT NULL,
        `disporder`   INT(10) NOT NULL DEFAULT 0,
        PRIMARY KEY (`type_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Kategorien.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_categories` (
        `category_id` INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`        VARCHAR(100) NOT NULL,
        `description` VARCHAR(255) DEFAULT NULL,
        `disporder`   INT(10) NOT NULL DEFAULT 0,
        PRIMARY KEY (`category_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Kommunikationswege.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_communication` (
        `comm_id`   INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `name`      VARCHAR(100) NOT NULL,
        `disporder` INT(10) NOT NULL DEFAULT 0,
        PRIMARY KEY (`comm_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Umsetzende Personen.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_implementers` (
        `iid`             INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid`             INT(10) UNSIGNED NOT NULL,
        `uid`             INT(10) UNSIGNED NOT NULL,
        `assigned_at`     DATETIME NOT NULL,
        `assigned_by_uid` INT(10) UNSIGNED NOT NULL,
        PRIMARY KEY (`iid`),
        KEY `pid` (`pid`),
        KEY `uid` (`uid`),
        UNIQUE KEY `unique_implementer` (`pid`,`uid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Alert-Log.
    $db->write_query("CREATE TABLE IF NOT EXISTS `" . TABLE_PREFIX . "projektboerse_alertlog` (
        `alid`          INT(10) UNSIGNED NOT NULL AUTO_INCREMENT,
        `pid`           INT(10) UNSIGNED DEFAULT NULL,
        `event_type`    VARCHAR(50) NOT NULL,
        `recipient_uid` INT(10) UNSIGNED NOT NULL,
        `sent_at`       DATETIME NOT NULL,
        `alert_data`    TEXT DEFAULT NULL,
        PRIMARY KEY (`alid`),
        KEY `pid` (`pid`),
        KEY `recipient_uid` (`recipient_uid`),
        KEY `event_type` (`event_type`),
        KEY `sent_at` (`sent_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Default-Daten einfügen (nur wenn Tabelle leer).
    projektboerse_db_seed_defaults($db);
}

/**
 * Befüllt die Stamm-Tabellen mit Default-Einträgen, falls noch leer.
 *
 * @param object $db MyBB-Datenbank-Objekt.
 */
function projektboerse_db_seed_defaults($db): void
{
    // Projekt-Typen.
    $count = $db->fetch_field($db->simple_select('projektboerse_types', 'COUNT(*) AS c'), 'c');
    if ((int)$count === 0) {
        $defaults = [
            ['Plugin-Entwicklung', 1],
            ['Theme-Entwicklung',  2],
            ['Grafik/Design',      3],
            ['Sonstiges',          4],
        ];
        foreach ($defaults as [$name, $order]) {
            $db->insert_query('projektboerse_types', [
                'name'      => $db->escape_string($name),
                'disporder' => (int)$order,
            ]);
        }
    }

    // Kategorien.
    $count = $db->fetch_field($db->simple_select('projektboerse_categories', 'COUNT(*) AS c'), 'c');
    if ((int)$count === 0) {
        $defaults = [
            ['Entwicklung',    1],
            ['Design',         2],
            ['Content',        3],
            ['Administration', 4],
        ];
        foreach ($defaults as [$name, $order]) {
            $db->insert_query('projektboerse_categories', [
                'name'      => $db->escape_string($name),
                'disporder' => (int)$order,
            ]);
        }
    }

    // Kommunikationswege.
    $count = $db->fetch_field($db->simple_select('projektboerse_communication', 'COUNT(*) AS c'), 'c');
    if ((int)$count === 0) {
        $defaults = [
            ['Private Nachricht', 1],
            ['Discord',           2],
            ['E-Mail',            3],
            ['Teamspeak',         4],
        ];
        foreach ($defaults as [$name, $order]) {
            $db->insert_query('projektboerse_communication', [
                'name'      => $db->escape_string($name),
                'disporder' => (int)$order,
            ]);
        }
    }
}

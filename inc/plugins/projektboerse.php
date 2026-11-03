<?php
/**
 * Projektboerse – Hauptplugin-Datei
 *
 * Vollständige Projektverwaltung für MyBB 1.8: Einreichung, Freigabe-Workflow,
 * Feedback-Phase, Bewerbungen, Umsetzung, Alerts und Deadlines.
 *
 * @package projektboerse
 * @author  pand0rica
 * @version 3.2.1
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Hooks direkt auf Top-Level registrieren (laufen bei jeder Anfrage).
$plugins->add_hook("global_start",                "projektboerse_handle_global");
$plugins->add_hook("index_start",                 "projektboerse_handle_index");
$plugins->add_hook("modcp_start",                 "projektboerse_handle_modcp");
$plugins->add_hook("admin_config_menu",           "projektboerse_admin_config_menu");
$plugins->add_hook("admin_config_action_handler", "projektboerse_admin_config_action_handler");
$plugins->add_hook("admin_config_permissions",    "projektboerse_admin_config_permissions");
// MyAlerts 2.x hat keinen Formatter-Lade-Hook – wir registrieren unseren
// Formatter in projektboerse_handle_global() (global_start) selbst.

// Module nur laden, wenn das Plugin installiert ist.
if (function_exists('projektboerse_is_installed') && projektboerse_is_installed()) {
    require_once MYBB_ROOT . "inc/plugins/projektboerse/groups.php";
    require_once MYBB_ROOT . "inc/plugins/projektboerse/classes.php";
    require_once MYBB_ROOT . "inc/plugins/projektboerse/hooks.php";
}

/**
 * Plugin-Metadaten für den ACP-Plugin-Manager.
 *
 * @return array
 */
function projektboerse_info(): array
{
    return [
        "name"          => "Projektbörse",
        "description"   => "Vollständige Projektverwaltung mit Einreichung, Freigabe, Feedback, Bewerbungen und Umsetzung.",
        "website"       => "https://github.com/pand0rica/projektboerse",
        "author"        => "pand0rica",
        "authorsite"    => "https://github.com/pand0rica",
        "version"       => "3.2.1",
        "guid"          => "",
        "codename"      => "projektboerse",
        "compatibility" => "18*",
    ];
}

/**
 * Wird einmalig bei der Installation aufgerufen: legt Tabellen und Standarddaten an.
 */
function projektboerse_install(): void
{
    global $db;

    require_once MYBB_ROOT . "inc/plugins/projektboerse/database.php";
    projektboerse_db_create_tables($db);
}

/**
 * Prüft, ob das Plugin installiert ist (Haupttabelle vorhanden).
 *
 * @return bool
 */
function projektboerse_is_installed(): bool
{
    global $db;
    return $db->table_exists("projektboerse_projects");
}

/**
 * Wird bei der Deinstallation aufgerufen: entfernt alle Tabellen.
 * Aktivierungs-Artefakte (Templates, Settings, Stylesheet) werden zuvor
 * über projektboerse_deactivate() vom ACP entfernt.
 */
function projektboerse_uninstall(): void
{
    global $db;

    $tables = [
        "projektboerse_alertlog",
        "projektboerse_implementers",
        "projektboerse_project_groups",
        "projektboerse_comments",
        "projektboerse_applications",
        "projektboerse_communication",
        "projektboerse_categories",
        "projektboerse_types",
        "projektboerse_projects",
        // Tags-Feature (bis 2.2.0). activate() entfernt die Tabellen bereits;
        // hier nur noch defensiv für Installationen, die nie reaktiviert wurden.
        "projektboerse_project_tags",
        "projektboerse_tags",
    ];

    foreach ($tables as $table) {
        if ($db->table_exists($table)) {
            $db->drop_table($table);
        }
    }

    // Settings + Gruppe entfernen (gehören zu install/uninstall, nicht zu
    // activate/deactivate – sonst gehen individuelle Werte bei jedem Update verloren).
    $db->delete_query('settings', "name LIKE 'projektboerse\\_%'");
    $db->delete_query('settinggroups', "name = 'projektboerse'");
    rebuild_settings();
}

/**
 * Wird bei jeder Aktivierung aufgerufen: legt Settings, Templates, Stylesheet
 * und MyBB-Task an. Idempotent – fehlende Einzel-Settings späterer Versionen
 * werden ergänzt (Upgrade-Pfad).
 */
function projektboerse_activate(): void
{
    global $db;

    require_once MYBB_ROOT . "inc/plugins/projektboerse/templates.php";
    require_once MYBB_ROOT . "inc/plugins/projektboerse/database.php";

    // 0) Schema nachziehen --------------------------------------------------
    // install() läuft nur einmal; Bestandsinstallationen bekommen neue bzw.
    // entfernte Tabellen deshalb hier.
    projektboerse_db_upgrade_schema($db);

    // 1) Settings -----------------------------------------------------------
    $existing = $db->fetch_array($db->simple_select('settinggroups', 'gid', "name = 'projektboerse'"));
    if (!$existing) {
        $gid = (int)$db->insert_query('settinggroups', [
            'name'        => 'projektboerse',
            'title'       => 'Projektbörse',
            'description' => 'Einstellungen für das Projektbörse-Plugin.',
            'disporder'   => 100,
            'isdefault'   => 0,
        ]);
        projektboerse_install_settings($gid);
    } else {
        // Upgrade-Pfad: fehlende Settings einer späteren Version nachrüsten.
        projektboerse_install_settings((int)$existing['gid'], true);
    }
    rebuild_settings();

    // 2) Templates ----------------------------------------------------------
    projektboerse_insert_templates($db);

    // 3) Stylesheet in alle Themes ------------------------------------------
    projektboerse_install_stylesheet();

    // 4) Task ---------------------------------------------------------------
    projektboerse_install_task();

    // 5) MyAlerts-AlertTypes registrieren ----------------------------------
    projektboerse_register_alert_types();
}

/**
 * Alle Alert-Codes, die das Plugin verwendet. Jeder Code wird beim Aktivieren
 * als MyAlerts-AlertType registriert (falls MyAlerts installiert ist).
 *
 * @return string[]
 */
function projektboerse_alert_codes(): array
{
    return [
        'projektboerse_new_project',
        'projektboerse_approved',
        'projektboerse_published',
        'projektboerse_open',
        'projektboerse_feedback',
        'projektboerse_rejected',
        'projektboerse_archived',
        'projektboerse_completed',
        'projektboerse_new_application',
        'projektboerse_application_accepted',
        'projektboerse_application_rejected',
        'projektboerse_new_comment',
        'projektboerse_implementer_assigned',
        'projektboerse_group_assigned',
        'projektboerse_deadline',
        'projektboerse_feedback_ended',
    ];
}

/**
 * Registriert alle Alert-Types in MyAlerts. Falls MyAlerts nicht installiert
 * ist, passiert nichts.
 */
function projektboerse_register_alert_types(): void
{
    global $db, $cache;

    if (!class_exists('MybbStuff_MyAlerts_AlertTypeManager')
        || !class_exists('MybbStuff_MyAlerts_Entity_AlertType')) {
        return;
    }

    $alert_type_manager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if (!$alert_type_manager) {
        $alert_type_manager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    }

    $types = [];
    foreach (projektboerse_alert_codes() as $code) {
        $alert_type = new MybbStuff_MyAlerts_Entity_AlertType();
        $alert_type->setCode($code);
        $alert_type->setEnabled(true);
        $alert_type->setCanBeUserDisabled(true);
        $types[] = $alert_type;
    }

    $alert_type_manager->addTypes($types);
}

/**
 * Entfernt alle Alert-Types aus MyAlerts.
 */
function projektboerse_unregister_alert_types(): void
{
    global $db, $cache;

    if (!class_exists('MybbStuff_MyAlerts_AlertTypeManager')) {
        return;
    }

    $alert_type_manager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
    if (!$alert_type_manager) {
        $alert_type_manager = MybbStuff_MyAlerts_AlertTypeManager::createInstance($db, $cache);
    }

    foreach (projektboerse_alert_codes() as $code) {
        $alert_type_manager->deleteByCode($code);
    }
}

/**
 * Registriert unseren universalen Projektboerse_MyAlerts_Formatter für alle
 * projektboerse_*-AlertTypes. Wird aus projektboerse_handle_global()
 * (global_start) aufgerufen.
 *
 * MyAlerts 2.x bietet keinen Lade-Hook für Formatter – wir müssen
 * `myalerts_create_instances()` selbst rufen (idempotent), damit der
 * AlertFormatterManager-Singleton existiert, und dann unseren Formatter
 * registrieren. Static guard sorgt dafür, dass das pro Request nur einmal
 * passiert.
 */
function projektboerse_register_alert_formatter(): void
{
    static $registered = false;
    global $mybb, $lang;

    if ($registered) {
        return;
    }

    if (!class_exists('MybbStuff_MyAlerts_AlertFormatterManager')
        || !class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
        return;
    }

    // FormatterManager-Singleton holen oder erstellen (gossip-main-Pattern).
    $manager = MybbStuff_MyAlerts_AlertFormatterManager::getInstance();
    if (!$manager) {
        $manager = MybbStuff_MyAlerts_AlertFormatterManager::createInstance($mybb, $lang);
    }
    if (!$manager) {
        return;
    }

    require_once MYBB_ROOT . 'inc/plugins/projektboerse/myalerts_formatter.php';
    if (!class_exists('Projektboerse_MyAlerts_Formatter')) {
        return;
    }

    // Konstruktor der AbstractFormatter-Parent-Klasse erwartet ($mybb, $lang, $code).
    // registerFormatter() nimmt nur den Formatter – der Code wird aus dem
    // Formatter selbst gelesen.
    foreach (projektboerse_alert_codes() as $code) {
        $manager->registerFormatter(
            new Projektboerse_MyAlerts_Formatter($mybb, $lang, $code)
        );
    }

    $registered = true;
}

/**
 * Wird bei jeder Deaktivierung aufgerufen: Spiegelbild zu activate().
 * Entfernt Templates, Settings, settinggroup, Stylesheet und Task.
 */
function projektboerse_deactivate(): void
{
    global $db;

    // Templates entfernen.
    $db->delete_query('templates', "title LIKE 'projektboerse_%'");

    // Settings + settinggroup bleiben beim Deaktivieren erhalten (debugging.md #21);
    // sie werden erst in projektboerse_uninstall() entfernt.

    // Stylesheet aus allen Themes entfernen.
    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    $db->delete_query("themestylesheets", "name = 'projektboerse.css'");
    $themes = $db->simple_select("themes", "tid");
    while ($theme = $db->fetch_array($themes)) {
        update_theme_stylesheet_list((int)$theme["tid"]);
    }

    // Task entfernen.
    $db->delete_query('tasks', "file = 'projektboerse_deadlines'");

    // MyAlerts-AlertTypes entfernen.
    projektboerse_unregister_alert_types();

    rebuild_settings();
}

/**
 * Legt alle Plugin-Settings an. Wenn $upgrade_only=true, werden nur fehlende
 * Einzel-Settings ergänzt (Upgrade-Pfad).
 *
 * @param int  $gid           Setting-Gruppen-ID.
 * @param bool $upgrade_only  Nur fehlende Settings anlegen.
 */
function projektboerse_install_settings(int $gid, bool $upgrade_only = false): void
{
    global $db;

    $settings = [
        ['projektboerse_enabled',            'Projektbörse aktiviert',               'Plugin aktivieren/deaktivieren.',                                                       'yesno',         '1',     1],
        ['projektboerse_allowed_groups',     'Usergruppen: Berechtigt für Projektbörse', 'Master-Allowlist: nur User dieser Gruppen dürfen überhaupt mit der Projektbörse interagieren (einreichen, bewerben, Team) und Alerts empfangen. Hier Haupt-Account-Gruppen auswählen, Charakter-Accounts weglassen. Leer = keine Einschränkung.', 'groupselect', '2,3,4,43', 2],
        ['projektboerse_submit_groups',      'Usergruppen: Projekte einreichen',     'Welche Usergruppen dürfen Projekte einreichen? Wird zusätzlich durch die Master-Allowlist gefiltert.', 'groupselect', '2,3,4,43', 3],
        ['projektboerse_apply_groups',       'Usergruppen: Bewerbungen',             'Welche Usergruppen dürfen sich bewerben? Wird zusätzlich durch die Master-Allowlist gefiltert.', 'groupselect', '2,3,4,43', 4],
        ['projektboerse_team_groups',        'Usergruppen: Teammitglieder',          'Welche Usergruppen sind Teammitglieder (Verwaltung)? Wird zusätzlich durch die Master-Allowlist gefiltert.', 'groupselect', '3,4',      5],
        ['projektboerse_alert_new_project',  'Alert: Neues Projekt',                 'Alert senden bei neuem Projekt?',                                                       'yesno',   '1',     6],
        ['projektboerse_alert_approved',     'Alert: Projekt freigegeben',           'Alert senden, wenn Projekt freigegeben wurde?',                                         'yesno',   '1',     7],
        ['projektboerse_alert_published',    'Alert: Projekt in der Projektbörse (alle User)', 'Alert an ALLE berechtigten User senden, wenn ein Projekt freigegeben wird und in der Projektbörse (Feedbackphase) erscheint? Empfänger werden über die Master-Allowlist gefiltert – reine Charakter-Accounts erhalten keinen Alert.', 'yesno', '1', 8],
        ['projektboerse_alert_open',         'Alert: Bewerbungsphase (alle User)',   'Alert an ALLE berechtigten User senden, wenn ein Projekt von der Feedback- in die Bewerbungsphase wechselt (für Bewerbungen offen)? Empfänger werden über die Master-Allowlist gefiltert – reine Charakter-Accounts erhalten keinen Alert.', 'yesno', '1', 9],
        ['projektboerse_alert_new_application', 'Alert: Neue Bewerbung',             'Alert senden bei neuer Bewerbung?',                                                     'yesno',   '1',     10],
        ['projektboerse_alert_application_accepted', 'Alert: Bewerbung angenommen', 'Alert senden, wenn Bewerbung angenommen wurde?',                                         'yesno',   '1',     11],
        ['projektboerse_alert_application_rejected', 'Alert: Bewerbung abgelehnt',  'Alert senden, wenn Bewerbung abgelehnt wurde?',                                          'yesno',   '1',     12],
        ['projektboerse_alert_comment',      'Alert: Neuer Kommentar',               'Alert senden bei neuem Kommentar?',                                                     'yesno',   '1',     13],
        ['projektboerse_alert_deadline',     'Alert: Deadline überschritten',        'Alert senden bei überschrittener Deadline?',                                            'yesno',   '1',     14],
        ['projektboerse_alert_archived',     'Alert: Projekt archiviert',            'Alert senden, wenn Projekt archiviert wurde?',                                          'yesno',   '1',     15],
        ['projektboerse_alert_completed',    'Alert: Projekt abgeschlossen',         'Alert senden, wenn Projekt abgeschlossen wurde?',                                       'yesno',   '1',     16],
        ['projektboerse_alert_rejected',     'Alert: Projekt abgelehnt',             'Alert senden, wenn Projekt abgelehnt wurde?',                                           'yesno',   '1',     17],
        ['projektboerse_alert_implementer_assigned', 'Alert: Umsetzer zugewiesen',  'Alert senden, wenn User als Umsetzer zugewiesen wurde?',                                 'yesno',   '1',     18],
        ['projektboerse_alert_group_assigned', 'Alert: Plotting-Gruppe zugewiesen', 'Alert an die Mitglieder einer Plotting-Gruppe senden, wenn ein Projekt dieser Gruppe zugewiesen wird? Setzt das aktive Plugin plottinggroups voraus. Empfänger werden zusätzlich über die Master-Allowlist gefiltert – Gruppenmitglieder außerhalb der berechtigten Usergruppen (typischerweise Charakter-Accounts) erhalten keinen Alert.', 'yesno', '1', 19],
        ['projektboerse_alert_new_project_recipients', 'Alert-Empfänger: Neue Projekte', 'Zusätzliche User-IDs für neue Projekte (Kommagetrennt, zusätzlich zum Team)',       'text',    '',      20],
        ['projektboerse_feedback_recipients', 'Alert-Empfänger: Feedbackphase',      'User-IDs für Alerts in Feedbackphase (Kommagetrennt)',                                  'text',    '',      21],
        ['projektboerse_default_feedback_days', 'Standard Feedback-Deadline (Tage)', 'Wie viele Tage standardmäßig für die Feedback-Phase?',                                  'numeric', '7',     22],
        ['projektboerse_default_implementation_days', 'Standard Umsetzungs-Deadline (Tage)', 'Wie viele Tage standardmäßig für die Umsetzung?',                                'numeric', '30',    23],
        ['projektboerse_per_page',           'Projekte pro Seite',                   'Wie viele Projekte pro Seite anzeigen?',                                                'numeric', '20',    24],
        ['projektboerse_allow_html',         'HTML in Projekt-Inhalten erlauben',    'Wenn aktiv, werden Beschreibungen, Belohnungs-Texte, Wiki-Links, Bewerbungs-Texte und Kommentare als HTML interpretiert. Erlaubt sind nur die sicheren Tags p, br, b/strong, i/em, u, s, ul/ol/li, blockquote, h1–h6, code, pre, a, img. Alle Skript-Tags, Event-Handler (onclick, onerror, …) sowie javascript:/data:/vbscript:-URLs werden serverseitig entfernt.', 'yesno', '0', 25],
    ];

    foreach ($settings as $setting) {
        [$name, $title, $description, $optionscode, $value, $disporder] = $setting;

        if ($upgrade_only) {
            $exists = $db->fetch_array($db->simple_select('settings', 'sid, optionscode',
                "name = '" . $db->escape_string($name) . "'"));
            if ($exists) {
                // optionscode aktualisieren, falls geändert (z.B. text → groupselect).
                // Andere User-Werte (value, title, description) bleiben unangetastet.
                if ((string)$exists['optionscode'] !== $optionscode) {
                    $db->update_query('settings', [
                        'optionscode' => $db->escape_string($optionscode),
                    ], "sid = " . (int)$exists['sid'], 1);
                }
                continue;
            }
        }

        $db->insert_query('settings', [
            'name'        => $db->escape_string($name),
            'title'       => $db->escape_string($title),
            'description' => $db->escape_string($description),
            'optionscode' => $db->escape_string($optionscode),
            'value'       => $db->escape_string($value),
            'disporder'   => (int)$disporder,
            'gid'         => (int)$gid,
        ]);
    }
}

/**
 * Stylesheet in alle Themes einfügen (oder aktualisieren) und Theme-Cache neu bauen.
 */
function projektboerse_install_stylesheet(): void
{
    global $db;

    require_once MYBB_ROOT . "inc/adminfunctions_templates.php";
    require_once MYBB_ADMIN_DIR . "inc/functions_themes.php";

    $css_path = MYBB_ROOT . "inc/plugins/projektboerse/assets/projektboerse.css";
    if (!file_exists($css_path)) {
        return;
    }

    $css    = $db->escape_string((string)file_get_contents($css_path));
    $themes = $db->simple_select("themes", "tid");

    while ($theme = $db->fetch_array($themes)) {
        $tid      = (int)$theme["tid"];
        $existing = $db->fetch_array($db->simple_select(
            "themestylesheets", "sid",
            "name = 'projektboerse.css' AND tid = '" . $tid . "'"
        ));

        if ($existing) {
            $db->update_query("themestylesheets", [
                "stylesheet"   => $css,
                "lastmodified" => TIME_NOW,
            ], "sid='" . (int)$existing["sid"] . "'", 1);
        } else {
            $sid = $db->insert_query("themestylesheets", [
                "name"         => "projektboerse.css",
                "tid"          => $tid,
                "attachedto"   => "",
                "stylesheet"   => $css,
                "lastmodified" => TIME_NOW,
                "cachefile"    => "projektboerse.css",
            ]);
            $db->update_query("themestylesheets",
                ["cachefile" => "css.php?stylesheet={$sid}"],
                'sid = ' . (int)$sid, 1);
        }
        update_theme_stylesheet_list($tid);
    }
}

/**
 * Den Deadline-Task registrieren (idempotent).
 */
function projektboerse_install_task(): void
{
    global $db;

    require_once MYBB_ROOT . "inc/functions_task.php";

    $existing = $db->fetch_array($db->simple_select('tasks', '*', "file = 'projektboerse_deadlines'"));
    if ($existing) {
        // Up to 3.2.0 the task was stored with minute '*/30'. MyBB's scheduler has no step syntax:
        // fetch_next_run() then fails, nextrun stays 0 and the task blocks every other task.
        if ($existing['minute'] === '*/30') {
            $existing['minute'] = '0,30';
            $db->update_query('tasks', [
                'minute'  => '0,30',
                'nextrun' => fetch_next_run($existing),
                'locked'  => 0,
            ], 'tid = ' . (int)$existing['tid']);
        }
        return;
    }

    $new_task = [
        'title'       => 'Projektbörse: Deadline-Prüfung',
        'description' => 'Prüft Projekte auf überschrittene Deadlines und ändert Status automatisch.',
        'file'        => 'projektboerse_deadlines',
        'minute'      => '0,30',
        'hour'        => '*',
        'day'         => '*',
        'month'       => '*',
        'weekday'     => '*',
        'enabled'     => 1,
        'logging'     => 1,
        'nextrun'     => TIME_NOW,
    ];

    $new_task['nextrun'] = fetch_next_run($new_task);
    $db->insert_query('tasks', $new_task);
}

// ============================================================================
// ACP-Hook-Handler (top-level, immer registriert)
// ============================================================================

/**
 * Erweitert das ACP-Konfigurationsmenü um den Projektbörse-Eintrag.
 *
 * @param array $sub_menu Referenz auf das Untermenü.
 */
function projektboerse_admin_config_menu(&$sub_menu): void
{
    $sub_menu[] = [
        'id'    => 'projektboerse',
        'title' => 'Projektbörse',
        'link'  => 'index.php?module=config-projektboerse',
    ];
}

/**
 * Registriert die URL-Action für das ACP-Modul.
 *
 * @param array $actions Referenz auf das Action-Array.
 */
function projektboerse_admin_config_action_handler(&$actions): void
{
    $actions['projektboerse'] = [
        'active' => 'projektboerse',
        'file'   => 'projektboerse.php',
    ];
}

/**
 * Fügt die Admin-Permission für die Projektbörse hinzu.
 *
 * @param array $admin_permissions Referenz auf das Permission-Array.
 */
function projektboerse_admin_config_permissions(&$admin_permissions): void
{
    global $lang;
    $lang->load('projektboerse');
    $admin_permissions['projektboerse'] = $lang->projektboerse_admin_permission;
}

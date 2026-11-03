<?php
/**
 * Projektboerse – ACP-Modul (config).
 *
 * Verwaltung von Kategorien, Projekt-Typen, Kommunikationswegen,
 * Alert-Einstellungen und Statistiken.
 *
 * Plotting-Gruppen werden NICHT hier verwaltet, sondern im eigenständigen
 * Plugin plottinggroups (ACP → Werkzeuge & Wartung → Plotting-Gruppen).
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// Permission-Prüfung erfolgt MyBB-seitig über $page->add_breadcrumb_item +
// $admin_permissions['projektboerse'].

// ACP-Sprachdatei laden (inc/languages/<lang>/admin/projektboerse.lang.php).
// Ohne diesen Aufruf bleiben alle $lang->projektboerse_*-Strings leer
// (Breadcrumb, Tab-Beschriftungen, Tabellen-Header etc.).
$lang->load("projektboerse");

$page->add_breadcrumb_item($lang->projektboerse, "index.php?module=config-projektboerse");

$action = $mybb->get_input('action');

/**
 * Liefert die ACP-Navigationstabs.
 */
/**
 * Schreibende ACP-Aktionen: verify_post_check() bricht im ACP nicht ab,
 * daher Rückgabewert prüfen (debugging.md #23).
 */
function projektboerse_acp_verify_key(): void
{
    global $mybb, $lang;

    if (!verify_post_check($mybb->get_input('my_post_key'), true)) {
        flash_message($lang->invalid_post_verify_key2, 'error');
        admin_redirect('index.php?module=config-projektboerse');
    }
}

function projektboerse_acp_nav_tabs(string $active): void
{
    global $page, $lang;

    $tabs = [
        'overview' => [
            'title'       => $lang->projektboerse_overview,
            'link'        => 'index.php?module=config-projektboerse',
            'description' => $lang->projektboerse_tab_desc_overview,
        ],
        'categories' => [
            'title'       => $lang->projektboerse_categories,
            'link'        => 'index.php?module=config-projektboerse&action=categories',
            'description' => $lang->projektboerse_tab_desc_categories,
        ],
        'types' => [
            'title'       => $lang->projektboerse_types,
            'link'        => 'index.php?module=config-projektboerse&action=types',
            'description' => $lang->projektboerse_tab_desc_types,
        ],
        'communication' => [
            'title'       => $lang->projektboerse_communication,
            'link'        => 'index.php?module=config-projektboerse&action=communication',
            'description' => $lang->projektboerse_tab_desc_communication,
        ],
        'alerts' => [
            'title'       => $lang->projektboerse_alerts,
            'link'        => 'index.php?module=config-projektboerse&action=alerts',
            'description' => $lang->projektboerse_tab_desc_alerts,
        ],
        'statistics' => [
            'title'       => $lang->projektboerse_statistics,
            'link'        => 'index.php?module=config-projektboerse&action=statistics',
            'description' => $lang->projektboerse_tab_desc_statistics,
        ],
    ];

    $page->output_nav_tabs($tabs, $active);
}

switch ($action) {
    case 'categories':
        projektboerse_acp_categories();
        break;
    case 'types':
        projektboerse_acp_types();
        break;
    case 'communication':
        projektboerse_acp_communication();
        break;
    case 'alerts':
        projektboerse_acp_alerts();
        break;
    case 'statistics':
        projektboerse_acp_statistics();
        break;
    case 'backfill_events':
        projektboerse_acp_backfill_events();
        break;
    default:
        projektboerse_acp_home();
        break;
}

/**
 * Wartung: trägt vergangene Freigaben/Abschlüsse rückwirkend in die
 * Ereignis-Historie der zugeordneten Plotting-Gruppen ein. Idempotent.
 */
function projektboerse_acp_backfill_events(): void
{
    global $mybb, $lang;

    if ($mybb->request_method !== 'post') {
        admin_redirect("index.php?module=config-projektboerse");
    }
    projektboerse_acp_verify_key();

    $result = ProjektboerseProject::backfill_group_events();

    if (!empty($result['unavailable'])) {
        flash_message($lang->projektboerse_backfill_unavailable, 'error');
    } else {
        flash_message($lang->sprintf($lang->projektboerse_backfill_done,
            (int)$result['added'], (int)$result['skipped']), 'success');
    }
    admin_redirect("index.php?module=config-projektboerse");
}

/**
 * Generischer CRUD-Handler für einfache Stamm-Tabellen.
 *
 * @param string $table     Tabelle ohne TABLE_PREFIX.
 * @param string $pk_col    Name der Primärschlüsselspalte.
 * @param string $action    URL-Action (für Links).
 * @param string $title     Seitentitel.
 * @param string $nav_key   Key für die ACP-Tab-Navigation.
 * @param bool   $with_desc Zusätzliches Description-Feld unterstützen?
 * @param string $lang_stem Singular-Stamm für die Erfolgsmeldungen
 *                          (z.B. 'category' → projektboerse_category_added/updated/deleted).
 */
function projektboerse_acp_crud(string $table, string $pk_col, string $action,
    string $title, string $nav_key, bool $with_desc = false, string $lang_stem = 'category'): void
{
    global $page, $db, $mybb, $lang;

    $base_url = 'index.php?module=config-projektboerse&action=' . $action;

    // POST-Handling.
    if ($mybb->request_method === 'post') {
        projektboerse_acp_verify_key();

        $sub_action = $mybb->get_input('sub_action');
        $row_id     = (int)$mybb->get_input($pk_col, MyBB::INPUT_INT);

        if ($sub_action === 'delete') {
            $db->delete_query($table, $pk_col . " = " . $row_id);
            flash_message($lang->{'projektboerse_' . $lang_stem . '_deleted'}, 'success');
            admin_redirect($base_url);
        }

        $data = [
            'name'      => $db->escape_string((string)$mybb->get_input('name')),
            'disporder' => (int)$mybb->get_input('disporder', MyBB::INPUT_INT),
        ];
        if ($with_desc) {
            $data['description'] = $db->escape_string((string)$mybb->get_input('description'));
        }

        if ($data['name'] === '') {
            flash_message($lang->projektboerse_error_missing_name, 'error');
            admin_redirect($base_url);
        }

        if ($row_id > 0 && $sub_action === 'edit') {
            $db->update_query($table, $data, $pk_col . " = " . $row_id);
            flash_message($lang->{'projektboerse_' . $lang_stem . '_updated'}, 'success');
        } else {
            $db->insert_query($table, $data);
            flash_message($lang->{'projektboerse_' . $lang_stem . '_added'}, 'success');
        }
        admin_redirect($base_url);
    }

    // Liste + Add/Edit-Form auf einer Seite (Freizeit-Plugin-Pattern):
    // do=edit&pk_col=X füllt das Formular unten mit dem Datensatz vor;
    // ohne do=edit zeigt das Formular leere Felder zum Anlegen.
    $do      = $mybb->get_input('do');
    $is_edit = ($do === 'edit');
    $edit_id = $is_edit ? (int)$mybb->get_input($pk_col, MyBB::INPUT_INT) : 0;
    $edit_row = [];
    if ($is_edit && $edit_id > 0) {
        $edit_row = $db->fetch_array($db->simple_select($table, '*', $pk_col . " = " . $edit_id)) ?: [];
        if (!$edit_row) {
            flash_message($lang->projektboerse_error_not_found, 'error');
            admin_redirect($base_url);
        }
    }

    // Listenansicht.
    $page->output_header($title);
    projektboerse_acp_nav_tabs($nav_key);

    $table_obj = new Table;
    $table_obj->construct_header($lang->projektboerse_name);
    if ($with_desc) {
        $table_obj->construct_header($lang->projektboerse_desc);
    }
    $table_obj->construct_header($lang->projektboerse_order, ['class' => 'align_center', 'width' => '10%']);
    $table_obj->construct_header($lang->projektboerse_options, ['class' => 'align_center', 'width' => '20%']);

    $query = $db->simple_select($table, '*', '', ['order_by' => 'disporder']);
    while ($row = $db->fetch_array($query)) {
        $row_id = (int)$row[$pk_col];
        $table_obj->construct_cell(htmlspecialchars_uni((string)$row['name']));
        if ($with_desc) {
            $table_obj->construct_cell(htmlspecialchars_uni((string)($row['description'] ?? '')));
        }
        $table_obj->construct_cell((int)$row['disporder'], ['class' => 'align_center']);

        $popup = new PopupMenu($action . '_' . $row_id, $lang->projektboerse_options);
        $popup->add_item($lang->projektboerse_edit,
            $base_url . '&do=edit&' . $pk_col . '=' . $row_id);
        $popup->add_item($lang->projektboerse_delete,
            'javascript:document.getElementById(\'delete_' . $action . '_' . $row_id . '\').submit();',
            'return confirm(' . htmlspecialchars_uni((string)json_encode($lang->projektboerse_confirm_delete)) . ');');

        $delete_form = '<form id="delete_' . $action . '_' . $row_id . '" method="post" action="' .
            htmlspecialchars_uni($base_url) . '" style="display:none;">' .
            '<input type="hidden" name="sub_action" value="delete" />' .
            '<input type="hidden" name="' . $pk_col . '" value="' . $row_id . '" />' .
            '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />' .
            '</form>';

        $table_obj->construct_cell($popup->fetch() . $delete_form, ['class' => 'align_center']);
        $table_obj->construct_row();
    }

    if ($table_obj->num_rows() === 0) {
        $colspan = $with_desc ? 4 : 3;
        $table_obj->construct_cell($lang->projektboerse_no_categories, ['colspan' => $colspan]);
        $table_obj->construct_row();
    }

    $table_obj->output($title);

    // Add-/Edit-Formular direkt unter der Liste.
    $form_title   = $is_edit ? $lang->projektboerse_edit : $lang->projektboerse_add;
    $submit_label = $is_edit ? $lang->projektboerse_save : $lang->projektboerse_add;

    $form = new Form($base_url, 'post');
    echo $form->generate_hidden_field('sub_action', $is_edit ? 'edit' : 'add');
    if ($is_edit) {
        echo $form->generate_hidden_field($pk_col, $edit_id);
    }

    $container = new FormContainer($form_title);
    $container->output_row($lang->projektboerse_name, '',
        $form->generate_text_box('name', (string)($edit_row['name'] ?? '')), 'name');
    if ($with_desc) {
        $container->output_row($lang->projektboerse_desc, '',
            $form->generate_text_area('description', (string)($edit_row['description'] ?? '')), 'description');
    }
    $container->output_row($lang->projektboerse_order, '',
        $form->generate_numeric_field('disporder', (int)($edit_row['disporder'] ?? 0), ['min' => 0]), 'disporder');
    $container->end();

    $buttons   = [];
    $buttons[] = $form->generate_submit_button($submit_label);
    if ($is_edit) {
        $buttons[] = '<a class="button" href="' . htmlspecialchars_uni($base_url) . '">' .
            htmlspecialchars_uni($lang->projektboerse_cancel) . '</a>';
    }
    $form->output_submit_wrapper($buttons);
    $form->end();

    $page->output_footer();
}

/**
 * ACP-Startseite mit Schnellstatistik.
 */
function projektboerse_acp_home(): void
{
    global $page, $db, $lang;

    $page->output_header($lang->projektboerse . ' - ' . $lang->projektboerse_overview);
    projektboerse_acp_nav_tabs('overview');

    $count_projects = function (string $where = '') use ($db): int {
        return (int)$db->fetch_field(
            $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', $where), 'c');
    };

    $count_apps = function (string $where = '') use ($db): int {
        return (int)$db->fetch_field(
            $db->simple_select('projektboerse_applications', 'COUNT(*) AS c', $where), 'c');
    };

    $table = new Table;
    $table->construct_header($lang->projektboerse);
    $table->construct_header($lang->projektboerse_stat_total, ['class' => 'align_center', 'width' => '20%']);

    $table->construct_cell('<strong>' . $lang->projektboerse_total_projects . '</strong>');
    $table->construct_cell($count_projects(), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell($lang->projektboerse_stat_pending);
    $table->construct_cell($count_projects("status = 'pending'"), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell($lang->projektboerse_stat_active);
    $table->construct_cell($count_projects("status = 'active'"), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell($lang->projektboerse_stat_open);
    $table->construct_cell($count_projects("status = 'open'"), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell($lang->projektboerse_stat_implementation);
    $table->construct_cell($count_projects("status = 'implementation'"), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell($lang->projektboerse_stat_completed);
    $table->construct_cell($count_projects("status = 'completed'"), ['class' => 'align_center']);
    $table->construct_row();

    $table->construct_cell('<strong>' . $lang->projektboerse_total_applications . '</strong>');
    $table->construct_cell($count_apps(), ['class' => 'align_center']);
    $table->construct_row();

    $table->output($lang->projektboerse_overview);

    // Plotting-Gruppen gehören dem Plugin plottinggroups – hier nur der Verweis,
    // damit niemand die Verwaltung fälschlich in diesem Modul sucht.
    if (class_exists('ProjektboerseGroups') && ProjektboerseGroups::available()) {
        $groups_note = $lang->projektboerse_groups_managed_elsewhere .
            ' <a href="index.php?module=tools-plottinggroups">' .
            htmlspecialchars_uni($lang->projektboerse_groups) . '</a>';
    } else {
        $groups_note = $lang->projektboerse_groups_unavailable;
    }

    $groups_table = new Table;
    $groups_table->construct_cell($groups_note);
    $groups_table->construct_row();
    $groups_table->output($lang->projektboerse_groups);

    // Wartung: Ereignis-Historie nachtragen. Nur sinnvoll, wenn plottinggroups
    // (inkl. Events-Feature) aktiv ist – sonst nur ein erklärender Hinweis.
    $backfill_available = class_exists('ProjektboerseGroups')
        && ProjektboerseGroups::available()
        && method_exists('Plottinggroups_Api', 'logEvent');

    $maint_table = new Table;
    $maint_table->construct_cell($lang->projektboerse_backfill_events_desc);

    if ($backfill_available) {
        $backfill_form = new Form('index.php?module=config-projektboerse&action=backfill_events', 'post');
        $button = $backfill_form->generate_submit_button($lang->projektboerse_backfill_events_button,
            ['onclick' => 'return confirm(' . htmlspecialchars_uni((string)json_encode($lang->projektboerse_backfill_confirm)) . ');']);
        $maint_table->construct_cell($button, ['class' => 'align_center', 'width' => '25%']);
    } else {
        $maint_table->construct_cell($lang->projektboerse_backfill_unavailable, ['class' => 'align_center', 'width' => '25%']);
    }
    $maint_table->construct_row();
    $maint_table->output($lang->projektboerse_maintenance);

    if ($backfill_available) {
        // Das Formular-Markup (verstecktes my_post_key-Feld) muss zwischen
        // <form>…</form> stehen; end() gibt genau das aus.
        $backfill_form->end();
    }

    $page->output_footer();
}

/**
 * Kategorien-Verwaltung.
 */
function projektboerse_acp_categories(): void
{
    global $lang;
    projektboerse_acp_crud('projektboerse_categories', 'category_id', 'categories',
        $lang->projektboerse_categories, 'categories', true, 'category');
}

/**
 * Typen-Verwaltung.
 */
function projektboerse_acp_types(): void
{
    global $lang;
    projektboerse_acp_crud('projektboerse_types', 'type_id', 'types',
        $lang->projektboerse_types, 'types', false, 'type');
}

/**
 * Kommunikationswege-Verwaltung.
 */
function projektboerse_acp_communication(): void
{
    global $lang;
    projektboerse_acp_crud('projektboerse_communication', 'comm_id', 'communication',
        $lang->projektboerse_communication, 'communication', false, 'communication');
}

/**
 * Alert-Einstellungen + Log-Anzeige.
 */
function projektboerse_acp_alerts(): void
{
    global $page, $db, $mybb, $lang;

    if ($mybb->request_method === 'post') {
        projektboerse_acp_verify_key();

        $alert_settings = [
            'projektboerse_alert_new_project',
            'projektboerse_alert_approved',
            'projektboerse_alert_published',
            'projektboerse_alert_open',
            'projektboerse_alert_rejected',
            'projektboerse_alert_new_application',
            'projektboerse_alert_application_accepted',
            'projektboerse_alert_application_rejected',
            'projektboerse_alert_comment',
            'projektboerse_alert_deadline',
            'projektboerse_alert_archived',
            'projektboerse_alert_completed',
            'projektboerse_alert_implementer_assigned',
        ];

        foreach ($alert_settings as $name) {
            $value = (int)$mybb->get_input($name, MyBB::INPUT_INT);
            $db->update_query('settings', ['value' => (string)$value],
                "name = '" . $db->escape_string($name) . "'");
        }

        $recipients = (string)$mybb->get_input('projektboerse_alert_new_project_recipients');
        $db->update_query('settings',
            ['value' => $db->escape_string($recipients)],
            "name = 'projektboerse_alert_new_project_recipients'");

        rebuild_settings();
        flash_message($lang->projektboerse_alerts_updated, 'success');
        admin_redirect("index.php?module=config-projektboerse&action=alerts");
    }

    $page->output_header($lang->projektboerse_alerts);
    projektboerse_acp_nav_tabs('alerts');

    $form = new Form("index.php?module=config-projektboerse&action=alerts", "post");

    $container = new FormContainer($lang->projektboerse_alert_settings);
    $events = [
        'projektboerse_alert_new_project'           => 'Neues Projekt eingereicht',
        'projektboerse_alert_approved'              => 'Projekt freigegeben',
        'projektboerse_alert_published'             => 'Projekt in der Projektbörse – an alle User',
        'projektboerse_alert_open'                  => 'Bewerbungsphase gestartet – an alle User',
        'projektboerse_alert_rejected'              => 'Projekt abgelehnt',
        'projektboerse_alert_new_application'       => 'Neue Bewerbung',
        'projektboerse_alert_application_accepted'  => 'Bewerbung angenommen',
        'projektboerse_alert_application_rejected'  => 'Bewerbung abgelehnt',
        'projektboerse_alert_comment'               => 'Neuer Kommentar',
        'projektboerse_alert_deadline'              => 'Deadline überschritten',
        'projektboerse_alert_archived'              => 'Projekt archiviert',
        'projektboerse_alert_completed'             => 'Projekt abgeschlossen',
        'projektboerse_alert_implementer_assigned'  => 'Umsetzer zugewiesen',
    ];

    foreach ($events as $setting => $label) {
        $container->output_row($label, '',
            $form->generate_yes_no_radio($setting, (int)($mybb->settings[$setting] ?? 1)));
    }
    $container->end();

    $container = new FormContainer($lang->projektboerse_alert_recipients);
    $container->output_row('User-IDs (kommagetrennt)', 'Zusätzliche Empfänger bei neuen Projekten',
        $form->generate_text_box('projektboerse_alert_new_project_recipients',
            (string)($mybb->settings['projektboerse_alert_new_project_recipients'] ?? ''),
            ['style' => 'width: 100%;']));
    $container->end();

    $form->output_submit_wrapper([
        $form->generate_submit_button($lang->projektboerse_save),
    ]);
    $form->end();

    // Alert-Log.
    $log_table = new Table;
    $log_table->construct_header($lang->projektboerse_alert_timestamp);
    $log_table->construct_header($lang->projektboerse_alert_event);
    $log_table->construct_header($lang->projektboerse_alert_recipient);
    $log_table->construct_header($lang->projektboerse_project);

    $query = $db->simple_select('projektboerse_alertlog', '*', '',
        ['order_by' => 'sent_at', 'order_dir' => 'DESC', 'limit' => 50]);

    while ($log = $db->fetch_array($query)) {
        $user     = get_user((int)$log['recipient_uid']);
        $username = $user ? htmlspecialchars_uni((string)$user['username']) : '#' . (int)$log['recipient_uid'];

        $project_link = (int)$log['pid'] > 0
            ? '<a href="../projektboerse.php?do=view&amp;pid=' . (int)$log['pid'] . '" target="_blank">' .
              htmlspecialchars_uni('Projekt #' . (int)$log['pid']) . '</a>'
            : '-';

        $log_table->construct_cell(my_date('normal', strtotime((string)$log['sent_at'])));
        $log_table->construct_cell(htmlspecialchars_uni((string)$log['event_type']));
        $log_table->construct_cell($username);
        $log_table->construct_cell($project_link);
        $log_table->construct_row();
    }

    if ($log_table->num_rows() === 0) {
        $log_table->construct_cell($lang->projektboerse_no_alert_logs, ['colspan' => 4]);
        $log_table->construct_row();
    }

    $log_table->output($lang->projektboerse_alert_log);

    $page->output_footer();
}

/**
 * Erweiterte Statistiken.
 */
function projektboerse_acp_statistics(): void
{
    global $page, $db, $lang;

    $page->output_header($lang->projektboerse_statistics);
    projektboerse_acp_nav_tabs('statistics');

    $count = function (string $where = '') use ($db): int {
        return (int)$db->fetch_field(
            $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', $where), 'c');
    };

    // Globale Stats.
    $table = new Table;
    $table->construct_header('Status');
    $table->construct_header('Anzahl', ['class' => 'align_center', 'width' => '20%']);

    $rows = [
        [$lang->projektboerse_total_projects,                  $count()],
        [$lang->projektboerse_stat_pending,                    $count("status = 'pending'")],
        [$lang->projektboerse_stat_active,                     $count("status = 'active'")],
        [$lang->projektboerse_stat_open,                       $count("status = 'open'")],
        [$lang->projektboerse_stat_implementation,             $count("status = 'implementation'")],
        [$lang->projektboerse_stat_completed,                  $count("status = 'completed'")],
        [$lang->projektboerse_stat_rejected,                   $count("status = 'rejected'")],
        [$lang->projektboerse_stat_archived,                   $count("status = 'archived'")],
    ];

    foreach ($rows as $row) {
        $table->construct_cell((string)$row[0]);
        $table->construct_cell((string)(int)$row[1], ['class' => 'align_center']);
        $table->construct_row();
    }
    $table->output($lang->projektboerse_global_stats);

    // Top-Einreicher.
    $submitter_table = new Table;
    $submitter_table->construct_header('User');
    $submitter_table->construct_header('Projekte', ['class' => 'align_center', 'width' => '20%']);

    $query = $db->query("
        SELECT u.uid, u.username, COUNT(*) AS project_count
        FROM " . TABLE_PREFIX . "projektboerse_projects p
        LEFT JOIN " . TABLE_PREFIX . "users u ON p.uid = u.uid
        GROUP BY p.uid
        ORDER BY project_count DESC
        LIMIT 10");

    while ($user = $db->fetch_array($query)) {
        $submitter_table->construct_cell(
            $user['username']
                ? '<a href="../member.php?action=profile&amp;uid=' . (int)$user['uid'] . '" target="_blank">' .
                  htmlspecialchars_uni((string)$user['username']) . '</a>'
                : '#' . (int)$user['uid']);
        $submitter_table->construct_cell((string)(int)$user['project_count'], ['class' => 'align_center']);
        $submitter_table->construct_row();
    }
    if ($submitter_table->num_rows() === 0) {
        $submitter_table->construct_cell($lang->projektboerse_no_data, ['colspan' => 2]);
        $submitter_table->construct_row();
    }
    $submitter_table->output('Top 10 Einreicher');

    // Top-Umsetzer.
    $impl_table = new Table;
    $impl_table->construct_header('User');
    $impl_table->construct_header('Umsetzungen', ['class' => 'align_center', 'width' => '20%']);

    $query = $db->query("
        SELECT u.uid, u.username, COUNT(*) AS impl_count
        FROM " . TABLE_PREFIX . "projektboerse_implementers i
        LEFT JOIN " . TABLE_PREFIX . "users u ON i.uid = u.uid
        GROUP BY i.uid
        ORDER BY impl_count DESC
        LIMIT 10");

    while ($user = $db->fetch_array($query)) {
        $impl_table->construct_cell(
            $user['username']
                ? '<a href="../member.php?action=profile&amp;uid=' . (int)$user['uid'] . '" target="_blank">' .
                  htmlspecialchars_uni((string)$user['username']) . '</a>'
                : '#' . (int)$user['uid']);
        $impl_table->construct_cell((string)(int)$user['impl_count'], ['class' => 'align_center']);
        $impl_table->construct_row();
    }
    if ($impl_table->num_rows() === 0) {
        $impl_table->construct_cell($lang->projektboerse_no_data, ['colspan' => 2]);
        $impl_table->construct_row();
    }
    $impl_table->output('Top 10 Umsetzer');

    $page->output_footer();
}

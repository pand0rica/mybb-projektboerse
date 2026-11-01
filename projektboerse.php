<?php
/**
 * Projektboerse – Frontend-Einstiegsdatei.
 *
 * Liegt im MyBB-Root und routet alle do=...-Aktionen an die entsprechenden
 * Handler-Funktionen aus inc/plugins/projektboerse/hooks.php.
 *
 * @package projektboerse
 */

define('IN_MYBB', 1);
define('THIS_SCRIPT', 'projektboerse.php');

require_once './global.php';

if (!function_exists('projektboerse_is_installed') || !projektboerse_is_installed()) {
    error("Das Projektbörse-Plugin ist nicht installiert oder nicht aktiviert.");
}

$lang->load("projektboerse");

if ((int)($mybb->settings['projektboerse_enabled'] ?? 1) !== 1) {
    error($lang->projektboerse_disabled);
}

// Master-Allowlist: User außerhalb der berechtigten Gruppen dürfen die
// Projektbörse gar nicht erst sehen. Gast-Accounts (uid=0) bleiben außen
// vor, sofern die Allowlist gesetzt ist; bei leerer Allowlist ist alles
// erlaubt (Backward-Compat).
if (!Projektboerse::is_eligible((int)$mybb->user['uid'])) {
    error_no_permission();
}

$do = $mybb->get_input('do');

switch ($do) {
    case 'view':
        projektboerse_view_project();
        break;

    case 'create':
        projektboerse_create_project();
        break;

    case 'submit':
        projektboerse_submit_project();
        break;

    case 'add_comment':
        projektboerse_add_comment();
        break;

    case 'submit_application':
        projektboerse_submit_application();
        break;

    case 'edit':
        projektboerse_edit_project();
        break;

    case 'update':
        projektboerse_update_project();
        break;

    case 'active':
        projektboerse_list_projects('active');
        break;

    case 'open':
        projektboerse_list_projects('open');
        break;

    case 'completed':
        projektboerse_list_projects('completed');
        break;

    case 'my_applications':
        projektboerse_my_applications();
        break;

    case 'stats':
        projektboerse_stats();
        break;

    case 'guide':
        projektboerse_guide();
        break;

    default:
        projektboerse_index();
        break;
}

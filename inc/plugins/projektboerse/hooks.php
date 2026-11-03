<?php
/**
 * Projektboerse – Hook-Handler, ModCP-Routing und Frontend-Render-Funktionen.
 *
 * Wird von der Hauptdatei eingebunden, sobald das Plugin installiert ist.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

// ============================================================================
// Helper
// ============================================================================

/**
 * Lädt einen User-Datensatz.
 *
 * @param int $uid Ziel-User.
 * @return array|null
 */
function projektboerse_get_user(int $uid): ?array
{
    global $db;

    if ($uid <= 0) {
        return null;
    }

    $query = $db->simple_select('users', '*', "uid = " . $uid);
    $row   = $db->fetch_array($query);
    return $row ?: null;
}

/**
 * Liefert eine Liste der Teammitglieder als [uid => username].
 *
 * Wird für das Kontaktperson-Dropdown genutzt – daher zusätzlich gegen die
 * Master-Allowlist (Projektboerse::is_eligible) gefiltert, damit nur User
 * mit tatsächlicher Freigabe-Berechtigung gelistet werden (z.B. keine
 * Charakter-Accounts, die versehentlich in einer Team-Gruppe stehen).
 *
 * @return array<int,string>
 */
function projektboerse_get_team_members(): array
{
    global $db, $mybb;

    $team_groups = array_filter(array_map('intval',
        explode(',', (string)($mybb->settings['projektboerse_team_groups'] ?? ''))));

    if (empty($team_groups)) {
        return [];
    }

    $where_parts = [];
    foreach ($team_groups as $gid) {
        $gid = (int)$gid;
        $where_parts[] = "usergroup = " . $gid;
        $where_parts[] = "CONCAT(',', additionalgroups, ',') LIKE '%," . $gid . ",%'";
    }
    $where = '(' . implode(' OR ', $where_parts) . ')';

    $query = $db->simple_select('users', 'uid, username', $where,
        ['order_by' => 'username', 'order_dir' => 'ASC']);

    $members = [];
    while ($user = $db->fetch_array($query)) {
        $uid = (int)$user['uid'];
        if (!Projektboerse::is_team_member($uid)) {
            continue;
        }
        $members[$uid] = $user['username'];
    }
    return $members;
}

/**
 * Liefert das lokalisierte Label zu einem Bewerbungs-Status.
 */
function projektboerse_application_status_label(string $status): string
{
    global $lang;
    $key = 'projektboerse_app_status_' . $status;
    return isset($lang->{$key}) && $lang->{$key} !== '' ? $lang->{$key} : $status;
}

/**
 * Rendert Plotting-Gruppen als verlinkte Pills.
 *
 * Form und Beschriftung (#Name) entsprechen den Pills, die plottinggroups
 * selbst in Beiträgen rendert, damit eine Gruppe überall gleich aussieht.
 *
 * Bekannte Kopplung: das Fragment ?gid=X#plottinggroups_members ist hier fest
 * verdrahtet, weil Plottinggroups_Api nur groupsUrl() anbietet und keinen
 * URL-Builder pro Gruppe. Bricht das, gehört ein groupUrl(int $gid) in die API.
 *
 * @param array $names gid => Gruppenname
 */
function projektboerse_render_group_pills(array $names): string
{
    $url = ProjektboerseGroups::groupsUrl();
    $out = '';

    foreach ($names as $gid => $name) {
        $label = '#' . htmlspecialchars_uni((string)$name);

        if ($url !== '') {
            $out .= '<a class="projekt_group" href="' . htmlspecialchars_uni($url) .
                '?gid=' . (int)$gid . '#plottinggroups_members" title="' .
                htmlspecialchars_uni((string)$name) . '">' . $label . '</a>';
        } else {
            // plottinggroups deaktiviert, Zuordnungen aber noch vorhanden.
            $out .= '<span class="projekt_group">' . $label . '</span>';
        }
    }

    return $out;
}

/**
 * Rendert ein farbiges Status-Badge für eine Bewerbung.
 */
function projektboerse_render_application_status_badge(string $status): string
{
    $label = htmlspecialchars_uni(projektboerse_application_status_label($status));
    return '<span class="bewerbung_status bewerbung_status_' . htmlspecialchars_uni($status) . '">' .
        $label . '</span>';
}

/**
 * Rendert die Aktions-Buttons (Annehmen/Ablehnen) für eine ModCP-Bewerbung.
 * Buttons erscheinen nur für offene ("submitted") Bewerbungen; bereits
 * bearbeitete Bewerbungen zeigen stattdessen das Review-Datum.
 */
function projektboerse_render_modcp_application_actions(array $application): string
{
    global $mybb, $lang;

    if ($application['status'] !== 'submitted') {
        if (!empty($application['reviewed_at']) && $application['reviewed_at'] !== '0000-00-00 00:00:00') {
            return '<span class="smalltext">' .
                $lang->projektboerse_reviewed_on . ' ' .
                my_date('normal', strtotime((string)$application['reviewed_at'])) . '</span>';
        }
        return '';
    }

    $aid = (int)$application['aid'];

    // Echte Buttons in kleinen POST-Formularen (CSRF-sicher, debugging.md #22).
    $accept = projektboerse_modcp_application_action_link('accept_application', $aid, $lang->projektboerse_accept);
    $reject = projektboerse_modcp_application_action_link('reject_application', $aid, $lang->projektboerse_reject);

    return $accept . ' ' . $reject;
}

/**
 * Rendert eine einzelne Bewerbungs-Aktion als Button in einem kleinen
 * POST-Formular (analog zu projektboerse_modcp_action_button, aber auf aid
 * statt pid).
 */
function projektboerse_modcp_application_action_link(string $do, int $aid, string $label): string
{
    global $mybb;

    return '<form method="post" action="modcp.php?action=projektboerse" class="projektboerse_inline_form">' .
        '<input type="hidden" name="do" value="' . htmlspecialchars_uni($do) . '" />' .
        '<input type="hidden" name="aid" value="' . (int)$aid . '" />' .
        '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />' .
        '<input type="submit" class="button" value="' . htmlspecialchars_uni($label) . '" />' .
        '</form>';
}

/**
 * Parser-Helper für Plaintext-Benutzereingaben (Bewerbung, Kommentar).
 *
 * @param string $text Roher Text aus der DB (noch nicht escaped).
 */
function projektboerse_parse_message(string $text): string
{
    global $mybb;

    require_once MYBB_ROOT . "inc/class_parser.php";
    static $parser = null;
    if ($parser === null) {
        $parser = new postParser;
    }

    $allow_html = projektboerse_html_allowed();
    if ($allow_html) {
        // HTML zuerst gegen die Allowlist filtern, BEVOR der MyBB-Parser
        // mit allow_html=1 die spitzen Klammern unangetastet lässt.
        $text = projektboerse_sanitize_html($text);
    }

    $options = [
        'allow_html'      => $allow_html ? 1 : 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 1,
        'allow_imgcode'   => 1,
        'allow_videocode' => 1,
        'filter_badwords' => 1,
    ];

    return $parser->parse_message($text, $options);
}

/**
 * Liest die Setting projektboerse_allow_html als bool.
 */
function projektboerse_html_allowed(): bool
{
    global $mybb;
    return (int)($mybb->settings['projektboerse_allow_html'] ?? 0) === 1;
}

/**
 * Formatiert einen Freitext-Block (Beschreibung, Belohnung, Wiki-Links).
 *
 * - Setting AUS: vollständiger HTML-Escape + nl2br (Plain Text).
 * - Setting AN : Sanitizer-Allowlist mit DOMDocument; Skript-Tags,
 *                Event-Handler und gefährliche URL-Schemata werden entfernt.
 */
function projektboerse_format_html(string $input): string
{
    $input = (string)$input;
    if ($input === '') {
        return '';
    }
    if (!projektboerse_html_allowed()) {
        return nl2br(htmlspecialchars_uni($input));
    }
    return projektboerse_sanitize_html($input);
}

/**
 * Formatiert das Links-Feld über den MyBB-Parser.
 *
 * Erlaubt BBCode (v.a. [url]/[b]/[i]), aber kein rohes HTML: HTML wird vom
 * Parser escaped (allow_html = 0), sodass ausschliesslich BBCode interpretiert
 * wird. So können Team-Mitglieder Links bequem per [url=...]Titel[/url] setzen.
 */
function projektboerse_format_links(string $input): string
{
    $input = trim((string)$input);
    if ($input === '') {
        return '';
    }

    require_once MYBB_ROOT . 'inc/class_parser.php';
    static $parser = null;
    if ($parser === null) {
        $parser = new postParser();
    }

    return $parser->parse_message($input, [
        'allow_html'      => 0,
        'allow_mycode'    => 1,
        'allow_smilies'   => 0,
        'allow_imgcode'   => 1,
        'allow_videocode' => 0,
        'filter_badwords' => 1,
        'nl2br'           => 1,
    ]);
}

/**
 * HTML-Sanitizer per Allowlist. Nutzt DOMDocument, kein Regex.
 *
 * Erlaubte Tags + Attribute werden zentral hier definiert. Alles andere
 * (Skript-Tags, Inline-Event-Handler, Style-Attribute, javascript:/data:/
 * vbscript:-URLs, unbekannte Tags) wird stillschweigend entfernt – Tag-
 * Inhalte (Text) bleiben dabei erhalten.
 */
function projektboerse_sanitize_html(string $html): string
{
    $html = trim($html);
    if ($html === '') {
        return '';
    }

    $allowed_tags = [
        'p', 'br', 'b', 'strong', 'i', 'em', 'u', 's', 'span', 'div',
        'ul', 'ol', 'li', 'blockquote',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
        'code', 'pre',
        'a', 'img',
        'hr',
    ];

    $allowed_attrs = [
        'a'   => ['href', 'title', 'target', 'rel'],
        'img' => ['src', 'alt', 'title', 'width', 'height'],
    ];

    $dom = new DOMDocument('1.0', 'UTF-8');
    $previous = libxml_use_internal_errors(true);

    // Wrapper sorgt für eine eindeutige Wurzel + erzwingt UTF-8.
    $wrapped = '<?xml encoding="UTF-8"?><div id="projektboerse_sanitize_root">' . $html . '</div>';
    $loaded  = $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();
    libxml_use_internal_errors($previous);

    if (!$loaded) {
        // Im Zweifel komplett escapen – nie unverarbeitetes HTML rausgeben.
        return nl2br(htmlspecialchars_uni($html));
    }

    $root = $dom->getElementById('projektboerse_sanitize_root');
    if ($root === null) {
        return '';
    }

    projektboerse_sanitize_dom_node($root, $allowed_tags, $allowed_attrs);

    // Innerhalb des Wrappers serialisieren (Wrapper selbst nicht ausgeben).
    $out = '';
    foreach ($root->childNodes as $child) {
        $out .= $dom->saveHTML($child);
    }
    return $out;
}

/**
 * Rekursiver Walker für projektboerse_sanitize_html().
 * Nicht-erlaubte Element-Knoten werden durch ihren Text-Inhalt ersetzt
 * (so geht kein Lesefluss verloren). Nicht erlaubte Attribute werden
 * entfernt, gefährliche URL-Schemata in href/src ebenfalls.
 */
function projektboerse_sanitize_dom_node(\DOMNode $node, array $allowed_tags, array $allowed_attrs): void
{
    if (!$node->hasChildNodes()) {
        return;
    }

    // Kinder zuerst in ein Array, weil wir während der Iteration ggf. löschen.
    $children = iterator_to_array($node->childNodes);

    foreach ($children as $child) {
        if (!($child instanceof DOMElement)) {
            // Text-, CDATA-, Kommentar-Knoten: Kommentare raus, Rest behalten.
            if ($child instanceof DOMComment) {
                $node->removeChild($child);
            }
            continue;
        }

        $tag = strtolower($child->tagName);

        // Unerlaubtes Tag → durch Text-Inhalt ersetzen (Text bleibt erhalten).
        if (!in_array($tag, $allowed_tags, true)) {
            $text = $child->ownerDocument->createTextNode($child->textContent);
            $node->replaceChild($text, $child);
            continue;
        }

        // Attribute prüfen: alles raus, was nicht in der Whitelist steht.
        $allowed_for_tag = $allowed_attrs[$tag] ?? [];
        $to_remove       = [];
        foreach ($child->attributes as $attr) {
            $aname = strtolower($attr->name);

            if (!in_array($aname, $allowed_for_tag, true)) {
                $to_remove[] = $attr->name;
                continue;
            }

            // URL-Felder gegen gefährliche Schemata absichern.
            if ($aname === 'href' || $aname === 'src') {
                $value = trim((string)$attr->value);
                if (preg_match('#^(?:javascript|data|vbscript|file)\s*:#i', $value)) {
                    $to_remove[] = $attr->name;
                    continue;
                }
            }
        }
        foreach ($to_remove as $aname) {
            $child->removeAttribute($aname);
        }

        // Externe Links: rel="noopener noreferrer" + target absichern.
        if ($tag === 'a' && $child->hasAttribute('target')) {
            $child->setAttribute('rel', 'noopener noreferrer');
        }

        projektboerse_sanitize_dom_node($child, $allowed_tags, $allowed_attrs);
    }
}

// ============================================================================
// Globale Hooks
// ============================================================================

/**
 * Globaler Hook: lädt die Sprachdateien und registriert den MyAlerts-Formatter.
 * projektboerse_myalerts wird benötigt, damit MyAlerts beim Rendern des
 * Bell-Dropdowns die $lang->myalerts_projektboerse_* Strings findet.
 *
 * MyAlerts 2.x hat keinen Formatter-Lade-Hook, daher rufen wir den
 * Registrar hier auf – static guard innen verhindert Mehrfachregistrierung.
 */
function projektboerse_handle_global(): void
{
    global $lang;
    $lang->load("projektboerse");
    $lang->load("projektboerse_myalerts");

    projektboerse_register_alert_formatter();
}

/**
 * Index-Hook: Red-Alert für Teammitglieder (wartende Projekte/Bewerbungen).
 */
function projektboerse_handle_index(): void
{
    global $mybb, $db, $lang, $header;

    if ((int)($mybb->settings['projektboerse_enabled'] ?? 1) !== 1) {
        return;
    }

    if (!Projektboerse::is_team_member()) {
        return;
    }

    $pending_count     = (int)$db->fetch_field(
        $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', "status = 'pending'"), 'c');
    $applications_count = ProjektboerseApplication::count_pending();

    $alerts_html = '';

    if ($pending_count > 0) {
        $projects_text = $lang->sprintf(
            $pending_count === 1
                ? $lang->projektboerse_redalert_projects_one
                : $lang->projektboerse_redalert_projects,
            (int)$pending_count
        );
        $alerts_html .= '<div class="projektboerse_redalert"><strong>' .
            htmlspecialchars_uni($lang->projektboerse) . ':</strong> ' .
            '<a href="modcp.php?action=projektboerse">' . htmlspecialchars_uni($projects_text) . '</a></div>';
    }

    if ($applications_count > 0) {
        $applications_text = $lang->sprintf(
            $applications_count === 1
                ? $lang->projektboerse_redalert_applications_one
                : $lang->projektboerse_redalert_applications,
            (int)$applications_count
        );
        $alerts_html .= '<div class="projektboerse_redalert projektboerse_redalert_warning"><strong>' .
            htmlspecialchars_uni($lang->projektboerse) . ':</strong> ' .
            '<a href="modcp.php?action=projektboerse">' . htmlspecialchars_uni($applications_text) . '</a></div>';
    }

    if ($alerts_html !== '') {
        $header .= $alerts_html;
    }
}

// ============================================================================
// ModCP-Integration
// ============================================================================

/**
 * ModCP-Hook: Navigation erweitern und ggf. Aktionen dispatchen.
 */
function projektboerse_handle_modcp(): void
{
    global $mybb, $db, $lang, $modcp_nav;

    if (!Projektboerse::is_team_member()) {
        return;
    }

    $pending_count = (int)$db->fetch_field(
        $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', "status = 'pending'"), 'c');

    $pending_display = $pending_count > 0
        ? ' <span class="projektboerse_modcp_count">(' . (int)$pending_count . ')</span>'
        : '';

    $nav_item = '<tr><td class="trow1 smalltext">' .
        '<a href="modcp.php?action=projektboerse" class="modcp_nav_item modcp_nav_projektboerse">' .
        htmlspecialchars_uni($lang->projektboerse_modcp) . $pending_display .
        '</a></td></tr>';

    $pos = strpos((string)$modcp_nav, '</tbody>');
    if ($pos !== false) {
        $modcp_nav = substr_replace($modcp_nav, $nav_item . '</tbody>', $pos, 8);
    } else {
        $modcp_nav .= $nav_item;
    }

    if ($mybb->get_input('action') === 'projektboerse') {
        projektboerse_modcp_handler();
    }
}

/**
 * Dispatcher für alle ModCP-Aktionen.
 */
function projektboerse_modcp_handler(): void
{
    global $mybb, $lang;

    if (!Projektboerse::is_team_member()) {
        error_no_permission();
    }

    $do = $mybb->get_input('do');

    switch ($do) {
        case 'approve':
            projektboerse_modcp_approve_form();
            break;

        case 'submit_approve':
            projektboerse_modcp_submit_approve();
            break;

        case 'reject_form':
            projektboerse_modcp_reject_form();
            break;

        case 'reject':
            projektboerse_modcp_reject();
            break;

        case 'delete_form':
            projektboerse_modcp_delete_form();
            break;

        case 'accept_application':
            projektboerse_modcp_accept_application();
            break;

        case 'reject_application':
            projektboerse_modcp_reject_application();
            break;

        case 'complete':
            projektboerse_modcp_complete();
            break;

        case 'archive':
            projektboerse_modcp_archive();
            break;

        case 'reopen':
            projektboerse_modcp_reopen();
            break;

        case 'open_applications':
            projektboerse_modcp_open_applications();
            break;

        case 'delete':
            projektboerse_modcp_delete();
            break;

        case 'edit':
            projektboerse_modcp_edit();
            break;

        case 'update':
            projektboerse_modcp_update();
            break;

        case 'view':
            $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
            redirect("projektboerse.php?do=view&pid=" . $pid);
            break;

        case 'applications':
            projektboerse_modcp_applications();
            break;

        default:
            projektboerse_modcp_index();
            break;
    }
}

/**
 * ModCP-Index: zeigt wartende Projekte, wartende Bewerbungen und alle Projekte.
 */
function projektboerse_modcp_index(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang, $modcp_nav;

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");

    // Wartende Projekte.
    $pending_projects = projektboerse_render_modcp_project_list("status = 'pending'",
        $lang->projektboerse_no_pending_projects);

    // Wartende Bewerbungen.
    $pending_applications = '';
    $query = $db->query("
        SELECT a.*, p.title AS project_title
        FROM " . TABLE_PREFIX . "projektboerse_applications a
        LEFT JOIN " . TABLE_PREFIX . "projektboerse_projects p ON a.pid = p.pid
        WHERE a.status = 'submitted'
        ORDER BY a.submitted_at DESC
    ");

    while ($application = $db->fetch_array($query)) {
        $applicant = projektboerse_get_user((int)$application['uid']);
        $applicant_formatted = $applicant
            ? build_profile_link(format_name($applicant['username'], $applicant['usergroup'], $applicant['displaygroup']), (int)$applicant['uid'])
            : '#' . (int)$application['uid'];

        $submitted_date         = my_date('relative', strtotime($application['submitted_at']));
        $project_title          = htmlspecialchars_uni((string)$application['project_title']);
        $application_text_parsed = projektboerse_parse_message((string)$application['application_text']);

        $application_status_badge = projektboerse_render_application_status_badge((string)$application['status']);
        $application_actions      = projektboerse_render_modcp_application_actions($application);

        eval('$pending_applications .= "' . $templates->get('projektboerse_modcp_application') . '";');
    }

    if ($pending_applications === '') {
        $pending_applications = '<div class="trow1 projektboerse_empty">' .
            $lang->projektboerse_no_pending_applications . '</div>';
    }

    // Alle Projekte (jüngste 20).
    $all_projects = projektboerse_render_modcp_project_list('', $lang->projektboerse_no_projects, 20);

    eval('$page = "' . $templates->get('projektboerse_modcp') . '";');
    output_page($page);
}

/**
 * Rendert eine Navigations-Aktion (Ansehen, Bearbeiten, Freigeben-Formular …) als
 * GET-Formular mit echtem Button – nie als Link (conventions.md §9, debugging.md #22).
 *
 * @param string               $script Zielskript, z.B. 'modcp.php'.
 * @param array<string,string|int> $params Query-Parameter als versteckte Felder.
 * @param string               $label  Beschriftung (bereits lokalisiert).
 */
function projektboerse_get_button(string $script, array $params, string $label): string
{
    $html = '<form method="get" action="' . htmlspecialchars_uni($script) . '" class="projektboerse_inline_form">';
    foreach ($params as $name => $value) {
        $html .= '<input type="hidden" name="' . htmlspecialchars_uni((string)$name) . '" value="'
            . htmlspecialchars_uni((string)$value) . '" />';
    }
    return $html . '<input type="submit" class="button" value="' . htmlspecialchars_uni($label) . '" /></form>';
}

/**
 * Rendert einen datenverändernden ModCP-Aktions-Button als kleines POST-Formular
 * inkl. my_post_key. Datenverändernde Aktionen (complete/archive/reopen) dürfen
 * nicht über GET-Links laufen (CSRF, debugging.md #3) – daher ein eigenes Mini-
 * Formular statt eines <a>-Links.
 *
 * @param string $do      Dispatcher-Action (z.B. 'complete').
 * @param int    $pid     Projekt-ID.
 * @param string $label   Button-Beschriftung (bereits lokalisiert).
 * @param string $confirm Optionaler JS-Bestätigungstext (leer = keine Rückfrage).
 * @param bool   $as_link Veraltet und ohne Wirkung – Aktionen sind immer
 *                        `<input type="submit" class="button">` (debugging.md #22).
 */
function projektboerse_modcp_action_button(string $do, int $pid, string $label, string $confirm = '', bool $as_link = false): string
{
    global $mybb;

    $form_fields =
        '<input type="hidden" name="do" value="' . htmlspecialchars_uni($do) . '" />' .
        '<input type="hidden" name="pid" value="' . (int)$pid . '" />' .
        '<input type="hidden" name="my_post_key" value="' . $mybb->post_code . '" />';

    // $as_link wird nicht mehr ausgewertet: Aktionen sind immer echte Buttons
    // (debugging.md #22). Parameter bleibt für bestehende Aufrufe erhalten.
    unset($as_link);

    $onsubmit = $confirm !== ''
        ? ' onsubmit="return confirm(' . htmlspecialchars_uni((string)json_encode($confirm)) . ');"'
        : '';

    return '<form method="post" action="modcp.php?action=projektboerse" class="projektboerse_inline_form"' . $onsubmit . '>' .
        $form_fields .
        '<input type="submit" class="button" value="' . htmlspecialchars_uni($label) . '" />' .
        '</form>';
}

/**
 * Hilfsfunktion: rendert eine Liste von ModCP-Projektkacheln.
 */
function projektboerse_render_modcp_project_list(string $where, string $empty_text, int $limit = 0): string
{
    global $mybb, $db, $templates, $lang;

    $options = ['order_by' => 'created_at', 'order_dir' => 'DESC'];
    if ($limit > 0) {
        $options['limit'] = $limit;
    }
    $query = $db->simple_select('projektboerse_projects', '*', $where, $options);

    $out = '';
    while ($project = $db->fetch_array($query)) {
        $owner = projektboerse_get_user((int)$project['uid']);
        $owner_formatted = $owner
            ? build_profile_link(format_name($owner['username'], $owner['usergroup'], $owner['displaygroup']), (int)$owner['uid'])
            : '#' . (int)$project['uid'];

        $status_name  = Projektboerse::get_status_name((string)$project['status']);
        $status_class = Projektboerse::get_status_class((string)$project['status']);
        $created_date = my_date('relative', strtotime($project['created_at']));
        $project['title'] = htmlspecialchars_uni((string)$project['title']);

        $pid_link = (int)$project['pid'];
        $actions  = [];

        $actions[] = projektboerse_get_button('projektboerse.php', ['do' => 'view', 'pid' => $pid_link], $lang->projektboerse_view);

        if ($project['status'] === 'pending') {
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'approve', 'pid' => $pid_link], $lang->projektboerse_approve);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'reject_form', 'pid' => $pid_link], $lang->projektboerse_reject);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'edit', 'pid' => $pid_link], $lang->projektboerse_edit);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'delete_form', 'pid' => $pid_link], $lang->projektboerse_delete);
        } elseif ($project['status'] === 'active') {
            // Feedback-Phase: Team kann die Phase manuell beenden und das Projekt
            // für Bewerbungen öffnen (sonst passiert das erst per Deadline-Task).
            $actions[] = projektboerse_modcp_action_button('open_applications', $pid_link,
                $lang->projektboerse_open_applications, $lang->projektboerse_confirm_open_applications, true);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'edit', 'pid' => $pid_link], $lang->projektboerse_edit);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'delete_form', 'pid' => $pid_link], $lang->projektboerse_delete);
        } elseif ($project['status'] === 'open') {
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'applications', 'pid' => $pid_link], $lang->projektboerse_applications);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'edit', 'pid' => $pid_link], $lang->projektboerse_edit);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'delete_form', 'pid' => $pid_link], $lang->projektboerse_delete);
        } elseif ($project['status'] === 'implementation') {
            // Bewerbungen bleiben auch in der Umsetzungsphase erreichbar, damit
            // das Team weitere Umsetzende annehmen kann (Mehrfach-Annahme).
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'applications', 'pid' => $pid_link], $lang->projektboerse_applications);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'edit', 'pid' => $pid_link], $lang->projektboerse_edit);
            $actions[] = projektboerse_modcp_action_button('complete', $pid_link,
                $lang->projektboerse_complete, $lang->projektboerse_confirm_complete, true);
            $actions[] = projektboerse_modcp_action_button('reopen', $pid_link,
                $lang->projektboerse_reopen, $lang->projektboerse_confirm_reopen, true);
            $actions[] = projektboerse_modcp_action_button('archive', $pid_link,
                $lang->projektboerse_archive, $lang->projektboerse_confirm_archive, true);
        } elseif ($project['status'] === 'completed') {
            $actions[] = projektboerse_modcp_action_button('archive', $pid_link,
                $lang->projektboerse_archive, $lang->projektboerse_confirm_archive, true);
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'delete_form', 'pid' => $pid_link], $lang->projektboerse_delete);
        } else {
            $actions[] = projektboerse_get_button('modcp.php', ['action' => 'projektboerse', 'do' => 'delete_form', 'pid' => $pid_link], $lang->projektboerse_delete);
        }

        $action_buttons = implode(' ', $actions);

        $deadline_warning = '';
        if ($project['status'] === 'implementation' && !empty($project['implementation_deadline'])
            && strtotime($project['implementation_deadline']) < TIME_NOW) {
            $deadline_warning = '<div class="projekt_deadline_expired">' .
                '<strong>&#9888; ' . $lang->projektboerse_deadline_exceeded . '</strong></div>';
        }

        eval('$out .= "' . $templates->get('projektboerse_modcp_project') . '";');
    }

    if ($out === '') {
        $out = '<div class="trow1 projektboerse_empty">' . $empty_text . '</div>';
    }

    return $out;
}

/**
 * Anzeige des Freigabe-Formulars.
 */
function projektboerse_modcp_approve_form(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project || $project['status'] !== 'pending') {
        error($lang->projektboerse_project_not_found);
    }

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");
    add_breadcrumb($project['title'], "modcp.php?action=projektboerse&do=approve&pid=" . $pid);

    $project['title']       = htmlspecialchars_uni((string)$project['title']);
    $project['description'] = htmlspecialchars_uni((string)$project['description']);
    $project['reward']      = htmlspecialchars_uni((string)($project['reward'] ?? ''));
    $project['wiki_links']  = htmlspecialchars_uni((string)($project['wiki_links'] ?? ''));

    $category_select      = projektboerse_render_select('projektboerse_categories', 'category_id', 'name',
        (int)$project['category_id'], $lang->projektboerse_select_category, 'category_id');
    $type_select          = projektboerse_render_select('projektboerse_types', 'type_id', 'name',
        (int)$project['project_type_id'], $lang->projektboerse_select_type, 'project_type_id');
    $communication_select = projektboerse_render_select('projektboerse_communication', 'comm_id', 'name',
        (int)$project['communication_id'], $lang->projektboerse_select_communication, 'communication_id');

    $contact_person_select = projektboerse_render_team_select((int)($project['contact_person_uid'] ?? 0));

    $feedback_deadline_value       = !empty($project['feedback_deadline']) ? date('Y-m-d', strtotime($project['feedback_deadline'])) : '';
    $implementation_deadline_value = !empty($project['implementation_deadline']) ? date('Y-m-d', strtotime($project['implementation_deadline'])) : '';

    // Plotting-Gruppen-Checkboxen – identisch zum Erstell-/Bearbeiten-Formular
    // (siehe projektboerse_render_project_form). Zeigt alle freigegebenen
    // Gruppen an, vorausgewählt sind die bereits verknüpften. So kann das Team
    // bei der Freigabe Gruppen sowohl an- als auch abwählen. Ohne plottinggroups
    // oder ohne freigegebene Gruppen entfällt die Zeile (kein groups_present),
    // damit der Speicherpfad den Bestand nicht anfasst.
    $groups_field     = '';
    $available_groups = ProjektboerseGroups::approvedGroups();
    if ($available_groups) {
        $selected_gids = ProjektboerseProject::get_group_ids($pid);

        $groups_checkboxes = '';
        foreach ($available_groups as $gid => $name) {
            $checked = in_array((int)$gid, $selected_gids, true) ? ' checked="checked"' : '';
            $groups_checkboxes .= '<label>' .
                '<input type="checkbox" name="gids[]" value="' . (int)$gid . '"' . $checked . ' /> ' .
                htmlspecialchars_uni((string)$name) . '</label>';
        }

        $groups_field = '<div class="trow2 projektboerse_field">' .
            '<input type="hidden" name="groups_present" value="1" />' .
            '<label>' . $lang->projektboerse_groups . '</label>' .
            '<div class="projektboerse_groups_list">' . $groups_checkboxes . '</div>' .
            '<span class="projektboerse_hint">' . $lang->projektboerse_groups_hint . '</span>' .
            '</div>';
    }

    eval('$page = "' . $templates->get('projektboerse_approve_form') . '";');
    output_page($page);
}

/**
 * Verarbeitet die Freigabe eines Projekts.
 */
function projektboerse_modcp_submit_approve(): void
{
    global $mybb, $db, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);

    $title          = trim((string)$mybb->get_input('title'));
    $description    = trim((string)$mybb->get_input('description'));
    $category_id    = (int)$mybb->get_input('category_id', MyBB::INPUT_INT);
    $project_type_id = (int)$mybb->get_input('project_type_id', MyBB::INPUT_INT);
    $communication_id = (int)$mybb->get_input('communication_id', MyBB::INPUT_INT);

    if ($title === '')          error($lang->projektboerse_error_title_empty);
    if ($description === '')    error($lang->projektboerse_error_description_empty);
    if ($category_id === 0)     error($lang->projektboerse_error_category_empty);
    if ($project_type_id === 0) error($lang->projektboerse_error_type_empty);
    if ($communication_id === 0) error($lang->projektboerse_error_communication_empty);

    $feedback_deadline       = $mybb->get_input('feedback_deadline');
    $implementation_deadline = $mybb->get_input('implementation_deadline');

    $update = [
        'title'                   => $title,
        'description'             => $description,
        'category_id'             => $category_id,
        'project_type_id'         => $project_type_id,
        'communication_id'        => $communication_id,
        'reward'                  => (string)$mybb->get_input('reward'),
        'feedback_deadline'       => !empty($feedback_deadline) ? $feedback_deadline . ' 23:59:59' : '',
        'implementation_deadline' => !empty($implementation_deadline) ? $implementation_deadline . ' 23:59:59' : '',
        'wiki_links'              => (string)$mybb->get_input('wiki_links'),
        'contact_person_uid'      => (int)$mybb->get_input('contact_person_uid', MyBB::INPUT_INT),
    ];

    // Plotting-Gruppen nur übernehmen, wenn das Formular den Block gerendert hat
    // (siehe projektboerse_modcp_approve_form). Sonst bleibt der Bestand in Ruhe.
    if (ProjektboerseGroups::available()
        && (int)$mybb->get_input('groups_present', MyBB::INPUT_INT) === 1) {
        $update['gids'] = $mybb->get_input('gids', MyBB::INPUT_ARRAY);
    }

    ProjektboerseProject::update($pid, $update);

    if (ProjektboerseProject::approve($pid, (int)$mybb->user['uid'])) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_approved_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Anzeige des Ablehnen-Formulars (Begründung eingeben).
 */
function projektboerse_modcp_reject_form(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");
    add_breadcrumb($lang->projektboerse_reject, "modcp.php?action=projektboerse&do=reject_form&pid=" . $pid);

    $project['title'] = htmlspecialchars_uni((string)$project['title']);

    eval('$page = "' . $templates->get('projektboerse_modcp_reject_form') . '";');
    output_page($page);
}

/**
 * Lehnt ein Projekt ab. Sendet die Begründung als PN an den Ersteller.
 */
function projektboerse_modcp_reject(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid    = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $reason = trim((string)$mybb->get_input('reason'));

    if ($reason === '') {
        error($lang->projektboerse_error_reject_reason_empty);
    }

    if (ProjektboerseProject::reject($pid, (int)$mybb->user['uid'], $reason)) {
        projektboerse_send_reject_pm($pid, $reason);
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_rejected_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Anzeige der Lösch-Bestätigung.
 */
function projektboerse_modcp_delete_form(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    if (!Projektboerse::can_delete_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");
    add_breadcrumb($lang->projektboerse_delete, "modcp.php?action=projektboerse&do=delete_form&pid=" . $pid);

    $project['title'] = htmlspecialchars_uni((string)$project['title']);

    eval('$page = "' . $templates->get('projektboerse_modcp_delete_form') . '";');
    output_page($page);
}

/**
 * Schickt dem Projektersteller eine PN mit der Ablehnungs-Begründung.
 * Nutzt MyBBs send_pm() als System-Nachricht (admin_override = true).
 */
function projektboerse_send_reject_pm(int $pid, string $reason): void
{
    global $mybb, $lang;

    if (empty($mybb->settings['enablepms'])) {
        return;
    }

    $project = ProjektboerseProject::get($pid);
    if (!$project) {
        return;
    }

    require_once MYBB_ROOT . 'inc/functions.php';

    $title   = (string)$project['title'];
    $subject = $lang->sprintf($lang->projektboerse_pm_rejected_subject, $title);
    $message = $lang->sprintf($lang->projektboerse_pm_rejected_message, $title, $reason);

    $pm_data = [
        'subject'    => $subject,
        'message'    => $message,
        'touid'      => (int)$project['uid'],
        'receivepms' => true,
    ];

    send_pm($pm_data, (int)$mybb->user['uid'], true);
}

/**
 * Bewerbung annehmen.
 */
function projektboerse_modcp_accept_application(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $aid    = (int)$mybb->get_input('aid', MyBB::INPUT_INT);
    $notes  = (string)$mybb->get_input('notes');
    $result = ProjektboerseApplication::accept($aid, (int)$mybb->user['uid'], $notes);

    if (isset($result['success'])) {
        // Zurück zur Bewerbungsliste des Projekts, damit das Team weitere
        // Bewerbungen annehmen oder einzeln ablehnen kann (Mehrfach-Annahme).
        redirect(projektboerse_modcp_applications_redirect((int)($result['pid'] ?? 0)),
            $lang->projektboerse_application_accepted);
    } else {
        error($result['error'] ?? $lang->projektboerse_error);
    }
}

/**
 * Bewerbung ablehnen.
 */
function projektboerse_modcp_reject_application(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $aid    = (int)$mybb->get_input('aid', MyBB::INPUT_INT);
    $notes  = (string)$mybb->get_input('notes');
    $result = ProjektboerseApplication::reject($aid, (int)$mybb->user['uid'], $notes);

    if (isset($result['success'])) {
        redirect(projektboerse_modcp_applications_redirect((int)($result['pid'] ?? 0)),
            $lang->projektboerse_application_rejected);
    } else {
        error($result['error'] ?? $lang->projektboerse_error);
    }
}

/**
 * Liefert das Redirect-Ziel nach einer Bewerbungs-Aktion: die Bewerbungsliste
 * des Projekts, falls bekannt – sonst der ModCP-Index als Fallback.
 */
function projektboerse_modcp_applications_redirect(int $pid): string
{
    return $pid > 0
        ? "modcp.php?action=projektboerse&do=applications&pid=" . $pid
        : "modcp.php?action=projektboerse";
}

/**
 * Projekt abschließen.
 */
function projektboerse_modcp_complete(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    if (ProjektboerseProject::complete($pid, (int)$mybb->user['uid'])) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_completed_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Projekt archivieren.
 */
function projektboerse_modcp_archive(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    if (ProjektboerseProject::archive($pid, (int)$mybb->user['uid'])) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_archived_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Projekt erneut öffnen.
 */
function projektboerse_modcp_reopen(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    if (ProjektboerseProject::reopen($pid, (int)$mybb->user['uid'])) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_reopened_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Feedback-Phase manuell beenden und für Bewerbungen öffnen (active → open).
 */
function projektboerse_modcp_open_applications(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    if (ProjektboerseProject::open_for_applications($pid, (int)$mybb->user['uid'])) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_opened_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Projekt löschen.
 */
function projektboerse_modcp_delete(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);

    if (!Projektboerse::can_delete_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    if (ProjektboerseProject::delete($pid)) {
        redirect("modcp.php?action=projektboerse", $lang->projektboerse_deleted_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * Edit-Formular für ein Projekt.
 */
function projektboerse_modcp_edit(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    if (!Projektboerse::can_edit_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");
    add_breadcrumb($project['title'], "modcp.php?action=projektboerse&do=edit&pid=" . $pid);

    projektboerse_render_project_form($project, 'update', 'modcp.php?action=projektboerse',
        $lang->projektboerse_edit . ' - ' . $project['title'],
        $lang->projektboerse_save, true);
}

/**
 * Projekt-Update aus dem Edit-Formular speichern.
 */
function projektboerse_modcp_update(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);

    if (!Projektboerse::can_edit_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    $feedback_deadline       = $mybb->get_input('feedback_deadline');
    $implementation_deadline = $mybb->get_input('implementation_deadline');

    $data = [
        'title'                   => (string)$mybb->get_input('title'),
        'description'             => (string)$mybb->get_input('description'),
        'reward'                  => (string)$mybb->get_input('reward'),
        'contact_person_uid'      => (int)$mybb->get_input('contact_person_uid', MyBB::INPUT_INT),
        'feedback_deadline'       => !empty($feedback_deadline) ? $feedback_deadline . ' 23:59:59' : '',
        'implementation_deadline' => !empty($implementation_deadline) ? $implementation_deadline . ' 23:59:59' : '',
        'communication_id'        => (int)$mybb->get_input('communication_id', MyBB::INPUT_INT),
        'project_type_id'         => (int)$mybb->get_input('project_type_id', MyBB::INPUT_INT),
        'category_id'             => (int)$mybb->get_input('category_id', MyBB::INPUT_INT),
        'wiki_links'              => (string)$mybb->get_input('wiki_links'),
    ];

    // Plotting-Gruppen nur übernehmen, wenn das Formular den Block auch
    // gerendert hat – sonst würde ein Speichern die Zuordnungen löschen.
    if (ProjektboerseGroups::available()
        && (int)$mybb->get_input('groups_present', MyBB::INPUT_INT) === 1) {
        $data['gids'] = $mybb->get_input('gids', MyBB::INPUT_ARRAY);
    }

    if (ProjektboerseProject::update($pid, $data)) {
        redirect("projektboerse.php?do=view&pid=" . $pid, $lang->projektboerse_updated_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * ModCP-Übersicht aller Bewerbungen eines Projekts.
 */
function projektboerse_modcp_applications(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang, $modcp_nav;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    add_breadcrumb($lang->projektboerse_modcp, "modcp.php?action=projektboerse");
    add_breadcrumb($project['title'], "projektboerse.php?do=view&pid=" . $pid);
    add_breadcrumb($lang->projektboerse_applications, "modcp.php?action=projektboerse&do=applications&pid=" . $pid);

    $applications_list = '';
    foreach (ProjektboerseApplication::get_by_project($pid) as $application) {
        $applicant = projektboerse_get_user((int)$application['uid']);
        $applicant_formatted = $applicant
            ? build_profile_link(format_name($applicant['username'], $applicant['usergroup'], $applicant['displaygroup']), (int)$applicant['uid'])
            : '#' . (int)$application['uid'];

        $submitted_date = my_date('normal', strtotime($application['submitted_at']));
        $project_title  = htmlspecialchars_uni((string)$project['title']);
        $application_text_parsed = projektboerse_parse_message((string)$application['application_text']);

        $application_status_badge = projektboerse_render_application_status_badge((string)$application['status']);
        $application_actions      = projektboerse_render_modcp_application_actions($application);

        eval('$applications_list .= "' . $templates->get('projektboerse_modcp_application') . '";');
    }

    if ($applications_list === '') {
        $applications_list = '<div class="trow1 projektboerse_empty">' .
            $lang->projektboerse_no_applications . '</div>';
    }

    $pending_projects     = '';
    $pending_applications = $applications_list;
    $all_projects         = '';

    eval('$page = "' . $templates->get('projektboerse_modcp') . '";');
    output_page($page);
}

// ============================================================================
// Frontend-Render-Funktionen (werden von projektboerse.php aufgerufen)
// ============================================================================

/**
 * Frontend-Index: alle öffentlichen Projekte, gruppiert nach Kategorie.
 */
function projektboerse_index(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    add_breadcrumb($lang->projektboerse, "projektboerse.php");

    $submit_link = Projektboerse::can_submit()
        ? projektboerse_get_button('projektboerse.php', ['do' => 'create'], $lang->projektboerse_create)
        : '';
    eval('$projektboerse_nav = "' . $templates->get('projektboerse_nav') . '";');
    $projektboerse_filter = '';

    // "Offene Projekte" = alles Öffentliche, das noch im Lauf ist.
    // Außen vor bleiben:
    //   pending   – wartet auf Freigabe, intern (Team-View)
    //   completed – abgeschlossen, erscheint unter "Archivierte Projekte"
    //   rejected  – End-Zustand, abgelehnt
    //   archived  – End-Zustand, archiviert
    //   inactive  – End-Zustand, deaktiviert
    $public_where = "status IN ('active','open','implementation')";

    // Übersicht nach Kategorien gruppiert. Jede Kategorie ist ein eigener
    // Abschnitt; daher keine globale Pagination mehr (die Kategorie-Blöcke
    // strukturieren die Seite).
    $projektboerse_projects = projektboerse_render_project_sections($public_where);
    $page_title             = $lang->projektboerse_all_projects;

    eval('$page = "' . $templates->get('projektboerse_index_categories') . '";');
    output_page($page);
}

/**
 * Frontend: Liste nach Status gefiltert.
 */
function projektboerse_list_projects(string $status = 'active'): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    add_breadcrumb($lang->projektboerse, "projektboerse.php");

    $titles = [
        'active'    => $lang->projektboerse_active_projects,
        'open'      => $lang->projektboerse_open_projects,
        'completed' => $lang->projektboerse_archived_projects,
    ];
    $page_title = $titles[$status] ?? $lang->projektboerse_projects;

    add_breadcrumb($page_title, "projektboerse.php?do=" . $status);

    $submit_link = Projektboerse::can_submit()
        ? projektboerse_get_button('projektboerse.php', ['do' => 'create'], $lang->projektboerse_create)
        : '';
    eval('$projektboerse_nav = "' . $templates->get('projektboerse_nav') . '";');
    $projektboerse_filter = '';

    $per_page = max(1, (int)($mybb->settings['projektboerse_per_page'] ?? 20));
    $page     = max(1, (int)$mybb->get_input('page', MyBB::INPUT_INT));
    $start    = ($page - 1) * $per_page;

    $where          = "status = '" . $db->escape_string($status) . "'";
    $total_projects = (int)$db->fetch_field(
        $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', $where), 'c');

    $projektboerse_projects = projektboerse_render_project_list($where, $per_page, $start);

    $multipage = multipage($total_projects, $per_page, $page, "projektboerse.php?do=" . $status);

    eval('$page = "' . $templates->get('projektboerse_index') . '";');
    output_page($page);
}

/**
 * Rendert eine einzelne Projektkachel (projektboerse_project_bit) für die
 * Frontend-Übersicht. Zentral, damit flache Liste und kategorisierte Ansicht
 * dieselbe Aufbereitung teilen.
 */
function projektboerse_render_project_bit(array $project): string
{
    global $mybb, $db, $templates, $lang;

    $owner = projektboerse_get_user((int)$project['uid']);
    $owner_formatted = $owner
        ? build_profile_link(format_name($owner['username'], $owner['usergroup'], $owner['displaygroup']), (int)$owner['uid'])
        : '#' . (int)$project['uid'];

    $status_name  = Projektboerse::get_status_name((string)$project['status']);
    $status_class = Projektboerse::get_status_class((string)$project['status']);

    $cat_name = $db->fetch_field(
        $db->simple_select('projektboerse_categories', 'name',
            "category_id = " . (int)$project['category_id']), 'name');
    $category_name = $cat_name ?: '-';

    $project['title']            = htmlspecialchars_uni((string)$project['title']);
    $description_plain           = strip_tags((string)$project['description']);
    $project['description_short'] = htmlspecialchars_uni(mb_substr($description_plain, 0, 200));
    if (mb_strlen($description_plain) > 200) {
        $project['description_short'] .= '…';
    }

    $created_date            = my_date('relative', strtotime($project['created_at']));
    $projekt_deadline_warning = '';

    $projekt_groups_display = '';
    $tile_group_names = ProjektboerseGroups::groupNames(
        ProjektboerseProject::get_group_ids((int)$project['pid'])
    );
    if ($tile_group_names) {
        $projekt_groups_display = '<div class="projekt_groups">' .
            projektboerse_render_group_pills($tile_group_names) . '</div>';
    }

    if ($project['status'] === 'implementation' && !empty($project['implementation_deadline'])
        && strtotime($project['implementation_deadline']) < TIME_NOW) {
        $projekt_deadline_warning = '<div class="projekt_deadline_expired">' .
            '<strong>&#9888; ' . $lang->projektboerse_deadline_exceeded . '</strong></div>';
    }

    $out = '';
    eval('$out = "' . $templates->get('projektboerse_project_bit') . '";');
    return $out;
}

/**
 * Rendert eine Liste von Projektkacheln auf der Frontend-Übersicht.
 *
 * @param int $limit 0 = keine Begrenzung (alle Treffer).
 */
function projektboerse_render_project_list(string $where, int $limit, int $start): string
{
    global $db, $lang;

    $options = ['order_by' => 'created_at', 'order_dir' => 'DESC'];
    if ($limit > 0) {
        $options['limit_start'] = $start;
        $options['limit']       = $limit;
    }
    $query = $db->simple_select('projektboerse_projects', '*', $where, $options);

    $out = '';
    while ($project = $db->fetch_array($query)) {
        $out .= projektboerse_render_project_bit($project);
    }

    if ($out === '') {
        $out = '<div class="trow1 projektboerse_empty">' .
            $lang->projektboerse_no_projects . '</div>';
    }

    return $out;
}

/**
 * Rendert die Übersicht in nach Kategorie gruppierte Abschnitte. Jede Kategorie
 * mit mindestens einem passenden Projekt bekommt einen eigenen Section-Block
 * (in Anzeige-Reihenfolge disporder). Projekte ohne gültige Kategorie landen in
 * einem Sammel-Abschnitt am Ende.
 */
function projektboerse_render_project_sections(string $where): string
{
    global $db, $lang;

    // Kategorien in Anzeige-Reihenfolge.
    $categories = [];
    $cat_query  = $db->simple_select('projektboerse_categories', 'category_id, name', '',
        ['order_by' => 'disporder, name']);
    while ($cat = $db->fetch_array($cat_query)) {
        $categories[(int)$cat['category_id']] = (string)$cat['name'];
    }

    // Alle passenden Projekte laden und nach Kategorie gruppieren.
    $grouped = [];
    $query   = $db->simple_select('projektboerse_projects', '*', $where,
        ['order_by' => 'created_at', 'order_dir' => 'DESC']);
    while ($project = $db->fetch_array($query)) {
        $cid = (int)$project['category_id'];
        if (!isset($categories[$cid])) {
            // Unbekannte oder gelöschte Kategorie → Sammelgruppe (Schlüssel 0).
            $cid = 0;
        }
        $grouped[$cid][] = $project;
    }

    if (empty($grouped)) {
        return '<div class="tborder projektboerse_section">' .
            '<div class="trow1 projektboerse_empty">' . $lang->projektboerse_no_projects .
            '</div></div>';
    }

    $out = '';
    foreach ($categories as $cid => $cat_name) {
        if (empty($grouped[$cid])) {
            continue;
        }
        $out .= projektboerse_render_category_section($cat_name, $grouped[$cid]);
    }
    if (!empty($grouped[0])) {
        $out .= projektboerse_render_category_section($lang->projektboerse_uncategorized, $grouped[0]);
    }

    return $out;
}

/**
 * Rendert einen einzelnen Kategorie-Abschnitt (thead + Projektkacheln).
 *
 * @param array<int,array> $projects
 */
function projektboerse_render_category_section(string $category_name, array $projects): string
{
    $bits = '';
    foreach ($projects as $project) {
        $bits .= projektboerse_render_project_bit($project);
    }

    return '<div class="tborder projektboerse_section">' .
        '<div class="thead"><strong>' . htmlspecialchars_uni($category_name) . '</strong> ' .
        '<span class="smalltext projektboerse_cat_count">(' . count($projects) . ')</span></div>' .
        $bits .
        '</div>';
}

/**
 * Frontend-Detailseite eines Projekts.
 */
function projektboerse_view_project(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    if (!Projektboerse::can_view_project($project, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($project['title'], "projektboerse.php?do=view&pid=" . $pid);

    $owner = projektboerse_get_user((int)$project['uid']);
    $owner_formatted = $owner
        ? build_profile_link(format_name($owner['username'], $owner['usergroup'], $owner['displaygroup']), (int)$owner['uid'])
        : '#' . (int)$project['uid'];

    $contact_formatted = '-';
    if (!empty($project['contact_person_uid'])) {
        $contact = projektboerse_get_user((int)$project['contact_person_uid']);
        if ($contact) {
            $contact_formatted = build_profile_link(
                format_name($contact['username'], $contact['usergroup'], $contact['displaygroup']),
                (int)$contact['uid']);
        }
    }

    $status_name  = Projektboerse::get_status_name((string)$project['status']);
    $status_class = Projektboerse::get_status_class((string)$project['status']);

    $category_name = (string)$db->fetch_field(
        $db->simple_select('projektboerse_categories', 'name',
            "category_id = " . (int)$project['category_id']), 'name') ?: '-';
    $type_name = (string)$db->fetch_field(
        $db->simple_select('projektboerse_types', 'name',
            "type_id = " . (int)$project['project_type_id']), 'name') ?: '-';
    $communication_name = (string)$db->fetch_field(
        $db->simple_select('projektboerse_communication', 'name',
            "comm_id = " . (int)$project['communication_id']), 'name') ?: '-';

    $feedback_deadline = !empty($project['feedback_deadline']) && $project['feedback_deadline'] !== '0000-00-00 00:00:00'
        ? my_date('normal', strtotime($project['feedback_deadline'])) : '-';
    $implementation_deadline = !empty($project['implementation_deadline']) && $project['implementation_deadline'] !== '0000-00-00 00:00:00'
        ? my_date('normal', strtotime($project['implementation_deadline'])) : '-';

    // Plotting-Gruppen. groupNames() liefert auch [] wenn plottinggroups
    // fehlt – die Sektion entfällt dann einfach.
    $groups_section = '';
    $group_names    = ProjektboerseGroups::groupNames(ProjektboerseProject::get_group_ids($pid));
    if ($group_names) {
        $groups_section = '<div class="trow1 projektboerse_field">' .
            '<dfn>' . $lang->projektboerse_groups . '</dfn>' .
            '<div class="projekt_groups">' . projektboerse_render_group_pills($group_names) . '</div>' .
            '</div>';
    }

    // Implementer.
    $implementers_section = '';
    $impl_query = $db->query("
        SELECT u.uid, u.username, u.usergroup, u.displaygroup
        FROM " . TABLE_PREFIX . "projektboerse_implementers i
        LEFT JOIN " . TABLE_PREFIX . "users u ON i.uid = u.uid
        WHERE i.pid = " . $pid);
    $impl_html = '';
    while ($impl = $db->fetch_array($impl_query)) {
        if (!$impl['uid']) {
            continue;
        }
        $impl_html .= build_profile_link(
            format_name($impl['username'], $impl['usergroup'], $impl['displaygroup']),
            (int)$impl['uid']) . ' ';
    }
    if ($impl_html !== '') {
        $implementers_section = '<div class="trow2 projektboerse_field">' .
            '<dfn>' . $lang->projektboerse_implementers . '</dfn>' .
            '<div>' . $impl_html . '</div>' .
            '</div>';
    }

    // Deadline-Warnung.
    $deadline_warning_display = '';
    if ($project['status'] === 'implementation' && !empty($project['implementation_deadline'])
        && strtotime($project['implementation_deadline']) < TIME_NOW) {
        $deadline_warning_display = '<div class="projekt_deadline_expired"><strong>&#9888; ' .
            $lang->projektboerse_deadline_exceeded . '</strong></div>';
    }

    // Links (BBCode).
    $wiki_links_section = '';
    if (!empty($project['wiki_links'])) {
        $wiki_links_section = '<div class="trow1 projektboerse_field">' .
            '<dfn>' . $lang->projektboerse_wiki_links . '</dfn>' .
            '<div>' . projektboerse_format_links((string)$project['wiki_links']) . '</div>' .
            '</div>';
    }

    // Aktions-Buttons.
    $action_buttons = '';
    $already_applied = false;
    if ((int)$mybb->user['uid'] > 0) {
        $already_applied = (int)$db->fetch_field(
            $db->simple_select('projektboerse_applications', 'COUNT(*) AS c',
                "pid = " . $pid . " AND uid = " . (int)$mybb->user['uid']), 'c') > 0;
    }
    // Hinweis: Kein separater "Bewerben"-Button hier – er wäre nur ein Ankersprung
    // zum Bewerbungsformular, das ohnehin direkt darunter angezeigt wird (siehe
    // $application_form_section). Der Doppel-Button hat User verwirrt.
    if (Projektboerse::can_edit_project($pid, (int)$mybb->user['uid'])) {
        $action_buttons .= projektboerse_get_button('projektboerse.php', ['do' => 'edit', 'pid' => $pid],
            $lang->projektboerse_edit);
    }
    if ($action_buttons === '') {
        $action_buttons = '&nbsp;';
    }

    // Felder fürs Template aufbereiten:
    // - Titel: immer reines Escape (taucht u.a. in <title>/Breadcrumbs auf).
    // - Beschreibung + Belohnung: via projektboerse_format_html() – respektiert
    //   die Setting projektboerse_allow_html (sicherer Sanitizer oder Escape).
    $project['title']       = htmlspecialchars_uni((string)$project['title']);
    $project['description'] = projektboerse_format_html((string)$project['description']);
    $reward_raw             = (string)($project['reward'] ?? '');
    $project['reward']      = $reward_raw === '' ? '-' : projektboerse_format_html($reward_raw);

    // Kommentare (nur Feedback-Phase).
    $comments_section = '';
    if ($project['status'] === 'active') {
        $comments_list = '';
        foreach (ProjektboerseComment::get_by_project($pid) as $comment) {
            $commenter = projektboerse_get_user((int)$comment['uid']);
            $commenter_formatted = $commenter
                ? build_profile_link(format_name($commenter['username'], $commenter['usergroup'], $commenter['displaygroup']), (int)$commenter['uid'])
                : '#' . (int)$comment['uid'];

            $comment_date = my_date('normal', strtotime($comment['created_at']));
            $comment_text = projektboerse_parse_message((string)$comment['comment']);

            eval('$comments_list .= "' . $templates->get('projektboerse_comment_bit') . '";');
        }
        if ($comments_list === '') {
            $comments_list = '<div class="trow1 projektboerse_empty">' .
                $lang->projektboerse_no_comments . '</div>';
        }

        $comment_form = '';
        if ((int)$mybb->user['uid'] > 0) {
            eval('$comment_form = "' . $templates->get('projektboerse_comment_form') . '";');
        }
        eval('$comments_section = "' . $templates->get('projektboerse_comments') . '";');
    }

    // Bewerbungs-Liste (nur Team + Ersteller). Auch in der Umsetzungsphase
    // sichtbar, damit das Team weitere Bewerbungen annehmen kann
    // (Mehrfach-Annahme) und der Ersteller den Stand sieht.
    $applications_section = '';
    if (in_array($project['status'], ['open', 'implementation'], true)
        && (Projektboerse::is_team_member((int)$mybb->user['uid']) || (int)$project['uid'] === (int)$mybb->user['uid'])) {
        $applications_list = '';
        foreach (ProjektboerseApplication::get_by_project($pid) as $app) {
            $applicant = projektboerse_get_user((int)$app['uid']);
            $applicant_formatted = $applicant
                ? build_profile_link(format_name($applicant['username'], $applicant['usergroup'], $applicant['displaygroup']), (int)$applicant['uid'])
                : '#' . (int)$app['uid'];

            $submitted_date          = my_date('normal', strtotime($app['submitted_at']));
            $application_text_parsed = projektboerse_parse_message((string)$app['application_text']);
            $application_status_badge = projektboerse_render_application_status_badge((string)$app['status']);

            $app_actions = '';
            if (Projektboerse::is_team_member((int)$mybb->user['uid'])) {
                $app_actions = projektboerse_render_modcp_application_actions($app);
            }

            eval('$applications_list .= "' . $templates->get('projektboerse_application_bit') . '";');
        }
        if ($applications_list === '') {
            $applications_list = '<div class="trow1 projektboerse_empty">' .
                $lang->projektboerse_no_applications . '</div>';
        }
        eval('$applications_section = "' . $templates->get('projektboerse_applications_section') . '";');
    }

    // Bewerbungsformular.
    $application_form_section = '';
    if ($project['status'] === 'open' && Projektboerse::can_apply((int)$mybb->user['uid'])) {
        if ($already_applied) {
            $application_form_section = '<div class="tborder projektboerse_section">' .
                '<div class="trow1 projekt_deadline_warning">' .
                $lang->projektboerse_already_applied .
                '</div></div>';
        } else {
            eval('$application_form_section = "' . $templates->get('projektboerse_application_form') . '";');
        }
    }

    eval('$page = "' . $templates->get('projektboerse_view') . '";');
    output_page($page);
}

/**
 * Formular zum Erstellen eines neuen Projekts.
 */
function projektboerse_create_project(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    if (!Projektboerse::can_submit((int)$mybb->user['uid'])) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($lang->projektboerse_create, "projektboerse.php?do=create");

    $project = [
        'pid'                => 0,
        'title'              => '',
        'description'        => '',
        'category_id'        => 0,
        'project_type_id'    => 0,
        'communication_id'   => 0,
        'wiki_links'         => '',
        'contact_person_uid' => 0,
        'reward'             => '',
    ];

    projektboerse_render_project_form($project, 'submit', 'projektboerse.php',
        $lang->projektboerse_create, $lang->projektboerse_submit, false);
}

/**
 * POST-Handler: neues Projekt einreichen.
 */
function projektboerse_submit_project(): void
{
    global $mybb, $lang;

    if (!Projektboerse::can_submit((int)$mybb->user['uid'])) {
        error_no_permission();
    }

    verify_post_check($mybb->get_input('my_post_key'));

    $title       = trim((string)$mybb->get_input('title'));
    $description = trim((string)$mybb->get_input('description'));
    $category_id = (int)$mybb->get_input('category_id', MyBB::INPUT_INT);

    if ($title === '')       error($lang->projektboerse_error_title_empty);
    if ($description === '') error($lang->projektboerse_error_description_empty);
    if ($category_id === 0)  error($lang->projektboerse_error_category_empty);

    $data = [
        'title'       => $title,
        'description' => $description,
        'category_id' => $category_id,
    ];

    // Plotting-Gruppen nur übernehmen, wenn das Formular den Block auch
    // gerendert hat – siehe projektboerse_render_project_form().
    if (ProjektboerseGroups::available()
        && (int)$mybb->get_input('groups_present', MyBB::INPUT_INT) === 1) {
        $data['gids'] = $mybb->get_input('gids', MyBB::INPUT_ARRAY);
    }

    $pid = ProjektboerseProject::create($data);
    if ($pid > 0) {
        redirect("projektboerse.php?do=view&pid=" . $pid, $lang->projektboerse_project_submitted);
    } else {
        error($lang->projektboerse_error_creating);
    }
}

/**
 * Frontend-Editform für ein Projekt.
 * Berechtigung über Projektboerse::can_edit_project() – Team immer,
 * Ersteller nur solange das Projekt im Status "pending" ist.
 */
function projektboerse_edit_project(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $project = ProjektboerseProject::get($pid);

    if (!$project) {
        error($lang->projektboerse_project_not_found);
    }

    if (!Projektboerse::can_edit_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($project['title'], "projektboerse.php?do=view&pid=" . $pid);
    add_breadcrumb($lang->projektboerse_edit, "projektboerse.php?do=edit&pid=" . $pid);

    // Team darf alle optionalen Felder sehen/ändern; der Ersteller im
    // pending-Status sieht nur die Basis-Felder (Titel/Beschreibung/Kategorie/Tags).
    $is_team = Projektboerse::is_team_member((int)$mybb->user['uid']);

    projektboerse_render_project_form($project, 'update', 'projektboerse.php',
        $lang->projektboerse_edit . ' - ' . $project['title'],
        $lang->projektboerse_save, $is_team);
}

/**
 * Frontend-POST-Handler: Projekt-Update speichern.
 */
function projektboerse_update_project(): void
{
    global $mybb, $lang;

    verify_post_check($mybb->get_input('my_post_key'));

    $pid = (int)$mybb->get_input('pid', MyBB::INPUT_INT);

    if (!Projektboerse::can_edit_project($pid, (int)$mybb->user['uid'])) {
        error_no_permission();
    }

    $is_team = Projektboerse::is_team_member((int)$mybb->user['uid']);

    $data = [
        'title'       => (string)$mybb->get_input('title'),
        'description' => (string)$mybb->get_input('description'),
        'category_id' => (int)$mybb->get_input('category_id', MyBB::INPUT_INT),
    ];

    // Team-only-Felder nur akzeptieren, wenn der Aufrufer auch Team ist.
    // Verhindert, dass ein Ersteller (pending) per Direkt-POST z.B. den
    // Status oder die Deadlines manipuliert.
    if ($is_team) {
        $feedback_deadline       = $mybb->get_input('feedback_deadline');
        $implementation_deadline = $mybb->get_input('implementation_deadline');

        $data['reward']                  = (string)$mybb->get_input('reward');
        $data['contact_person_uid']      = (int)$mybb->get_input('contact_person_uid', MyBB::INPUT_INT);
        $data['feedback_deadline']       = !empty($feedback_deadline) ? $feedback_deadline . ' 23:59:59' : '';
        $data['implementation_deadline'] = !empty($implementation_deadline) ? $implementation_deadline . ' 23:59:59' : '';
        $data['communication_id']        = (int)$mybb->get_input('communication_id', MyBB::INPUT_INT);
        $data['project_type_id']         = (int)$mybb->get_input('project_type_id', MyBB::INPUT_INT);
        $data['wiki_links']              = (string)$mybb->get_input('wiki_links');
    }

    // Bewusst AUSSERHALB des $is_team-Blocks: auch der Einreicher darf die
    // Gruppen seines noch nicht freigegebenen Projekts ändern.
    // Plotting-Gruppen nur übernehmen, wenn das Formular den Block auch
    // gerendert hat – sonst würde ein Speichern die Zuordnungen löschen.
    if (ProjektboerseGroups::available()
        && (int)$mybb->get_input('groups_present', MyBB::INPUT_INT) === 1) {
        $data['gids'] = $mybb->get_input('gids', MyBB::INPUT_ARRAY);
    }

    if (ProjektboerseProject::update($pid, $data)) {
        redirect("projektboerse.php?do=view&pid=" . $pid, $lang->projektboerse_updated_success);
    } else {
        error($lang->projektboerse_error);
    }
}

/**
 * POST-Handler: Kommentar zu einem Projekt hinzufügen.
 */
function projektboerse_add_comment(): void
{
    global $mybb, $lang;

    if ((int)$mybb->user['uid'] === 0) {
        error_no_permission();
    }

    verify_post_check($mybb->get_input('my_post_key'));

    $pid     = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $comment = trim((string)$mybb->get_input('comment'));

    if ($comment === '') {
        error($lang->projektboerse_comment_empty);
    }

    $project = ProjektboerseProject::get($pid);
    if (!$project || $project['status'] !== 'active') {
        error($lang->projektboerse_error_comment_not_allowed);
    }

    ProjektboerseComment::create($pid, $comment);

    redirect("projektboerse.php?do=view&pid=" . $pid, $lang->projektboerse_comment_added);
}

/**
 * POST-Handler: Bewerbung einreichen.
 */
function projektboerse_submit_application(): void
{
    global $mybb, $lang;

    if (!Projektboerse::can_apply((int)$mybb->user['uid'])) {
        error_no_permission();
    }

    verify_post_check($mybb->get_input('my_post_key'));

    $pid              = (int)$mybb->get_input('pid', MyBB::INPUT_INT);
    $application_text = trim((string)$mybb->get_input('application_text'));

    if ($application_text === '') {
        error($lang->projektboerse_motivation_empty);
    }

    $result = ProjektboerseApplication::create($pid, $application_text);
    if (isset($result['success'])) {
        redirect("projektboerse.php?do=view&pid=" . $pid, $lang->projektboerse_application_submitted);
    } else {
        error($result['error'] ?? $lang->projektboerse_error_applying);
    }
}

/**
 * Frontend-Seite "Meine Bewerbungen".
 */
function projektboerse_my_applications(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    if ((int)$mybb->user['uid'] === 0) {
        error_no_permission();
    }

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($lang->projektboerse_my_applications, "projektboerse.php?do=my_applications");

    $submit_link = Projektboerse::can_submit()
        ? projektboerse_get_button('projektboerse.php', ['do' => 'create'], $lang->projektboerse_create)
        : '';
    eval('$projektboerse_nav = "' . $templates->get('projektboerse_nav') . '";');

    $applications_list = '';
    $query = $db->query("
        SELECT a.*, p.title AS project_title, p.status AS project_status
        FROM " . TABLE_PREFIX . "projektboerse_applications a
        LEFT JOIN " . TABLE_PREFIX . "projektboerse_projects p ON a.pid = p.pid
        WHERE a.uid = " . (int)$mybb->user['uid'] . "
        ORDER BY a.submitted_at DESC
    ");

    while ($application = $db->fetch_array($query)) {
        $project_title  = htmlspecialchars_uni((string)$application['project_title']);
        $submitted_date = my_date('normal', strtotime($application['submitted_at']));
        $project_link   = 'projektboerse.php?do=view&amp;pid=' . (int)$application['pid'];
        $application_text_parsed = projektboerse_parse_message((string)$application['application_text']);

        if ($application['status'] === 'submitted') {
            $status_display = '<span class="projekt_status projekt_status_pending">&#9203; ' . $lang->projektboerse_status_submitted . '</span>';
        } elseif ($application['status'] === 'accepted') {
            $status_display = '<span class="projekt_status projekt_status_completed">&#10003; ' . $lang->projektboerse_status_accepted . '</span>';
        } else {
            $status_display = '<span class="projekt_status projekt_status_rejected">&#10007; ' . $lang->projektboerse_status_rejected . '</span>';
        }

        eval('$applications_list .= "' . $templates->get('projektboerse_my_application_bit') . '";');
    }

    if ($applications_list === '') {
        $applications_list = '<div class="trow1 projektboerse_empty">' .
            $lang->projektboerse_no_my_applications . '</div>';
    }

    eval('$page = "' . $templates->get('projektboerse_my_applications') . '";');
    output_page($page);
}

/**
 * Frontend-Statistikseite.
 */
function projektboerse_stats(): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($lang->projektboerse_stats, "projektboerse.php?do=stats");

    $submit_link = Projektboerse::can_submit()
        ? projektboerse_get_button('projektboerse.php', ['do' => 'create'], $lang->projektboerse_create)
        : '';
    eval('$projektboerse_nav = "' . $templates->get('projektboerse_nav') . '";');

    $count = function (string $where = '') use ($db): int {
        return (int)$db->fetch_field(
            $db->simple_select('projektboerse_projects', 'COUNT(*) AS c', $where), 'c');
    };

    $total_projects = $count();
    $active_count   = $count("status = 'active'");
    $completed_count = $count("status = 'completed'");

    $total_applications = (int)$db->fetch_field(
        $db->simple_select('projektboerse_applications', 'COUNT(*) AS c'), 'c');

    // Top-Implementer.
    $top_implementers = '';
    $impl_query = $db->query("
        SELECT u.uid, u.username, u.usergroup, u.displaygroup, COUNT(*) AS impl_count
        FROM " . TABLE_PREFIX . "projektboerse_implementers i
        LEFT JOIN " . TABLE_PREFIX . "users u ON i.uid = u.uid
        GROUP BY i.uid
        ORDER BY impl_count DESC
        LIMIT 10
    ");
    while ($user = $db->fetch_array($impl_query)) {
        if (!$user['uid']) {
            continue;
        }
        $user_formatted = build_profile_link(
            format_name($user['username'], $user['usergroup'], $user['displaygroup']),
            (int)$user['uid']);
        $top_implementers .= '<div class="trow1 statistik_row">' .
            '<span class="statistik_row_count">' . (int)$user['impl_count'] . ' ' . $lang->projektboerse_implementations . '</span>' .
            '<strong>' . $user_formatted . '</strong>' .
            '</div>';
    }
    if ($top_implementers === '') {
        $top_implementers = '<div class="trow1 projektboerse_empty">' . $lang->projektboerse_no_data . '</div>';
    }

    // Aktuelle Arbeit.
    $current_work_list = '';
    $work_query = $db->query("
        SELECT p.pid, p.title, p.implementation_deadline
        FROM " . TABLE_PREFIX . "projektboerse_projects p
        WHERE p.status = 'implementation'
        ORDER BY p.implementation_deadline ASC
    ");
    while ($project = $db->fetch_array($work_query)) {
        $deadline = !empty($project['implementation_deadline'])
            ? my_date('normal', strtotime($project['implementation_deadline'])) : '-';
        $exceeded = !empty($project['implementation_deadline'])
            && strtotime($project['implementation_deadline']) < TIME_NOW;
        $deadline_html = $exceeded
            ? '<span class="projektboerse_required">' . $deadline . '</span>'
            : '<span class="smalltext">' . $deadline . '</span>';

        // Umsetzer:innen des Projekts ermitteln (n:m – es können mehrere sein).
        $impl_query = $db->query("
            SELECT u.uid, u.username, u.usergroup, u.displaygroup
            FROM " . TABLE_PREFIX . "projektboerse_implementers i
            LEFT JOIN " . TABLE_PREFIX . "users u ON i.uid = u.uid
            WHERE i.pid = " . (int)$project['pid'] . "
            ORDER BY u.username ASC
        ");
        $impl_names = [];
        while ($impl = $db->fetch_array($impl_query)) {
            if (!$impl['uid']) {
                continue;
            }
            $impl_names[] = build_profile_link(
                format_name($impl['username'], $impl['usergroup'], $impl['displaygroup']),
                (int)$impl['uid']);
        }
        $implementers_html = !empty($impl_names)
            ? '<span class="smalltext statistik_row_implementers">' .
                $lang->projektboerse_implementers . ': ' . implode(', ', $impl_names) . '</span>'
            : '';

        $current_work_list .= '<div class="trow1 statistik_row">' .
            '<span class="statistik_row_count">' . $deadline_html . '</span>' .
            '<span class="statistik_row_main">' .
            '<a href="projektboerse.php?do=view&amp;pid=' . (int)$project['pid'] . '">' .
            htmlspecialchars_uni((string)$project['title']) . '</a>' .
            $implementers_html .
            '</span>' .
            '</div>';
    }
    if ($current_work_list === '') {
        $current_work_list = '<div class="trow1 projektboerse_empty">' .
            $lang->projektboerse_no_current_implementations . '</div>';
    }

    eval('$page = "' . $templates->get('projektboerse_stats') . '";');
    output_page($page);
}

/**
 * Statische Anleitung: erklärt Usern die Projektbörse, den Ablauf und die
 * einzelnen Phasen. Rein informativ, keine DB-Zugriffe.
 */
function projektboerse_guide(): void
{
    global $mybb, $templates, $theme, $headerinclude, $header, $footer, $lang;

    add_breadcrumb($lang->projektboerse, "projektboerse.php");
    add_breadcrumb($lang->projektboerse_guide, "projektboerse.php?do=guide");

    $submit_link = Projektboerse::can_submit()
        ? projektboerse_get_button('projektboerse.php', ['do' => 'create'], $lang->projektboerse_create)
        : '';
    eval('$projektboerse_nav = "' . $templates->get('projektboerse_nav') . '";');

    eval('$page = "' . $templates->get('projektboerse_guide') . '";');
    output_page($page);
}

// ============================================================================
// Form-Rendering-Helper
// ============================================================================

/**
 * Rendert ein Select für eine Standardtabelle.
 *
 * @param string $table       Tabelle ohne Präfix.
 * @param string $value_col   Spalte für value.
 * @param string $label_col   Spalte für Anzeigetext.
 * @param int    $selected    Aktuell gewählter Wert.
 * @param string $placeholder Erstes Placeholder-Option (oder leer).
 * @param string $field_name  HTML-Feldname.
 */
function projektboerse_render_select(string $table, string $value_col, string $label_col,
    int $selected, string $placeholder, string $field_name): string
{
    global $db;

    $out = '<select name="' . htmlspecialchars_uni($field_name) . '">';
    if ($placeholder !== '') {
        $out .= '<option value="">-- ' . htmlspecialchars_uni($placeholder) . ' --</option>';
    }

    $query = $db->simple_select($table, '*', '', ['order_by' => 'disporder']);
    while ($row = $db->fetch_array($query)) {
        $is_selected = ((int)$row[$value_col] === $selected) ? ' selected="selected"' : '';
        $out .= '<option value="' . (int)$row[$value_col] . '"' . $is_selected . '>' .
            htmlspecialchars_uni((string)$row[$label_col]) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

/**
 * Rendert das Team-Dropdown (für Kontaktperson).
 */
function projektboerse_render_team_select(int $selected): string
{
    global $lang;

    $out = '<select name="contact_person_uid"><option value="0">' .
        htmlspecialchars_uni($lang->projektboerse_no_contact_person) . '</option>';

    foreach (projektboerse_get_team_members() as $uid => $username) {
        $is_selected = ($uid === $selected) ? ' selected="selected"' : '';
        $out .= '<option value="' . (int)$uid . '"' . $is_selected . '>' .
            htmlspecialchars_uni((string)$username) . '</option>';
    }
    $out .= '</select>';
    return $out;
}

/**
 * Rendert das Projekt-Formular (Anlegen oder Bearbeiten).
 *
 * @param array  $project           Projekt-Datensatz (oder leere Defaults).
 * @param string $form_action       Form-Action-Token (z.B. 'submit', 'update').
 * @param string $form_action_url   Form-Action-URL.
 * @param string $form_title        Überschrift des Formulars.
 * @param string $submit_button_text Button-Beschriftung.
 * @param bool   $is_edit           true = alle Felder anzeigen; false = nur Basis.
 */
function projektboerse_render_project_form(array $project, string $form_action, string $form_action_url,
    string $form_title, string $submit_button_text, bool $is_edit): void
{
    global $mybb, $db, $templates, $theme, $headerinclude, $header, $footer, $lang;

    // Felder fürs Template escapen.
    $project['title']       = htmlspecialchars_uni((string)($project['title'] ?? ''));
    $project['description'] = htmlspecialchars_uni((string)($project['description'] ?? ''));
    $project['reward']      = htmlspecialchars_uni((string)($project['reward'] ?? ''));
    $project['wiki_links']  = htmlspecialchars_uni((string)($project['wiki_links'] ?? ''));

    $category_select = projektboerse_render_select('projektboerse_categories', 'category_id', 'name',
        (int)($project['category_id'] ?? 0), $lang->projektboerse_select_category, 'category_id');

    // Im Erstellen-Modus sind viele Felder unsichtbar (werden bei Freigabe ergänzt).
    $type_field = $reward_field = $communication_field = $contact_person_field = '';
    $feedback_deadline_field = $implementation_deadline_field = $wiki_links_field = '';

    if ($is_edit) {
        $type_select = projektboerse_render_select('projektboerse_types', 'type_id', 'name',
            (int)($project['project_type_id'] ?? 0), $lang->projektboerse_select_type, 'project_type_id');
        $type_field = '<div class="trow2 projektboerse_field"><label>' . $lang->projektboerse_type . '</label>' . $type_select . '</div>';

        $reward_field = '<div class="trow1 projektboerse_field"><label>' . $lang->projektboerse_reward .
            '</label><input type="text" name="reward" value="' . $project['reward'] . '" /></div>';

        $communication_select = projektboerse_render_select('projektboerse_communication', 'comm_id', 'name',
            (int)($project['communication_id'] ?? 0), $lang->projektboerse_select_communication, 'communication_id');
        $communication_field = '<div class="trow2 projektboerse_field"><label>' . $lang->projektboerse_communication . '</label>' .
            $communication_select . '</div>';

        $contact_person_select = projektboerse_render_team_select((int)($project['contact_person_uid'] ?? 0));
        $contact_person_field = '<div class="trow1 projektboerse_field"><label>' . $lang->projektboerse_contact_person .
            '</label>' . $contact_person_select . '</div>';

        $feedback_deadline_value = !empty($project['feedback_deadline']) ? date('Y-m-d', strtotime($project['feedback_deadline'])) : '';
        $feedback_deadline_field = '<div class="trow2 projektboerse_field"><label>' . $lang->projektboerse_feedback_deadline .
            '</label><input type="date" name="feedback_deadline" value="' . htmlspecialchars_uni($feedback_deadline_value) . '" /></div>';

        $implementation_deadline_value = !empty($project['implementation_deadline']) ? date('Y-m-d', strtotime($project['implementation_deadline'])) : '';
        $implementation_deadline_field = '<div class="trow1 projektboerse_field"><label>' . $lang->projektboerse_implementation_deadline .
            '</label><input type="date" name="implementation_deadline" value="' . htmlspecialchars_uni($implementation_deadline_value) . '" /></div>';

        $wiki_links_field = '<div class="trow2 projektboerse_field"><label>' . $lang->projektboerse_wiki_links .
            '</label><textarea name="wiki_links" rows="3" placeholder="' .
            htmlspecialchars_uni($lang->projektboerse_wiki_links_placeholder) . '">' .
            $project['wiki_links'] . '</textarea>' .
            '<span class="projektboerse_hint">' . $lang->projektboerse_wiki_links_hint . '</span></div>';
    }

    // Plotting-Gruppen-Checkboxen. Fehlt plottinggroups oder gibt es keine
    // freigegebene Gruppe, entfällt die Zeile komplett – eine Auswahl, die der
    // User nicht befüllen kann, wäre nur Rauschen. Ohne den Block wird auch
    // kein groups_present gesendet, sodass die Speicherpfade den Bestand in
    // Ruhe lassen.
    $groups_field     = '';
    $available_groups = ProjektboerseGroups::approvedGroups();
    if ($available_groups) {
        $selected_gids = !empty($project['pid'])
            ? ProjektboerseProject::get_group_ids((int)$project['pid'])
            : [];

        $groups_checkboxes = '';
        foreach ($available_groups as $gid => $name) {
            $checked = in_array((int)$gid, $selected_gids, true) ? ' checked="checked"' : '';
            $groups_checkboxes .= '<label>' .
                '<input type="checkbox" name="gids[]" value="' . (int)$gid . '"' . $checked . ' /> ' .
                htmlspecialchars_uni((string)$name) . '</label>';
        }

        $groups_field = '<div class="trow2 projektboerse_field">' .
            '<input type="hidden" name="groups_present" value="1" />' .
            '<label>' . $lang->projektboerse_groups . '</label>' .
            '<div class="projektboerse_groups_list">' . $groups_checkboxes . '</div>' .
            '<span class="projektboerse_hint">' . $lang->projektboerse_groups_hint . '</span>' .
            '</div>';
    }

    eval('$page = "' . $templates->get('projektboerse_form') . '";');
    output_page($page);
}

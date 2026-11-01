<?php
/**
 * Projektboerse – Business-Klassen
 *
 * Trennt Permission-Logik (Projektboerse), Projekt-Operationen
 * (ProjektboerseProject), Bewerbungen (ProjektboerseApplication),
 * Kommentare (ProjektboerseComment) und Alert-Versand (ProjektboerseAlert).
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Permission-Helper – prüft, welcher User welche Aktion ausführen darf.
 */
class Projektboerse
{
    /**
     * Master-Allowlist: prüft, ob der User überhaupt mit der Projektbörse
     * interagieren darf. Charakter-Accounts werden hier ausgefiltert, indem
     * ihre Gruppen NICHT in projektboerse_allowed_groups eingetragen sind.
     *
     * Leeres Setting = keine Einschränkung (Backward-Compat zu 2.0.0).
     *
     * @param int $uid 0 = aktueller User.
     */
    public static function is_eligible(int $uid = 0): bool
    {
        global $mybb, $db;

        $allowed_raw = trim((string)($mybb->settings['projektboerse_allowed_groups'] ?? ''));
        if ($allowed_raw === '') {
            return true;
        }

        if ($uid === 0) {
            $usergroup        = $mybb->user['usergroup']        ?? '';
            $additionalgroups = $mybb->user['additionalgroups'] ?? '';
        } else {
            $query = $db->simple_select('users', 'usergroup, additionalgroups',
                "uid = " . (int)$uid);
            $user = $db->fetch_array($query);
            if (!$user) {
                return false;
            }
            $usergroup        = $user['usergroup'];
            $additionalgroups = $user['additionalgroups'];
        }

        $allowed_groups = array_filter(array_map('intval', explode(',', $allowed_raw)));
        $user_groups    = array_filter(array_map('intval',
            explode(',', $usergroup . ',' . $additionalgroups)));

        if (empty($allowed_groups) || empty($user_groups)) {
            return false;
        }

        return count(array_intersect($allowed_groups, $user_groups)) > 0;
    }

    /**
     * Prüft, ob der angegebene User Teammitglied ist.
     * Master-Allowlist (is_eligible) wird vorgeschaltet.
     *
     * @param int $uid 0 = aktueller User.
     */
    public static function is_team_member(int $uid = 0): bool
    {
        return self::is_eligible($uid)
            && self::user_in_setting_groups($uid, 'projektboerse_team_groups');
    }

    /**
     * Prüft, ob der angegebene User Projekte einreichen darf.
     * Master-Allowlist (is_eligible) wird vorgeschaltet.
     *
     * @param int $uid 0 = aktueller User.
     */
    public static function can_submit(int $uid = 0): bool
    {
        return self::is_eligible($uid)
            && self::user_in_setting_groups($uid, 'projektboerse_submit_groups');
    }

    /**
     * Prüft, ob der angegebene User sich bewerben darf.
     * Master-Allowlist (is_eligible) wird vorgeschaltet.
     *
     * @param int $uid 0 = aktueller User.
     */
    public static function can_apply(int $uid = 0): bool
    {
        return self::is_eligible($uid)
            && self::user_in_setting_groups($uid, 'projektboerse_apply_groups');
    }

    /**
     * Prüft, ob ein User ein Projekt bearbeiten darf.
     * Team darf alles; Ersteller darf nur im Status "pending".
     *
     * @param int $pid Projekt-ID.
     * @param int $uid 0 = aktueller User.
     */
    public static function can_edit_project(int $pid, int $uid = 0): bool
    {
        global $mybb;

        if ($uid === 0) {
            $uid = (int)$mybb->user['uid'];
        }

        if (self::is_team_member($uid)) {
            return true;
        }

        $project = ProjektboerseProject::get($pid);
        if ($project && (int)$project['uid'] === $uid && $project['status'] === 'pending') {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob der angegebene User Projekte löschen darf.
     *
     * @param int $pid Projekt-ID.
     * @param int $uid 0 = aktueller User.
     */
    public static function can_delete_project(int $pid, int $uid = 0): bool
    {
        return self::is_team_member($uid);
    }

    /**
     * Prüft, ob der angegebene User Bewerbungen verwalten darf.
     *
     * @param int $uid 0 = aktueller User.
     */
    public static function can_manage_applications(int $uid = 0): bool
    {
        return self::is_team_member($uid);
    }

    /**
     * Prüft, ob der angegebene User den Projekt-Status ändern darf.
     *
     * @param int $pid Projekt-ID (ungenutzt, Signatur stabil).
     * @param int $uid 0 = aktueller User.
     */
    public static function can_change_status(int $pid, int $uid = 0): bool
    {
        return self::is_team_member($uid);
    }

    /**
     * Prüft, ob der angegebene User ein Projekt sehen darf.
     *
     * @param array $project Projekt-Datensatz.
     * @param int   $uid     0 = aktueller User.
     */
    public static function can_view_project(array $project, int $uid = 0): bool
    {
        global $mybb;

        if ($uid === 0) {
            $uid = (int)$mybb->user['uid'];
        }

        if (self::is_team_member($uid)) {
            return true;
        }

        if ((int)$project['uid'] === $uid) {
            return true;
        }

        $public_status = ['active', 'open', 'implementation', 'completed'];
        if (in_array($project['status'], $public_status, true)) {
            return true;
        }

        return false;
    }

    /**
     * Liefert eine lesbare Bezeichnung für einen Status-Schlüssel.
     */
    public static function get_status_name(string $status): string
    {
        global $lang;
        $key = 'projektboerse_status_' . $status;
        return $lang->{$key} ?? $status;
    }

    /**
     * Liefert die zugehörige CSS-Klasse für einen Status.
     */
    public static function get_status_class(string $status): string
    {
        $classes = [
            'pending'        => 'projekt_status_pending',
            'active'         => 'projekt_status_active',
            'open'           => 'projekt_status_open',
            'implementation' => 'projekt_status_implementation',
            'completed'      => 'projekt_status_completed',
            'rejected'       => 'projekt_status_rejected',
            'archived'       => 'projekt_status_archived',
            'inactive'       => 'projekt_status_inactive',
        ];

        return $classes[$status] ?? 'projekt_status_pending';
    }

    /**
     * Gemeinsamer Helper: prüft Mitgliedschaft in den Gruppen aus einem Setting.
     *
     * @param int    $uid          0 = aktueller User.
     * @param string $setting_name Name des Settings (Komma-Liste mit Gruppen-IDs).
     */
    private static function user_in_setting_groups(int $uid, string $setting_name): bool
    {
        global $mybb, $db;

        if ($uid === 0) {
            $usergroup        = $mybb->user['usergroup']         ?? '';
            $additionalgroups = $mybb->user['additionalgroups']  ?? '';
        } else {
            $query = $db->simple_select('users', 'usergroup, additionalgroups',
                "uid = " . (int)$uid);
            $user = $db->fetch_array($query);
            if (!$user) {
                return false;
            }
            $usergroup        = $user['usergroup'];
            $additionalgroups = $user['additionalgroups'];
        }

        $allowed_groups = array_filter(array_map('intval',
            explode(',', (string)($mybb->settings[$setting_name] ?? ''))));
        $user_groups    = array_filter(array_map('intval',
            explode(',', $usergroup . ',' . $additionalgroups)));

        if (empty($allowed_groups) || empty($user_groups)) {
            return false;
        }

        return count(array_intersect($allowed_groups, $user_groups)) > 0;
    }
}

/**
 * Operationen rund um Projekt-Datensätze (CRUD + Workflow).
 */
class ProjektboerseProject
{
    /**
     * Legt ein neues Projekt im Status "pending" an.
     *
     * @param array $data Projekt-Daten.
     * @return int Neue Projekt-ID (0 bei Fehler).
     */
    public static function create(array $data): int
    {
        global $db, $mybb;

        $now = date('Y-m-d H:i:s');

        // MyBB 1.8 db_mysqli::insert_query() rendert `null` als `''`, was unter
        // MySQL 8 Strict Mode für INT/DATETIME-Spalten zum SQL-Error führt.
        // Optionale Felder daher gezielt weglassen, damit DEFAULT NULL greift.
        $insert_data = [
            'uid'         => (int)$mybb->user['uid'],
            'title'       => $db->escape_string((string)($data['title'] ?? '')),
            'description' => $db->escape_string((string)($data['description'] ?? '')),
            'status'      => 'pending',
            'created_at'  => $now,
            'updated_at'  => $now,
        ];

        if (isset($data['reward']) && $data['reward'] !== '') {
            $insert_data['reward'] = $db->escape_string((string)$data['reward']);
        }
        if (!empty($data['contact_person_uid'])) {
            $insert_data['contact_person_uid'] = (int)$data['contact_person_uid'];
        }
        if (!empty($data['feedback_deadline'])) {
            $insert_data['feedback_deadline'] = $db->escape_string((string)$data['feedback_deadline']);
        }
        if (!empty($data['implementation_deadline'])) {
            $insert_data['implementation_deadline'] = $db->escape_string((string)$data['implementation_deadline']);
        }
        if (!empty($data['communication_id'])) {
            $insert_data['communication_id'] = (int)$data['communication_id'];
        }
        if (!empty($data['project_type_id'])) {
            $insert_data['project_type_id'] = (int)$data['project_type_id'];
        }
        if (!empty($data['category_id'])) {
            $insert_data['category_id'] = (int)$data['category_id'];
        }
        if (isset($data['wiki_links']) && $data['wiki_links'] !== '') {
            $insert_data['wiki_links'] = $db->escape_string((string)$data['wiki_links']);
        }

        $pid = (int)$db->insert_query('projektboerse_projects', $insert_data);
        if ($pid === 0) {
            return 0;
        }

        if (isset($data['gids']) && is_array($data['gids'])) {
            self::set_groups($pid, $data['gids']);
        }

        ProjektboerseAlert::send_new_project_alert($pid);

        // Kein group_assigned-Alert an dieser Stelle: das Projekt ist noch
        // 'pending' und für Gruppenmitglieder nicht sichtbar (can_view_project).
        // Der Alert folgt bei der Freigabe in change_status().

        return $pid;
    }

    /**
     * Aktualisiert ein bestehendes Projekt.
     *
     * @param int   $pid  Projekt-ID.
     * @param array $data Zu aktualisierende Felder.
     */
    public static function update(int $pid, array $data): bool
    {
        global $db;

        $update_data = ['updated_at' => date('Y-m-d H:i:s')];

        $allowed_fields = [
            'title', 'description', 'reward', 'contact_person_uid',
            'feedback_deadline', 'implementation_deadline', 'communication_id',
            'project_type_id', 'category_id', 'wiki_links',
        ];

        foreach ($allowed_fields as $field) {
            if (!array_key_exists($field, $data)) {
                continue;
            }

            if (in_array($field, ['contact_person_uid', 'communication_id', 'project_type_id', 'category_id'], true)) {
                $update_data[$field] = (int)$data[$field];
            } elseif (in_array($field, ['feedback_deadline', 'implementation_deadline'], true)) {
                // MyBB 1.8 db_mysqli::update_query() rendert `null` als `''`, was unter
                // MySQL 8 Strict Mode am DATETIME-Feld zum SQL-Error führt. Wenn der
                // Wert leer ist, das Feld einfach nicht updaten.
                if (!empty($data[$field])) {
                    $update_data[$field] = $db->escape_string((string)$data[$field]);
                }
            } else {
                $update_data[$field] = $db->escape_string((string)$data[$field]);
            }
        }

        $db->update_query('projektboerse_projects', $update_data, "pid = " . (int)$pid);

        if (isset($data['gids']) && is_array($data['gids'])) {
            $diff = self::set_groups($pid, $data['gids']);
            if ($diff['added']) {
                // Nur neu hinzugekommene Gruppen benachrichtigen; die bereits
                // zugeordneten wurden schon beim Hinzufügen informiert.
                ProjektboerseAlert::send_group_assigned_alert($pid, $diff['added'], $diff['kept']);
            }
        }

        // Wenn das Update Deadlines verschoben hat, ggf. sofort Status
        // nachziehen – sonst hinkt der Anzeigestatus bis zum nächsten
        // Task-Lauf (alle 30 Minuten) hinterher.
        self::reconcile_deadlines($pid);

        return true;
    }

    /**
     * Prüft Deadline-getriebene Status-Übergänge und führt sie sofort aus.
     * Wird nach jedem update() aufgerufen, kann aber auch eigenständig vom
     * Task oder aus Tests heraus angestoßen werden.
     *
     * Aktuell behandelt:
     *   - active → open, wenn feedback_deadline in der Vergangenheit liegt
     */
    public static function reconcile_deadlines(int $pid): void
    {
        $project = self::get($pid);
        if (!$project) {
            return;
        }

        $now = TIME_NOW;

        if ($project['status'] === 'active'
            && !empty($project['feedback_deadline'])
            && $project['feedback_deadline'] !== '0000-00-00 00:00:00'
            && strtotime((string)$project['feedback_deadline']) < $now) {
            self::change_status($pid, 'open');
        }
    }

    /**
     * Setzt den Status eines Projekts und führt Workflow-Aktionen aus.
     *
     * @param int    $pid             Projekt-ID.
     * @param string $new_status      Ziel-Status.
     * @param int    $approved_by_uid User-ID des Genehmigers (für pending → active).
     */
    public static function change_status(int $pid, string $new_status, int $approved_by_uid = 0): bool
    {
        global $db, $mybb;

        $project = self::get($pid);
        if (!$project) {
            return false;
        }

        $old_status = (string)$project['status'];

        $update_data = [
            'status'     => $db->escape_string($new_status),
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // pending → active (Freigabe).
        if ($new_status === 'active' && $approved_by_uid > 0) {
            $update_data['approved_at']     = date('Y-m-d H:i:s');
            $update_data['approved_by_uid'] = $approved_by_uid;

            if (empty($project['feedback_deadline'])) {
                $default_days = max(1, (int)$mybb->settings['projektboerse_default_feedback_days']);
                $update_data['feedback_deadline'] = date('Y-m-d H:i:s', strtotime("+{$default_days} days"));
            }
        }

        // → implementation (bei Bewerbungsannahme).
        if ($new_status === 'implementation' && empty($project['implementation_deadline'])) {
            $default_days = max(1, (int)$mybb->settings['projektboerse_default_implementation_days']);
            $update_data['implementation_deadline'] = date('Y-m-d H:i:s', strtotime("+{$default_days} days"));
        }

        // → completed (Abschluss).
        if ($new_status === 'completed') {
            $update_data['completed_at'] = date('Y-m-d H:i:s');
        }

        // implementation → open (erneute Freigabe bei Deadline-Überschreitung).
        if ($new_status === 'open' && $old_status === 'implementation') {
            $db->delete_query('projektboerse_implementers', "pid = " . (int)$pid);

            $default_days = max(1, (int)$mybb->settings['projektboerse_default_feedback_days']);
            $update_data['feedback_deadline'] = date('Y-m-d H:i:s', strtotime("+{$default_days} days"));
        }

        $db->update_query('projektboerse_projects', $update_data, "pid = " . (int)$pid);

        ProjektboerseAlert::send_status_change_alert($pid, $new_status, $old_status);

        // Erstzustellung des Gruppen-Alerts: bei der Einreichung war das Projekt
        // noch 'pending' und für Gruppenmitglieder unsichtbar, deshalb erst jetzt.
        if ($new_status === 'active' && $old_status === 'pending') {
            ProjektboerseAlert::send_group_assigned_alert($pid, self::get_group_ids($pid));
        }

        // Sicherung gegen "Approve mit bereits abgelaufener Feedback-Deadline":
        // wenn das Projekt frisch auf active steht, die Deadlines aber schon
        // in der Vergangenheit liegen, ziehen wir den Status direkt nach.
        if ($new_status === 'active') {
            self::reconcile_deadlines($pid);
        }

        // Gruppen-Ereignishistorie: bei Freigabe (pending → active) und beim
        // Abschluss taucht die Meldung in den "Neuesten Ereignissen" jeder
        // zugeordneten Plotting-Gruppe auf. Best-Effort über die Brücke;
        // ohne plottinggroups passiert nichts.
        if ($new_status === 'active' && $old_status === 'pending') {
            self::log_group_event($pid, 'projektboerse_approved', (string)$project['title']);
        } elseif ($new_status === 'completed') {
            self::log_group_event($pid, 'projektboerse_completed', (string)$project['title']);
        }

        return true;
    }

    /**
     * Meldet ein Projekt-Ereignis an die Historie jeder zugeordneten
     * Plotting-Gruppe. Der Typ-Slug wird in der Anzeige über den Sprachschlüssel
     * plottinggroups_event_type_<type> lokalisiert (siehe Projektbörse-Sprachdatei).
     *
     * @param int    $pid   Projekt-ID.
     * @param string $type  Ereignis-Typ-Slug.
     * @param string $title Projekttitel (roh; wird beim Rendern escaped).
     */
    private static function log_group_event(int $pid, string $type, string $title): void
    {
        global $mybb;

        $gids = self::get_group_ids($pid);
        if (empty($gids)) {
            return;
        }

        $link = 'projektboerse.php?do=view&pid=' . (int)$pid;
        $uid  = (int)($mybb->user['uid'] ?? 0);

        foreach ($gids as $gid) {
            ProjektboerseGroups::logEvent((int)$gid, $type, $title, $link, $uid);
        }
    }

    /**
     * Trägt vergangene Freigaben/Abschlüsse rückwirkend in die Ereignis-Historie
     * der zugeordneten Plotting-Gruppen ein.
     *
     * Events werden sonst nur im Moment der Aktion geschrieben; bereits vorher
     * freigegebene/abgeschlossene Projekte fehlen daher in der Historie. Diese
     * Wartungsaktion rekonstruiert sie aus den vorhandenen Zeitstempeln
     * (approved_at/completed_at) MIT dem historischen Datum – nicht "jetzt".
     *
     * Idempotent: über ProjektboerseGroups::eventExists() wird jede (gid, type,
     * link)-Kombination nur einmal geschrieben. Mehrfaches Ausführen und die
     * Überschneidung mit bereits live geschriebenen Events erzeugen keine
     * Dubletten.
     *
     * @return array{added:int,skipped:int,unavailable:bool}
     */
    public static function backfill_group_events(): array
    {
        global $db;

        if (!ProjektboerseGroups::available() || !method_exists('Plottinggroups_Api', 'logEvent')) {
            return ['added' => 0, 'skipped' => 0, 'unavailable' => true];
        }

        $added   = 0;
        $skipped = 0;

        // Nur Projekte, die überhaupt einer Gruppe zugeordnet sind.
        $query = $db->query("
            SELECT p.pid, p.title, p.approved_at, p.approved_by_uid, p.completed_at, pg.gid
            FROM " . TABLE_PREFIX . "projektboerse_project_groups pg
            INNER JOIN " . TABLE_PREFIX . "projektboerse_projects p ON p.pid = pg.pid
            ORDER BY p.pid ASC
        ");

        while ($row = $db->fetch_array($query)) {
            $gid   = (int)$row['gid'];
            $pid   = (int)$row['pid'];
            $title = (string)$row['title'];
            $link  = 'projektboerse.php?do=view&pid=' . $pid;

            $candidates = [];
            if (!empty($row['approved_at']) && $row['approved_at'] !== '0000-00-00 00:00:00') {
                $candidates[] = ['type' => 'projektboerse_approved', 'when' => (string)$row['approved_at'], 'uid' => (int)$row['approved_by_uid']];
            }
            // completed_at deckt auch archivierte Projekte ab, die vorher
            // abgeschlossen wurden (completed_at bleibt beim Archivieren gesetzt).
            if (!empty($row['completed_at']) && $row['completed_at'] !== '0000-00-00 00:00:00') {
                $candidates[] = ['type' => 'projektboerse_completed', 'when' => (string)$row['completed_at'], 'uid' => 0];
            }

            foreach ($candidates as $ev) {
                if (ProjektboerseGroups::eventExists($gid, $ev['type'], $link)) {
                    $skipped++;
                    continue;
                }
                $dateline = (int)strtotime($ev['when']);
                ProjektboerseGroups::logEvent($gid, $ev['type'], $title, $link, $ev['uid'], $dateline);
                $added++;
            }
        }

        return ['added' => $added, 'skipped' => $skipped, 'unavailable' => false];
    }

    /**
     * Projekt freigeben (pending → active).
     */
    public static function approve(int $pid, int $approved_by_uid): bool
    {
        $project = self::get($pid);
        if (!$project || $project['status'] !== 'pending') {
            return false;
        }
        return self::change_status($pid, 'active', $approved_by_uid);
    }

    /**
     * Projekt ablehnen (Status → rejected).
     */
    public static function reject(int $pid, int $rejected_by_uid, string $reason = ''): bool
    {
        global $db;

        $project = self::get($pid);
        if (!$project) {
            return false;
        }

        $db->update_query('projektboerse_projects', [
            'status'     => 'rejected',
            'updated_at' => date('Y-m-d H:i:s'),
        ], "pid = " . (int)$pid);

        ProjektboerseAlert::send_rejected_alert($pid, $reason);

        return true;
    }

    /**
     * Projekt archivieren (Status → archived).
     */
    public static function archive(int $pid, int $archived_by_uid): bool
    {
        global $db;

        $project = self::get($pid);
        if (!$project) {
            return false;
        }

        $db->update_query('projektboerse_projects', [
            'status'     => 'archived',
            'updated_at' => date('Y-m-d H:i:s'),
        ], "pid = " . (int)$pid);

        ProjektboerseAlert::send_archived_alert($pid);

        return true;
    }

    /**
     * Projekt abschließen (Status → completed).
     */
    public static function complete(int $pid, int $completed_by_uid): bool
    {
        $project = self::get($pid);
        if (!$project) {
            return false;
        }
        return self::change_status($pid, 'completed');
    }

    /**
     * Projekt erneut für Bewerbungen freigeben (implementation → open).
     */
    public static function reopen(int $pid, int $reopened_by_uid): bool
    {
        $project = self::get($pid);
        if (!$project || $project['status'] !== 'implementation') {
            return false;
        }
        return self::change_status($pid, 'open');
    }

    /**
     * Feedback-Phase manuell beenden und für Bewerbungen öffnen (active → open).
     *
     * Löst denselben Wechsel aus wie der Deadline-Task, nur auf Knopfdruck durch
     * das Team – inkl. "feedback_ended"-Alert an den Ersteller.
     */
    public static function open_for_applications(int $pid, int $opened_by_uid): bool
    {
        $project = self::get($pid);
        if (!$project || $project['status'] !== 'active') {
            return false;
        }
        return self::change_status($pid, 'open');
    }

    /**
     * Projekt komplett löschen (inkl. Bewerbungen, Kommentare, Tags, Implementer, Logs).
     */
    public static function delete(int $pid): bool
    {
        global $db;

        $project = self::get($pid);
        if (!$project) {
            return false;
        }

        $db->delete_query('projektboerse_applications',  "pid = " . (int)$pid);
        $db->delete_query('projektboerse_comments',      "pid = " . (int)$pid);
        $db->delete_query('projektboerse_project_groups', "pid = " . (int)$pid);
        $db->delete_query('projektboerse_implementers',  "pid = " . (int)$pid);
        $db->delete_query('projektboerse_alertlog',      "pid = " . (int)$pid);
        $db->delete_query('projektboerse_projects',      "pid = " . (int)$pid);

        return true;
    }

    /**
     * Liefert ein Projekt anhand seiner ID.
     *
     * @return array|null
     */
    public static function get(int $pid): ?array
    {
        global $db;
        $query  = $db->simple_select('projektboerse_projects', '*', "pid = " . (int)$pid);
        $row    = $db->fetch_array($query);
        return $row ?: null;
    }

    /**
     * Liefert Projekte gefiltert nach Status, paginiert.
     *
     * @return array<int,array>
     */
    public static function get_by_status(string $status, int $start = 0, int $limit = 20): array
    {
        global $db;

        $query = $db->simple_select('projektboerse_projects', '*',
            "status = '" . $db->escape_string($status) . "'", [
                'order_by'    => 'created_at',
                'order_dir'   => 'DESC',
                'limit_start' => (int)$start,
                'limit'       => (int)$limit,
            ]);

        $projects = [];
        while ($project = $db->fetch_array($query)) {
            $projects[] = $project;
        }
        return $projects;
    }

    /**
     * Plotting-Gruppen eines Projekts.
     *
     * @param int $pid Projekt-ID.
     * @return int[] gids
     */
    public static function get_group_ids(int $pid): array
    {
        global $db;

        $gids  = [];
        $query = $db->simple_select('projektboerse_project_groups', 'gid', "pid = " . (int)$pid);
        while ($row = $db->fetch_array($query)) {
            if ((int)$row['gid'] > 0) {
                $gids[] = (int)$row['gid'];
            }
        }

        return $gids;
    }

    /**
     * Schreibt die Plotting-Gruppen eines Projekts und liefert die Differenz
     * zurück, damit Aufrufer nur die Mitglieder NEU hinzugekommener Gruppen
     * benachrichtigen. Ein Delete-all-Reinsert würde jeden Speichervorgang wie
     * eine Neuzuordnung aussehen lassen.
     *
     * Ist plottinggroups nicht verfügbar, bleibt der Bestand unangetastet –
     * siehe ProjektboerseGroups::normalizeSubmittedGids().
     *
     * @param int   $pid  Projekt-ID.
     * @param array $gids Übermittelte Gruppen-IDs.
     * @return array ['added' => int[], 'removed' => int[], 'kept' => int[]]
     */
    public static function set_groups(int $pid, array $gids): array
    {
        global $db;

        $normalized = ProjektboerseGroups::normalizeSubmittedGids($gids, $pid);
        if ($normalized === null) {
            return ['added' => [], 'removed' => [], 'kept' => self::get_group_ids($pid)];
        }

        $old     = self::get_group_ids($pid);
        $added   = array_values(array_diff($normalized, $old));
        $removed = array_values(array_diff($old, $normalized));
        $kept    = array_values(array_intersect($old, $normalized));

        if ($removed) {
            $db->delete_query('projektboerse_project_groups',
                "pid = " . (int)$pid . " AND gid IN (" . implode(',', array_map('intval', $removed)) . ")");
        }

        foreach ($added as $gid) {
            $db->insert_query('projektboerse_project_groups', [
                'pid'      => (int)$pid,
                'gid'      => (int)$gid,
                'dateline' => TIME_NOW,
            ]);
        }

        return ['added' => $added, 'removed' => $removed, 'kept' => $kept];
    }

    /**
     * Fügt einen Umsetzer zum Projekt hinzu und schickt – wenn $send_alert
     * true ist – einen "als Umsetzer zugewiesen"-Alert.
     *
     * In ProjektboerseApplication::accept() wird der Alert unterdrückt, weil
     * der Bewerber dort bereits den "Bewerbung angenommen"-Alert bekommt;
     * zwei Benachrichtigungen für einen einzigen Workflow-Schritt sind Lärm.
     */
    public static function add_implementer(int $pid, int $uid, int $assigned_by_uid, bool $send_alert = true): bool
    {
        global $db;

        $db->insert_query('projektboerse_implementers', [
            'pid'             => (int)$pid,
            'uid'             => (int)$uid,
            'assigned_at'     => date('Y-m-d H:i:s'),
            'assigned_by_uid' => (int)$assigned_by_uid,
        ]);

        if ($send_alert) {
            ProjektboerseAlert::send_implementer_assigned_alert($pid, $uid);
        }

        return true;
    }

    /**
     * Liefert alle Umsetzer-UIDs eines Projekts.
     *
     * @return array<int,int>
     */
    public static function get_implementers(int $pid): array
    {
        global $db;

        $query = $db->simple_select('projektboerse_implementers', 'uid', "pid = " . (int)$pid);

        $implementers = [];
        while ($row = $db->fetch_array($query)) {
            $implementers[] = (int)$row['uid'];
        }
        return $implementers;
    }
}

/**
 * Operationen rund um Bewerbungen.
 */
class ProjektboerseApplication
{
    /**
     * Reicht eine Bewerbung für ein Projekt ein.
     *
     * @param int    $pid              Projekt-ID.
     * @param string $application_text Bewerbungstext (Plaintext, wird escaped).
     * @return array { success: bool, aid?: int, error?: string }
     */
    public static function create(int $pid, string $application_text): array
    {
        global $db, $mybb, $lang;

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return ['error' => $lang->projektboerse_project_not_found];
        }

        if ($project['status'] !== 'open') {
            return ['error' => $lang->projektboerse_error_applying];
        }

        if (!Projektboerse::can_apply((int)$mybb->user['uid'])) {
            return ['error' => $lang->projektboerse_no_permission];
        }

        $existing = $db->simple_select('projektboerse_applications', 'aid',
            "pid = " . (int)$pid . " AND uid = " . (int)$mybb->user['uid']);
        if ($db->num_rows($existing) > 0) {
            return ['error' => $lang->projektboerse_already_applied];
        }

        $application_text = trim($application_text);
        if ($application_text === '') {
            return ['error' => $lang->projektboerse_motivation_empty];
        }

        $aid = (int)$db->insert_query('projektboerse_applications', [
            'pid'              => (int)$pid,
            'uid'              => (int)$mybb->user['uid'],
            'application_text' => $db->escape_string($application_text),
            'status'           => 'submitted',
            'submitted_at'     => date('Y-m-d H:i:s'),
        ]);

        ProjektboerseAlert::send_new_application_alert($pid, $aid);

        return ['success' => true, 'aid' => $aid];
    }

    /**
     * Bewerbung annehmen – setzt Status, fügt den Bewerber als Implementer hinzu
     * und schaltet das Projekt (beim ersten angenommenen Bewerber) auf
     * "implementation".
     *
     * Mehrfach-Annahme: Es können beliebig viele Bewerbungen pro Projekt
     * angenommen werden – jede macht den jeweiligen Bewerber zum Umsetzer.
     * Andere offene Bewerbungen bleiben bewusst im Status "submitted" und
     * können separat angenommen oder vom Team einzeln abgelehnt werden
     * (kein automatisches Ablehnen mehr).
     */
    public static function accept(int $aid, int $reviewed_by_uid, string $notes = ''): array
    {
        global $db, $lang;

        $application = self::get($aid);
        if (!$application) {
            return ['error' => $lang->projektboerse_error];
        }

        if ($application['status'] !== 'submitted') {
            return ['error' => $lang->projektboerse_error];
        }

        if (!Projektboerse::can_manage_applications($reviewed_by_uid)) {
            return ['error' => $lang->projektboerse_no_permission];
        }

        $pid = (int)$application['pid'];

        $db->update_query('projektboerse_applications', [
            'status'          => 'accepted',
            'reviewed_at'     => date('Y-m-d H:i:s'),
            'reviewed_by_uid' => (int)$reviewed_by_uid,
            'review_notes'    => $db->escape_string($notes),
        ], "aid = " . (int)$aid);

        // send_alert=false: der "Bewerbung angenommen"-Alert weiter unten
        // deckt den Vorgang inhaltlich ab. Ein zusätzlicher Implementer-
        // Alert wäre redundant.
        ProjektboerseProject::add_implementer($pid, (int)$application['uid'], $reviewed_by_uid, false);

        // Nur beim Übergang auf "implementation" den Status wechseln. Bei
        // weiteren Annahmen ist das Projekt bereits in Umsetzung – ein
        // erneuter change_status() würde nur updated_at anfassen.
        $project = ProjektboerseProject::get($pid);
        if ($project && $project['status'] !== 'implementation') {
            ProjektboerseProject::change_status($pid, 'implementation');
        }

        ProjektboerseAlert::send_application_accepted_alert($aid);

        return ['success' => true, 'pid' => $pid];
    }

    /**
     * Bewerbung ablehnen.
     */
    public static function reject(int $aid, int $reviewed_by_uid, string $notes = ''): array
    {
        global $db, $lang;

        $application = self::get($aid);
        if (!$application) {
            return ['error' => $lang->projektboerse_error];
        }

        if ($application['status'] !== 'submitted') {
            return ['error' => $lang->projektboerse_error];
        }

        if (!Projektboerse::can_manage_applications($reviewed_by_uid)) {
            return ['error' => $lang->projektboerse_no_permission];
        }

        $db->update_query('projektboerse_applications', [
            'status'          => 'rejected',
            'reviewed_at'     => date('Y-m-d H:i:s'),
            'reviewed_by_uid' => (int)$reviewed_by_uid,
            'review_notes'    => $db->escape_string($notes),
        ], "aid = " . (int)$aid);

        ProjektboerseAlert::send_application_rejected_alert($aid);

        return ['success' => true, 'pid' => (int)$application['pid']];
    }

    /**
     * Zählt alle wartenden Bewerbungen (für Red-Alert).
     */
    public static function count_pending(): int
    {
        global $db;
        $query = $db->simple_select('projektboerse_applications', 'COUNT(*) AS c', "status = 'submitted'");
        return (int)$db->fetch_field($query, 'c');
    }

    /**
     * Liefert eine einzelne Bewerbung.
     *
     * @return array|null
     */
    public static function get(int $aid): ?array
    {
        global $db;
        $query  = $db->simple_select('projektboerse_applications', '*', "aid = " . (int)$aid);
        $row    = $db->fetch_array($query);
        return $row ?: null;
    }

    /**
     * Alle Bewerbungen für ein Projekt.
     *
     * @return array<int,array>
     */
    public static function get_by_project(int $pid): array
    {
        global $db;

        $query = $db->simple_select('projektboerse_applications', '*', "pid = " . (int)$pid,
            ['order_by' => 'submitted_at', 'order_dir' => 'DESC']);

        $apps = [];
        while ($app = $db->fetch_array($query)) {
            $apps[] = $app;
        }
        return $apps;
    }
}

/**
 * Operationen rund um Feedback-Kommentare.
 */
class ProjektboerseComment
{
    /**
     * Legt einen Kommentar an.
     */
    public static function create(int $pid, string $comment): int
    {
        global $db, $mybb;

        $cid = (int)$db->insert_query('projektboerse_comments', [
            'pid'        => (int)$pid,
            'uid'        => (int)$mybb->user['uid'],
            'comment'    => $db->escape_string($comment),
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        ProjektboerseAlert::send_new_comment_alert($pid, $cid);

        return $cid;
    }

    /**
     * Aktualisiert einen Kommentar.
     */
    public static function update(int $cid, string $comment): bool
    {
        global $db;

        $db->update_query('projektboerse_comments', [
            'comment'    => $db->escape_string($comment),
            'updated_at' => date('Y-m-d H:i:s'),
        ], "cid = " . (int)$cid);

        return true;
    }

    /**
     * Liefert alle Kommentare eines Projekts in chronologischer Reihenfolge.
     *
     * @return array<int,array>
     */
    public static function get_by_project(int $pid): array
    {
        global $db;

        $query = $db->simple_select('projektboerse_comments', '*', "pid = " . (int)$pid,
            ['order_by' => 'created_at', 'order_dir' => 'ASC']);

        $comments = [];
        while ($comment = $db->fetch_array($query)) {
            $comments[] = $comment;
        }
        return $comments;
    }
}

/**
 * Alert-Versand über MyAlerts (Best-Effort: nutzt die DB direkt, weil MyAlerts
 * keine stabile öffentliche API hat; bricht stillschweigend ab, wenn MyAlerts
 * nicht installiert ist). Loggt jeden Versand in projektboerse_alertlog.
 */
class ProjektboerseAlert
{
    /**
     * Generischer Alert-Versand (Best-Effort).
     *
     * @param int    $uid        Empfänger.
     * @param string $event_type Plugin-spezifischer Event-Schlüssel (z.B. 'new_project').
     * @param string $link       Ziel-Link für den Alert.
     * @param int    $pid        Projekt-ID (für Log).
     * @param array  $extra      Zusätzliche Daten (werden als JSON ins Log geschrieben).
     */
    private static function send_alert(int $uid, string $event_type, string $link, int $pid = 0, array $extra = []): bool
    {
        global $db;

        if ($uid <= 0) {
            return false;
        }

        // Master-Allowlist: User außerhalb der berechtigten Gruppen
        // (z.B. Charakter-Accounts) bekommen grundsätzlich keine Alerts –
        // auch nicht, wenn sie als Projektersteller, Bewerber oder zusätzlicher
        // Empfänger eingetragen wären.
        if (!Projektboerse::is_eligible($uid)) {
            return false;
        }

        // MyAlerts über die offizielle API – Schema-unabhängig, Guard via
        // class_exists. Falls MyAlerts deinstalliert wird, fällt der Block
        // still durch; das projektboerse_alertlog läuft weiter.
        if (class_exists('MybbStuff_MyAlerts_AlertTypeManager')
            && class_exists('MybbStuff_MyAlerts_AlertManager')
            && class_exists('MybbStuff_MyAlerts_Entity_Alert')) {
            try {
                $alert_type_manager = MybbStuff_MyAlerts_AlertTypeManager::getInstance();
                $alert_manager      = MybbStuff_MyAlerts_AlertManager::getInstance();
                if ($alert_type_manager && $alert_manager) {
                    $alert_type = $alert_type_manager->getByCode('projektboerse_' . $event_type);
                    if ($alert_type !== null) {
                        $alert = new MybbStuff_MyAlerts_Entity_Alert(
                            $uid,
                            $alert_type,
                            (int)$pid
                        );
                        $alert->setExtraDetails(array_merge(['link' => $link], $extra));
                        $alert_manager->addAlert($alert);
                    }
                }
            } catch (\Throwable $e) {
                // MyAlerts-Version inkompatibel → ignorieren, alertlog hat den Datensatz.
            }
        }

        // Plugin-Log (immer, auch ohne MyAlerts) für Nachvollziehbarkeit.
        $db->insert_query('projektboerse_alertlog', [
            'pid'           => (int)$pid,
            'event_type'    => $db->escape_string('projektboerse_' . $event_type),
            'recipient_uid' => (int)$uid,
            'sent_at'       => date('Y-m-d H:i:s'),
            'alert_data'    => $db->escape_string(json_encode(['link' => $link] + $extra)),
        ]);

        return true;
    }

    /**
     * Alert an alle Teammitglieder: neues Projekt eingereicht.
     */
    public static function send_new_project_alert(int $pid): bool
    {
        global $mybb, $db;

        if ((int)$mybb->settings['projektboerse_alert_new_project'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $recipients = self::team_member_uids();

        // Zusätzliche konfigurierte Empfänger.
        if (!empty($mybb->settings['projektboerse_alert_new_project_recipients'])) {
            $additional = array_filter(array_map('intval',
                explode(',', (string)$mybb->settings['projektboerse_alert_new_project_recipients'])));
            $recipients = array_unique(array_merge($recipients, $additional));
        }

        $link = 'modcp.php?action=projektboerse&do=view&pid=' . $pid;

        foreach ($recipients as $uid) {
            if ($uid === (int)$project['uid']) {
                continue;
            }
            self::send_alert($uid, 'new_project', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Alert an den Ersteller: Projekt freigegeben.
     */
    public static function send_approved_alert(int $pid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_approved'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        self::send_alert((int)$project['uid'], 'approved', $link, $pid, ['title' => $project['title']]);

        // Zusätzliche Feedback-Phase-Empfänger.
        if (!empty($mybb->settings['projektboerse_feedback_recipients'])) {
            $recipients = array_filter(array_map('intval',
                explode(',', (string)$mybb->settings['projektboerse_feedback_recipients'])));
            foreach ($recipients as $uid) {
                if ($uid <= 0 || $uid === (int)$project['uid']) {
                    continue;
                }
                self::send_alert($uid, 'feedback', $link, $pid, ['title' => $project['title']]);
            }
        }

        return true;
    }

    /**
     * Alert an den Ersteller: Projekt abgelehnt.
     */
    public static function send_rejected_alert(int $pid, string $reason = ''): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_rejected'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        self::send_alert(
            (int)$project['uid'],
            'rejected',
            'projektboerse.php?do=view&pid=' . $pid,
            $pid,
            ['title' => $project['title'], 'reason' => $reason]
        );

        return true;
    }

    /**
     * Alert an Ersteller + Implementer: Projekt archiviert.
     */
    public static function send_archived_alert(int $pid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_archived'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $recipients = array_unique(array_merge(
            [(int)$project['uid']],
            ProjektboerseProject::get_implementers($pid)
        ));

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        foreach ($recipients as $uid) {
            self::send_alert($uid, 'archived', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Alert: neue Bewerbung eingegangen.
     *
     * Geht ausschließlich an den am Projekt eingetragenen Ansprechpartner
     * (contact_person_uid). Ist kein Ansprechpartner gesetzt, fällt der
     * Versand auf alle Teammitglieder zurück – sonst würde die Bewerbung
     * niemanden erreichen.
     */
    public static function send_new_application_alert(int $pid, int $aid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_new_application'] !== 1) {
            return false;
        }

        $project     = ProjektboerseProject::get($pid);
        $application = ProjektboerseApplication::get($aid);
        if (!$project || !$application) {
            return false;
        }

        $link = 'modcp.php?action=projektboerse&do=applications&pid=' . $pid;

        $contact_uid = (int)($project['contact_person_uid'] ?? 0);
        $recipients  = $contact_uid > 0 ? [$contact_uid] : self::team_member_uids();

        foreach ($recipients as $uid) {
            self::send_alert((int)$uid, 'new_application', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Alert an Team, Ersteller und Implementer: Deadline überschritten.
     */
    public static function send_deadline_alert(int $pid, string $deadline_type): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_deadline'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $recipients = array_unique(array_merge(
            self::team_member_uids(),
            ProjektboerseProject::get_implementers($pid),
            [(int)$project['uid']]
        ));

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        foreach ($recipients as $uid) {
            self::send_alert($uid, 'deadline', $link, $pid, [
                'title'         => $project['title'],
                'deadline_type' => $deadline_type,
            ]);
        }

        return true;
    }

    /**
     * Dispatcher für Status-Änderungen.
     */
    public static function send_status_change_alert(int $pid, string $new_status, string $old_status = ''): void
    {
        if ($new_status === 'active') {
            self::send_approved_alert($pid);
            // Broadcast an alle berechtigten User: ein neues Projekt ist in der
            // Projektbörse. Nur bei der echten Erstfreigabe (pending → active),
            // nicht bei internen Re-Writes auf 'active'.
            if ($old_status === 'pending') {
                self::send_published_alert($pid);
            }
        } elseif ($new_status === 'rejected') {
            self::send_rejected_alert($pid);
        } elseif ($new_status === 'archived') {
            self::send_archived_alert($pid);
        } elseif ($new_status === 'completed') {
            self::send_completed_alert($pid);
        } elseif ($new_status === 'open') {
            // Broadcast an alle berechtigten User: Projekt ist jetzt für
            // Bewerbungen offen (Feedback- → Bewerbungsphase; greift auch beim
            // Reopen aus 'implementation').
            self::send_open_alert($pid);

            // Persönlicher Hinweis an den Ersteller zum Ende der Feedbackphase.
            if ($old_status === 'active') {
                $project = ProjektboerseProject::get($pid);
                if ($project) {
                    self::send_alert(
                        (int)$project['uid'],
                        'feedback_ended',
                        'projektboerse.php?do=view&pid=' . $pid,
                        $pid,
                        ['title' => $project['title']]
                    );
                }
            }
        }
    }

    /**
     * Broadcast an alle berechtigten User: ein neues Projekt ist freigegeben
     * und in der Projektbörse (Feedbackphase) sichtbar.
     *
     * Empfänger = Master-Allowlist (projektboerse_allowed_groups); reine
     * Charakter-Accounts bleiben außen vor. Der Ersteller wird übersprungen –
     * der erhält den persönlichen 'approved'-Alert.
     */
    public static function send_published_alert(int $pid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_published'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        foreach (self::eligible_member_uids() as $uid) {
            if ($uid === (int)$project['uid']) {
                continue;
            }
            self::send_alert($uid, 'published', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Broadcast an alle berechtigten User: ein Projekt ist jetzt für
     * Bewerbungen offen (Bewerbungsphase).
     *
     * Empfänger = Master-Allowlist (projektboerse_allowed_groups); reine
     * Charakter-Accounts bleiben außen vor. Der Ersteller wird übersprungen –
     * der erhält den persönlichen 'feedback_ended'-Alert.
     */
    public static function send_open_alert(int $pid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_open'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        foreach (self::eligible_member_uids() as $uid) {
            if ($uid === (int)$project['uid']) {
                continue;
            }
            self::send_alert($uid, 'open', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Alert an Bewerber: Bewerbung angenommen.
     */
    public static function send_application_accepted_alert(int $aid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_application_accepted'] !== 1) {
            return false;
        }

        $application = ProjektboerseApplication::get($aid);
        if (!$application) {
            return false;
        }
        $project = ProjektboerseProject::get((int)$application['pid']);
        if (!$project) {
            return false;
        }

        self::send_alert(
            (int)$application['uid'],
            'application_accepted',
            'projektboerse.php?do=view&pid=' . (int)$application['pid'],
            (int)$application['pid'],
            ['title' => $project['title']]
        );

        return true;
    }

    /**
     * Alert an Bewerber: Bewerbung abgelehnt.
     */
    public static function send_application_rejected_alert(int $aid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_application_rejected'] !== 1) {
            return false;
        }

        $application = ProjektboerseApplication::get($aid);
        if (!$application) {
            return false;
        }
        $project = ProjektboerseProject::get((int)$application['pid']);
        if (!$project) {
            return false;
        }

        self::send_alert(
            (int)$application['uid'],
            'application_rejected',
            'projektboerse.php?do=view&pid=' . (int)$application['pid'],
            (int)$application['pid'],
            ['title' => $project['title']]
        );

        return true;
    }

    /**
     * Alert bei neuem Kommentar.
     *
     * Empfänger: Ersteller + Ansprechpartner + ggf. zugewiesene Implementer.
     * Der Kommentar-Verfasser selbst wird ausgeschlossen, sonst bekäme er
     * Alerts auf den eigenen Kommentar.
     */
    public static function send_new_comment_alert(int $pid, int $cid): bool
    {
        global $mybb, $db;

        if ((int)$mybb->settings['projektboerse_alert_comment'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        // Kommentar-Verfasser ermitteln – er soll keinen Alert auf den
        // eigenen Kommentar bekommen.
        $author_uid = 0;
        $row = $db->fetch_array($db->simple_select(
            'projektboerse_comments', 'uid', "cid = " . (int)$cid));
        if ($row) {
            $author_uid = (int)$row['uid'];
        }

        $recipients = [(int)$project['uid']];
        if (!empty($project['contact_person_uid'])) {
            $recipients[] = (int)$project['contact_person_uid'];
        }
        foreach (ProjektboerseProject::get_implementers($pid) as $impl_uid) {
            $recipients[] = (int)$impl_uid;
        }

        $recipients = array_diff(array_unique($recipients), [$author_uid, 0]);
        $link       = 'projektboerse.php?do=view&pid=' . $pid . '#comments';

        foreach ($recipients as $uid) {
            self::send_alert((int)$uid, 'new_comment', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Alert an Implementer: dem Projekt zugewiesen.
     */
    public static function send_implementer_assigned_alert(int $pid, int $uid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_implementer_assigned'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        self::send_alert(
            $uid,
            'implementer_assigned',
            'projektboerse.php?do=view&pid=' . $pid,
            $pid,
            ['title' => $project['title']]
        );

        return true;
    }

    /**
     * Alert an die Mitglieder neu zugewiesener Plotting-Gruppen.
     *
     * Wird zweimal ausgelöst: bei der Freigabe (pending → active) mit allen
     * Gruppen des Projekts, und bei späteren Bearbeitungen nur mit den neu
     * hinzugekommenen. $already_gids bleibt dabei ausgespart, damit ein
     * nachträglich ergänztes Projekt die bereits informierten Gruppen nicht
     * erneut anpingt.
     *
     * Empfänger laufen wie alle anderen durch send_alert() und damit durch
     * Projektboerse::is_eligible() – Gruppenmitglieder außerhalb der
     * Master-Allowlist (typischerweise Charakter-Accounts) bekommen bewusst
     * nichts.
     *
     * @param int[] $notify_gids  Gruppen, deren Mitglieder benachrichtigt werden.
     * @param int[] $already_gids Gruppen, deren Mitglieder ausgespart bleiben.
     */
    public static function send_group_assigned_alert(int $pid, array $notify_gids, array $already_gids = []): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_group_assigned'] !== 1) {
            return false;
        }
        if (!$notify_gids) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        // Nur benachrichtigen, wenn Gruppenmitglieder das Projekt auch aufrufen
        // dürfen – sonst führt der Alert-Link auf error_no_permission().
        // Liste identisch zu Projektboerse::can_view_project(); bei Änderungen
        // dort bitte hier mitziehen.
        $public_status = ['active', 'open', 'implementation', 'completed'];
        if (!in_array((string)$project['status'], $public_status, true)) {
            return false;
        }

        $uids = ProjektboerseGroups::memberUids($notify_gids);
        if ($already_gids) {
            $uids = array_diff($uids, ProjektboerseGroups::memberUids($already_gids));
        }
        // Wer die Zuweisung vornimmt und der Projekt-Ersteller brauchen sie nicht.
        $uids = array_diff($uids, [(int)$mybb->user['uid'], (int)$project['uid'], 0]);
        if (!$uids) {
            return false;
        }

        foreach ($uids as $uid) {
            self::send_alert(
                (int)$uid,
                'group_assigned',
                'projektboerse.php?do=view&pid=' . $pid,
                $pid,
                ['title' => $project['title']]
            );
        }

        return true;
    }

    /**
     * Alert an alle Beteiligten: Projekt abgeschlossen.
     */
    public static function send_completed_alert(int $pid): bool
    {
        global $mybb;

        if ((int)$mybb->settings['projektboerse_alert_completed'] !== 1) {
            return false;
        }

        $project = ProjektboerseProject::get($pid);
        if (!$project) {
            return false;
        }

        $recipients = array_unique(array_merge(
            [(int)$project['uid']],
            ProjektboerseProject::get_implementers($pid)
        ));

        $link = 'projektboerse.php?do=view&pid=' . $pid;

        foreach ($recipients as $uid) {
            self::send_alert($uid, 'completed', $link, $pid, ['title' => $project['title']]);
        }

        return true;
    }

    /**
     * Liefert die UIDs aller Teammitglieder (aus den Team-Gruppen).
     *
     * @return array<int,int>
     */
    private static function team_member_uids(): array
    {
        global $mybb, $db;

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

        $query = $db->simple_select('users', 'uid', $where, ['order_by' => 'uid']);

        $uids = [];
        while ($row = $db->fetch_array($query)) {
            $uids[] = (int)$row['uid'];
        }
        return array_values(array_unique($uids));
    }

    /**
     * Liefert die UIDs aller berechtigten User (Master-Allowlist
     * projektboerse_allowed_groups). Basis für die Broadcast-Alerts an "alle
     * User", die Charakter-Accounts außerhalb der Allowlist ausschließen.
     *
     * Leere Allowlist = keine Einschränkung (wie Projektboerse::is_eligible())
     * → alle User.
     *
     * @return array<int,int>
     */
    private static function eligible_member_uids(): array
    {
        global $mybb, $db;

        $allowed_groups = array_filter(array_map('intval',
            explode(',', trim((string)($mybb->settings['projektboerse_allowed_groups'] ?? '')))));

        if (empty($allowed_groups)) {
            $query = $db->simple_select('users', 'uid', '', ['order_by' => 'uid']);
        } else {
            $where_parts = [];
            foreach ($allowed_groups as $gid) {
                $gid = (int)$gid;
                $where_parts[] = "usergroup = " . $gid;
                $where_parts[] = "CONCAT(',', additionalgroups, ',') LIKE '%," . $gid . ",%'";
            }
            $where = '(' . implode(' OR ', $where_parts) . ')';
            $query = $db->simple_select('users', 'uid', $where, ['order_by' => 'uid']);
        }

        $uids = [];
        while ($row = $db->fetch_array($query)) {
            $uids[] = (int)$row['uid'];
        }
        return array_values(array_unique($uids));
    }
}

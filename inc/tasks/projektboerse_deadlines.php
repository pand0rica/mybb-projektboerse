<?php
/**
 * Projektboerse – Deadline-Task.
 *
 * Läuft alle 30 Minuten. Schiebt aktive Projekte (Feedback-Phase) nach
 * Ablauf der Feedback-Deadline auf "open" weiter und sendet bei
 * Umsetzungs-Deadline-Überschreitung höchstens einen Alert pro 24h.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

require_once MYBB_ROOT . "inc/plugins/projektboerse/classes.php";

/**
 * MyBB-Task-Einstiegsfunktion.
 *
 * @param array $task Task-Datensatz aus mybb_tasks.
 */
function task_projektboerse_deadlines($task): void
{
    global $db;

    $now = date('Y-m-d H:i:s');

    // Feedback-Phase abgelaufen → Projekte automatisch für Bewerbungen öffnen.
    $query = $db->simple_select('projektboerse_projects', 'pid',
        "status = 'active'
         AND feedback_deadline IS NOT NULL
         AND feedback_deadline < '" . $db->escape_string($now) . "'");

    while ($row = $db->fetch_array($query)) {
        $pid = (int)$row['pid'];
        ProjektboerseProject::change_status($pid, 'open');
        ProjektboerseAlert::send_deadline_alert($pid, 'Feedback-Deadline');
        add_task_log($task, "Projekt #{$pid} – Feedback-Deadline abgelaufen, Status auf 'open' gesetzt");
    }

    // Umsetzungs-Deadline überschritten → Alert (max. einmal pro 24h pro Projekt).
    $query = $db->simple_select('projektboerse_projects', 'pid',
        "status = 'implementation'
         AND implementation_deadline IS NOT NULL
         AND implementation_deadline < '" . $db->escape_string($now) . "'");

    while ($row = $db->fetch_array($query)) {
        $pid = (int)$row['pid'];

        $recent = (int)$db->fetch_field(
            $db->simple_select('projektboerse_alertlog', 'COUNT(*) AS c',
                "pid = " . $pid .
                " AND event_type = 'projektboerse_deadline'
                  AND sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)"),
            'c');

        if ($recent === 0) {
            ProjektboerseAlert::send_deadline_alert($pid, 'Umsetzungs-Deadline');
            add_task_log($task, "Projekt #{$pid} – Umsetzungs-Deadline überschritten, Alert gesendet");
        }
    }
}

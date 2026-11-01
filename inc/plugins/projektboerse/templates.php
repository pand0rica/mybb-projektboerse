<?php
/**
 * Projektboerse – Template-Loader
 *
 * Liest alle .tpl-Dateien aus templates/ und fügt sie als globale Templates
 * (sid=-1) ein. Idempotent: bestehende Templates mit gleichem Titel werden
 * zunächst gelöscht, damit Updates beim Re-Aktivieren wirken.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

/**
 * Lädt alle .tpl-Dateien als Templates in die DB.
 *
 * @param object $db MyBB-Datenbank-Objekt.
 */
function projektboerse_insert_templates($db): void
{
    $template_dir = MYBB_ROOT . "inc/plugins/projektboerse/templates/";
    if (!is_dir($template_dir)) {
        return;
    }

    $handle = opendir($template_dir);
    if (!$handle) {
        return;
    }

    while (($file = readdir($handle)) !== false) {
        if (substr($file, -4) !== '.tpl') {
            continue;
        }

        $title   = substr($file, 0, -4);
        $content = (string)file_get_contents($template_dir . $file);

        // Existierenden globalen Eintrag entfernen (kein Theme-Override betroffen).
        $db->delete_query('templates',
            "title = '" . $db->escape_string($title) . "' AND sid = -1");

        $db->insert_query('templates', [
            'title'    => $db->escape_string($title),
            'template' => $db->escape_string($content),
            'sid'      => -1,
            'version'  => '',
            'dateline' => TIME_NOW,
        ]);
    }
    closedir($handle);
}

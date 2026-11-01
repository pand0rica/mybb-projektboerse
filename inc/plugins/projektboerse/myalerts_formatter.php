<?php
/**
 * Projektboerse – MyAlerts-Formatter.
 *
 * MybbStuff_MyAlerts 2.x rendert das Bell-Dropdown nicht mit Lang-Strings
 * allein, sondern delegiert jeden AlertType an einen registrierten Formatter
 * (extends MybbStuff_MyAlerts_Formatter_AbstractFormatter). Ohne Formatter
 * bleibt der Alert leer. Diese Klasse handhabt alle projektboerse_*-Codes
 * über die zentrale Lang-Datei projektboerse_myalerts.lang.php.
 *
 * @package projektboerse
 */

if (!defined("IN_MYBB")) {
    die("Direct initialization of this file is not allowed.");
}

if (!class_exists('MybbStuff_MyAlerts_Formatter_AbstractFormatter')) {
    return; // MyAlerts nicht installiert, Datei kann ohne Effekt eingebunden werden.
}

/**
 * Universal-Formatter für alle Projektbörse-Alerts. Nimmt den Lang-String
 * "myalerts_<code>" und ersetzt Platzhalter aus extra_details.
 */
class Projektboerse_MyAlerts_Formatter extends MybbStuff_MyAlerts_Formatter_AbstractFormatter
{
    /**
     * Initialisierungs-Hook (von MyAlerts aufgerufen). Hier sicherstellen,
     * dass die Sprachstrings geladen sind.
     */
    public function init()
    {
        $this->lang->load('projektboerse_myalerts');
    }

    /**
     * Rendert einen Alert für die Bell-Dropdown-Anzeige.
     *
     * @param MybbStuff_MyAlerts_Entity_Alert $alert       Der Alert.
     * @param array                           $outputAlert Vorgefüllte Daten (from_user etc.).
     */
    public function formatAlert(MybbStuff_MyAlerts_Entity_Alert $alert, array $outputAlert)
    {
        $code     = $alert->getType()->getCode();
        $lang_key = 'myalerts_' . $code;

        // Fallback: wenn Lang-String fehlt, geben wir wenigstens den Code zurück
        // (deutet auf fehlende Sprachdatei hin – sichtbarer Hinweis statt leer).
        if (!isset($this->lang->{$lang_key}) || $this->lang->{$lang_key} === '') {
            $this->lang->load('projektboerse_myalerts');
        }
        if (!isset($this->lang->{$lang_key}) || $this->lang->{$lang_key} === '') {
            return '[' . htmlspecialchars_uni($code) . ']';
        }

        $message = (string)$this->lang->{$lang_key};

        // MyAlerts liefert den Username-Link bereits vorgefertigt in $outputAlert['from_user'].
        if (isset($outputAlert['from_user']) && $outputAlert['from_user'] !== '') {
            $message = str_replace('{from_user}', (string)$outputAlert['from_user'], $message);
        }

        // Restliche Platzhalter aus extra_details (title, link, reason, deadline_type, ...).
        // WICHTIG: Die Lang-Strings betten {title} usw. direkt in HTML ein
        // (z.B. <a href="{link}">{title}</a>). Projekttitel/Begründung sind freie
        // Nutzereingaben – ohne Escaping wäre das ein Stored-XSS im Bell-Dropdown.
        // Daher jeden Wert vor dem Einsetzen mit htmlspecialchars_uni() entschärfen
        // (für {link} unkritisch, da als href-Wert ebenfalls korrekt escaped).
        $extra = $alert->getExtraDetails();
        if (is_array($extra)) {
            foreach ($extra as $key => $value) {
                if (!is_scalar($value)) {
                    continue;
                }
                $needle = '{' . $key . '}';
                if (strpos($message, $needle) !== false) {
                    $message = str_replace($needle, htmlspecialchars_uni((string)$value), $message);
                }
            }
        }

        return $message;
    }

    /**
     * Build href that the bell-link click should jump to. Wir nehmen den
     * Link direkt aus extra_details["link"].
     */
    public function buildShowLink(MybbStuff_MyAlerts_Entity_Alert $alert)
    {
        $extra = $alert->getExtraDetails();
        if (is_array($extra) && !empty($extra['link'])) {
            return $this->mybb->settings['bburl'] . '/' . ltrim((string)$extra['link'], '/');
        }
        return $this->mybb->settings['bburl'];
    }
}

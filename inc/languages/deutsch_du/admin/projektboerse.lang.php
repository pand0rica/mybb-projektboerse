<?php
/**
 * Projektboerse – Admin-Sprachdatei (Deutsch / du)
 */

$l['projektboerse']                    = 'Projektbörse';
$l['projektboerse_description']        = 'Verwaltung der Projektbörse';
$l['projektboerse_admin_permission']   = 'Kann die Projektbörse verwalten?';

// ACP-Menü
$l['projektboerse_management']         = 'Projektbörse verwalten';
$l['projektboerse_overview']           = 'Übersicht';
$l['projektboerse_categories']         = 'Kategorien';
$l['projektboerse_types']              = 'Projekt-Typen';
$l['projektboerse_groups']             = 'Plotting-Gruppen';
$l['projektboerse_communication']      = 'Kommunikationswege';
$l['projektboerse_alerts']             = 'Alert-Einstellungen';
$l['projektboerse_statistics']         = 'Statistiken';

// Tab-Beschreibungen
$l['projektboerse_tab_desc_overview']  = 'Schnellübersicht und Schnellzugriffe.';
$l['projektboerse_tab_desc_categories'] = 'Kategorien für Projekte verwalten.';
$l['projektboerse_tab_desc_types']     = 'Projekt-Typen verwalten (Plugin, Theme, Grafik …).';
$l['projektboerse_tab_desc_communication'] = 'Kommunikationswege für Projektabstimmung verwalten.';
$l['projektboerse_tab_desc_alerts']    = 'MyAlerts-Benachrichtigungen für Projektereignisse konfigurieren.';
$l['projektboerse_tab_desc_statistics'] = 'Erweiterte Statistiken zur Projektaktivität.';

// Allgemein
$l['projektboerse_add']                = 'Hinzufügen';
$l['projektboerse_edit']               = 'Bearbeiten';
$l['projektboerse_delete']             = 'Löschen';
$l['projektboerse_save']               = 'Speichern';
$l['projektboerse_cancel']             = 'Abbrechen';
$l['projektboerse_reset']              = 'Zurücksetzen';
$l['projektboerse_actions']            = 'Aktionen';
$l['projektboerse_name']               = 'Name';
$l['projektboerse_desc']               = 'Beschreibung';
$l['projektboerse_order']              = 'Reihenfolge';
$l['projektboerse_options']            = 'Optionen';
$l['projektboerse_yes']                = 'Ja';
$l['projektboerse_no']                 = 'Nein';

// Kategorien
$l['projektboerse_add_category']       = 'Kategorie hinzufügen';
$l['projektboerse_edit_category']      = 'Kategorie bearbeiten';
$l['projektboerse_category_added']     = 'Kategorie wurde hinzugefügt.';
$l['projektboerse_category_updated']   = 'Kategorie wurde aktualisiert.';
$l['projektboerse_category_deleted']   = 'Kategorie wurde gelöscht.';
$l['projektboerse_no_categories']      = 'Keine Kategorien vorhanden.';

// Projekt-Typen
$l['projektboerse_add_type']           = 'Projekt-Typ hinzufügen';
$l['projektboerse_edit_type']          = 'Projekt-Typ bearbeiten';
$l['projektboerse_type_added']         = 'Projekt-Typ wurde hinzugefügt.';
$l['projektboerse_type_updated']       = 'Projekt-Typ wurde aktualisiert.';
$l['projektboerse_type_deleted']       = 'Projekt-Typ wurde gelöscht.';
$l['projektboerse_no_types']           = 'Keine Projekt-Typen vorhanden.';

// Plotting-Gruppen (verwaltet im Plugin plottinggroups)
$l['projektboerse_groups_managed_elsewhere'] = 'Plotting-Gruppen werden unter Werkzeuge &amp; Wartung verwaltet:';
$l['projektboerse_groups_unavailable']       = 'Das Plugin plottinggroups ist nicht aktiv – die Gruppenzuweisung ist deaktiviert.';

// Kommunikation
$l['projektboerse_add_communication']  = 'Kommunikationsweg hinzufügen';
$l['projektboerse_edit_communication'] = 'Kommunikationsweg bearbeiten';
$l['projektboerse_communication_added'] = 'Kommunikationsweg wurde hinzugefügt.';
$l['projektboerse_communication_updated'] = 'Kommunikationsweg wurde aktualisiert.';
$l['projektboerse_communication_deleted'] = 'Kommunikationsweg wurde gelöscht.';
$l['projektboerse_no_communication']   = 'Keine Kommunikationswege vorhanden.';

// Alerts
$l['projektboerse_alert_settings']     = 'Alert-Einstellungen';
$l['projektboerse_alerts_updated']     = 'Alert-Einstellungen wurden gespeichert.';
$l['projektboerse_alert_recipients']   = 'Zusätzliche Empfänger (UIDs, kommagetrennt)';
$l['projektboerse_alert_log']          = 'Alert-Log (letzte 50 Einträge)';
$l['projektboerse_alert_event']        = 'Event';
$l['projektboerse_alert_timestamp']    = 'Zeitstempel';
$l['projektboerse_alert_recipient']    = 'Empfänger';
$l['projektboerse_no_alert_logs']      = 'Keine Logs vorhanden.';

// Statistik
$l['projektboerse_stat_total']         = 'Gesamt';
$l['projektboerse_stat_pending']       = 'Wartet auf Freigabe';
$l['projektboerse_stat_active']        = 'Aktiv (Feedback)';
$l['projektboerse_stat_open']          = 'Offen für Bewerbungen';
$l['projektboerse_stat_implementation'] = 'In Umsetzung';
$l['projektboerse_stat_completed']     = 'Abgeschlossen';
$l['projektboerse_stat_rejected']      = 'Abgelehnt';
$l['projektboerse_stat_archived']      = 'Archiviert';
$l['projektboerse_total_projects']     = 'Projekte gesamt';
$l['projektboerse_total_applications'] = 'Bewerbungen gesamt';
$l['projektboerse_global_stats']       = 'Globale Statistik';
$l['projektboerse_no_data']            = 'Keine Daten vorhanden.';
$l['projektboerse_project']            = 'Projekt';

// Fehler
$l['projektboerse_error_missing_name'] = 'Bitte gib einen Namen ein.';
$l['projektboerse_error_invalid_id']   = 'Ungültige ID.';
$l['projektboerse_error_not_found']    = 'Eintrag nicht gefunden.';
$l['projektboerse_confirm_delete']     = 'Wirklich löschen?';

// Wartung: Ereignis-Historie nachtragen
$l['projektboerse_maintenance']            = 'Wartung';
$l['projektboerse_backfill_events_desc']   = 'Trägt vergangene Freigaben und Abschlüsse rückwirkend in die Ereignis-Historie der zugeordneten Plotting-Gruppen ein (mit dem historischen Datum). Kann gefahrlos mehrfach ausgeführt werden – bereits vorhandene Ereignisse werden übersprungen.';
$l['projektboerse_backfill_events_button'] = 'Ereignis-Historie nachtragen';
$l['projektboerse_backfill_confirm']       = 'Vergangene Freigaben und Abschlüsse jetzt in die Gruppen-Historie nachtragen?';
$l['projektboerse_backfill_done']          = '{1} Ereignis(se) nachgetragen, {2} bereits vorhanden (übersprungen).';
$l['projektboerse_backfill_unavailable']   = 'Das Plugin plottinggroups (Version 1.1.0 mit Ereignis-Historie) ist nicht aktiv – es kann nichts nachgetragen werden.';

// Settinggroup
$l['setting_group_projektboerse']      = 'Projektbörse';
$l['setting_group_projektboerse_desc'] = 'Einstellungen für das Projektbörse-Plugin';

// Tasks
$l['task_projektboerse_deadlines']     = 'Projektbörse: Deadline-Prüfung';
$l['task_projektboerse_deadlines_desc'] = 'Prüft automatisch, ob Deadlines überschritten wurden, und ändert entsprechend den Status.';
$l['task_projektboerse_deadlines_ran'] = 'Die Deadline-Prüfung wurde erfolgreich durchgeführt.';

<?php
/**
 * Projektboerse – MyAlerts-Sprachstrings (Deutsch / du).
 *
 * MyAlerts lädt Alert-Nachrichten über $lang->myalerts_<code>
 * und die UserCP-Settings über $lang->myalerts_setting_<code>.
 * Wir laden diese Datei im global_start, damit die Strings
 * verfügbar sind, wann immer MyAlerts das Bell-Dropdown rendert.
 */

// === Settings (UserCP → Benachrichtigungen → Abonnements) ==================
$l['myalerts_setting_projektboerse_new_project']           = 'Neues Projekt in der Projektbörse eingereicht';
$l['myalerts_setting_projektboerse_approved']              = 'Mein eingereichtes Projekt wurde freigegeben';
$l['myalerts_setting_projektboerse_published']             = 'Ein neues Projekt ist in der Projektbörse';
$l['myalerts_setting_projektboerse_open']                  = 'Ein Projekt ist für Bewerbungen offen';
$l['myalerts_setting_projektboerse_feedback']              = 'Ein Projekt ist in der Feedbackphase';
$l['myalerts_setting_projektboerse_rejected']              = 'Mein eingereichtes Projekt wurde abgelehnt';
$l['myalerts_setting_projektboerse_archived']              = 'Ein Projekt, an dem ich beteiligt bin, wurde archiviert';
$l['myalerts_setting_projektboerse_completed']             = 'Ein Projekt, an dem ich beteiligt bin, wurde abgeschlossen';
$l['myalerts_setting_projektboerse_new_application']       = 'Es gibt eine neue Bewerbung';
$l['myalerts_setting_projektboerse_application_accepted']  = 'Meine Bewerbung wurde angenommen';
$l['myalerts_setting_projektboerse_application_rejected']  = 'Meine Bewerbung wurde abgelehnt';
$l['myalerts_setting_projektboerse_new_comment']           = 'Jemand hat einen Kommentar zu einem meiner Projekte hinterlassen';
$l['myalerts_setting_projektboerse_implementer_assigned']  = 'Ich wurde als Umsetzer einem Projekt zugewiesen';
$l['myalerts_setting_projektboerse_group_assigned']        = 'Einer meiner Plotting-Gruppen wurde ein Projekt zugewiesen';
$l['myalerts_setting_projektboerse_deadline']              = 'Eine Projekt-Deadline wurde überschritten';
$l['myalerts_setting_projektboerse_feedback_ended']        = 'Die Feedbackphase meines Projekts ist beendet';

// === Alert-Nachrichten (Bell-Dropdown) =====================================
// {from_user} wird von MyAlerts mit dem formatierten Username-Link ersetzt.
// {title}, {link}, {reason}, {deadline_type} kommen aus extra_details.
$l['myalerts_projektboerse_new_project']           = '{from_user} hat ein neues Projekt eingereicht: <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_approved']              = 'Dein Projekt <a href="{link}">{title}</a> wurde freigegeben und ist nun in der Feedbackphase.';
$l['myalerts_projektboerse_published']             = 'Ein neues Projekt ist in der Projektbörse: <a href="{link}">{title}</a>. Jetzt Feedback geben!';
$l['myalerts_projektboerse_open']                  = 'Das Projekt <a href="{link}">{title}</a> ist jetzt für Bewerbungen offen.';
$l['myalerts_projektboerse_feedback']              = 'Ein neues Projekt ist in der Feedbackphase: <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_rejected']              = 'Dein Projekt <a href="{link}">{title}</a> wurde abgelehnt.';
$l['myalerts_projektboerse_archived']              = 'Das Projekt <a href="{link}">{title}</a> wurde archiviert.';
$l['myalerts_projektboerse_completed']             = 'Das Projekt <a href="{link}">{title}</a> wurde als abgeschlossen markiert.';
$l['myalerts_projektboerse_new_application']       = '{from_user} hat sich für das Projekt <a href="{link}">{title}</a> beworben.';
$l['myalerts_projektboerse_application_accepted']  = 'Deine Bewerbung für <a href="{link}">{title}</a> wurde angenommen!';
$l['myalerts_projektboerse_application_rejected']  = 'Deine Bewerbung für <a href="{link}">{title}</a> wurde leider abgelehnt.';
$l['myalerts_projektboerse_new_comment']           = '{from_user} hat einen Kommentar zu <a href="{link}">{title}</a> hinterlassen.';
$l['myalerts_projektboerse_implementer_assigned']  = 'Du wurdest dem Projekt <a href="{link}">{title}</a> als Umsetzer zugewiesen.';
$l['myalerts_projektboerse_group_assigned']        = 'Das Projekt <a href="{link}">{title}</a> wurde einer deiner Plotting-Gruppen zugewiesen.';
$l['myalerts_projektboerse_deadline']              = 'Die {deadline_type}-Deadline für <a href="{link}">{title}</a> wurde überschritten.';
$l['myalerts_projektboerse_feedback_ended']        = 'Die Feedbackphase für <a href="{link}">{title}</a> ist beendet. Das Projekt ist nun für Bewerbungen offen.';

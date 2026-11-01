<?php
/**
 * Projektboerse – MyAlerts language strings (English).
 *
 * MyAlerts loads alert messages via $lang->myalerts_<code>
 * and the UserCP settings via $lang->myalerts_setting_<code>.
 * We load this file from global_start so it's available
 * whenever MyAlerts renders the bell dropdown.
 */

// === Settings (UserCP → Alerts → Subscriptions) ============================
$l['myalerts_setting_projektboerse_new_project']           = 'A new project has been submitted to the project board';
$l['myalerts_setting_projektboerse_approved']              = 'My submitted project has been approved';
$l['myalerts_setting_projektboerse_published']             = 'A new project is now in the project board';
$l['myalerts_setting_projektboerse_open']                  = 'A project is now open for applications';
$l['myalerts_setting_projektboerse_feedback']              = 'A project entered the feedback phase';
$l['myalerts_setting_projektboerse_rejected']              = 'My submitted project has been rejected';
$l['myalerts_setting_projektboerse_archived']              = 'A project I am involved with has been archived';
$l['myalerts_setting_projektboerse_completed']             = 'A project I am involved with has been completed';
$l['myalerts_setting_projektboerse_new_application']       = 'A new application has been submitted';
$l['myalerts_setting_projektboerse_application_accepted']  = 'My application has been accepted';
$l['myalerts_setting_projektboerse_application_rejected']  = 'My application has been rejected';
$l['myalerts_setting_projektboerse_new_comment']           = 'Someone commented on one of my projects';
$l['myalerts_setting_projektboerse_implementer_assigned']  = 'I have been assigned as an implementer';
$l['myalerts_setting_projektboerse_group_assigned']        = 'A project has been assigned to one of my plotting groups';
$l['myalerts_setting_projektboerse_deadline']              = 'A project deadline has been exceeded';
$l['myalerts_setting_projektboerse_feedback_ended']        = 'The feedback phase of my project has ended';

// === Alert messages (bell dropdown) ========================================
// {from_user} is replaced by MyAlerts with the formatted username link.
// {title}, {link}, {reason}, {deadline_type} come from extra_details.
$l['myalerts_projektboerse_new_project']           = '{from_user} submitted a new project: <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_approved']              = 'Your project <a href="{link}">{title}</a> has been approved and is now in the feedback phase.';
$l['myalerts_projektboerse_published']             = 'A new project is now in the project board: <a href="{link}">{title}</a>. Share your feedback!';
$l['myalerts_projektboerse_open']                  = 'The project <a href="{link}">{title}</a> is now open for applications.';
$l['myalerts_projektboerse_feedback']              = 'A new project entered the feedback phase: <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_rejected']              = 'Your project <a href="{link}">{title}</a> has been rejected.';
$l['myalerts_projektboerse_archived']              = 'The project <a href="{link}">{title}</a> has been archived.';
$l['myalerts_projektboerse_completed']             = 'The project <a href="{link}">{title}</a> has been marked as completed.';
$l['myalerts_projektboerse_new_application']       = '{from_user} applied for the project <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_application_accepted']  = 'Your application for <a href="{link}">{title}</a> has been accepted!';
$l['myalerts_projektboerse_application_rejected']  = 'Your application for <a href="{link}">{title}</a> has been rejected.';
$l['myalerts_projektboerse_new_comment']           = '{from_user} left a comment on <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_implementer_assigned']  = 'You have been assigned as an implementer for <a href="{link}">{title}</a>.';
$l['myalerts_projektboerse_group_assigned']        = 'The project <a href="{link}">{title}</a> has been assigned to one of your plotting groups.';
$l['myalerts_projektboerse_deadline']              = 'The {deadline_type} deadline for <a href="{link}">{title}</a> has been exceeded.';
$l['myalerts_projektboerse_feedback_ended']        = 'The feedback phase for <a href="{link}">{title}</a> has ended. The project is now open for applications.';

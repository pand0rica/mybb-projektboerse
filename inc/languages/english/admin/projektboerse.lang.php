<?php
/**
 * Projektboerse – Admin language file (English)
 */

$l['projektboerse']                    = 'Project Board';
$l['projektboerse_description']        = 'Project board administration';
$l['projektboerse_admin_permission']   = 'Can manage the project board?';

// ACP menu
$l['projektboerse_management']         = 'Manage project board';
$l['projektboerse_overview']           = 'Overview';
$l['projektboerse_categories']         = 'Categories';
$l['projektboerse_types']              = 'Project types';
$l['projektboerse_groups']             = 'Plotting groups';
$l['projektboerse_communication']      = 'Communication channels';
$l['projektboerse_alerts']             = 'Alert settings';
$l['projektboerse_statistics']         = 'Statistics';

// Tab descriptions
$l['projektboerse_tab_desc_overview']  = 'Quick overview and shortcuts.';
$l['projektboerse_tab_desc_categories'] = 'Manage project categories.';
$l['projektboerse_tab_desc_types']     = 'Manage project types (plugin, theme, graphics …).';
$l['projektboerse_tab_desc_communication'] = 'Manage communication channels for projects.';
$l['projektboerse_tab_desc_alerts']    = 'Configure MyAlerts notifications for project events.';
$l['projektboerse_tab_desc_statistics'] = 'Extended statistics about project activity.';

// Generic
$l['projektboerse_add']                = 'Add';
$l['projektboerse_edit']               = 'Edit';
$l['projektboerse_delete']             = 'Delete';
$l['projektboerse_save']               = 'Save';
$l['projektboerse_cancel']             = 'Cancel';
$l['projektboerse_reset']              = 'Reset';
$l['projektboerse_actions']            = 'Actions';
$l['projektboerse_name']               = 'Name';
$l['projektboerse_desc']               = 'Description';
$l['projektboerse_order']              = 'Order';
$l['projektboerse_options']            = 'Options';
$l['projektboerse_yes']                = 'Yes';
$l['projektboerse_no']                 = 'No';

// Categories
$l['projektboerse_add_category']       = 'Add category';
$l['projektboerse_edit_category']      = 'Edit category';
$l['projektboerse_category_added']     = 'Category has been added.';
$l['projektboerse_category_updated']   = 'Category has been updated.';
$l['projektboerse_category_deleted']   = 'Category has been deleted.';
$l['projektboerse_no_categories']      = 'No categories available.';

// Project types
$l['projektboerse_add_type']           = 'Add project type';
$l['projektboerse_edit_type']          = 'Edit project type';
$l['projektboerse_type_added']         = 'Project type has been added.';
$l['projektboerse_type_updated']       = 'Project type has been updated.';
$l['projektboerse_type_deleted']       = 'Project type has been deleted.';
$l['projektboerse_no_types']           = 'No project types available.';

// Plotting groups (managed by the plottinggroups plugin)
$l['projektboerse_groups_managed_elsewhere'] = 'Plotting groups are managed under Tools &amp; Maintenance:';
$l['projektboerse_groups_unavailable']       = 'The plottinggroups plugin is not active – group assignment is disabled.';

// Communication
$l['projektboerse_add_communication']  = 'Add communication channel';
$l['projektboerse_edit_communication'] = 'Edit communication channel';
$l['projektboerse_communication_added'] = 'Communication channel has been added.';
$l['projektboerse_communication_updated'] = 'Communication channel has been updated.';
$l['projektboerse_communication_deleted'] = 'Communication channel has been deleted.';
$l['projektboerse_no_communication']   = 'No communication channels available.';

// Alerts
$l['projektboerse_alert_settings']     = 'Alert settings';
$l['projektboerse_alerts_updated']     = 'Alert settings have been saved.';
$l['projektboerse_alert_recipients']   = 'Additional recipients (UIDs, comma-separated)';
$l['projektboerse_alert_log']          = 'Alert log (last 50 entries)';
$l['projektboerse_alert_event']        = 'Event';
$l['projektboerse_alert_timestamp']    = 'Timestamp';
$l['projektboerse_alert_recipient']    = 'Recipient';
$l['projektboerse_no_alert_logs']      = 'No log entries available.';

// Statistics
$l['projektboerse_stat_total']         = 'Total';
$l['projektboerse_stat_pending']       = 'Waiting for approval';
$l['projektboerse_stat_active']        = 'Active (feedback)';
$l['projektboerse_stat_open']          = 'Open for applications';
$l['projektboerse_stat_implementation'] = 'In implementation';
$l['projektboerse_stat_completed']     = 'Completed';
$l['projektboerse_stat_rejected']      = 'Rejected';
$l['projektboerse_stat_archived']      = 'Archived';
$l['projektboerse_total_projects']     = 'Total projects';
$l['projektboerse_total_applications'] = 'Total applications';
$l['projektboerse_global_stats']       = 'Global statistics';
$l['projektboerse_no_data']            = 'No data available.';
$l['projektboerse_project']            = 'Project';

// Errors
$l['projektboerse_error_missing_name'] = 'Please provide a name.';
$l['projektboerse_error_invalid_id']   = 'Invalid ID.';
$l['projektboerse_error_not_found']    = 'Entry not found.';
$l['projektboerse_confirm_delete']     = 'Really delete?';

// Maintenance: backfill event history
$l['projektboerse_maintenance']            = 'Maintenance';
$l['projektboerse_backfill_events_desc']   = 'Retroactively adds past approvals and completions to the event history of the assigned plotting groups (using the historical date). Safe to run multiple times – events that already exist are skipped.';
$l['projektboerse_backfill_events_button'] = 'Backfill event history';
$l['projektboerse_backfill_confirm']       = 'Backfill past approvals and completions into the group history now?';
$l['projektboerse_backfill_done']          = 'Backfilled {1} event(s), {2} already existed (skipped).';
$l['projektboerse_backfill_unavailable']   = 'The plottinggroups plugin (version 1.1.0 with event history) is not active – nothing can be backfilled.';

// Setting group
$l['setting_group_projektboerse']      = 'Project Board';
$l['setting_group_projektboerse_desc'] = 'Settings for the project board plugin';

// Tasks
$l['task_projektboerse_deadlines']     = 'Project Board: Deadline check';
$l['task_projektboerse_deadlines_desc'] = 'Automatically checks whether deadlines have been exceeded and updates the status.';
$l['task_projektboerse_deadlines_ran'] = 'The deadline check ran successfully.';

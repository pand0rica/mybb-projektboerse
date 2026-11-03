# Changelog

All notable changes to this plugin are documented here.
Format: [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
Versioning: [SemVer](https://semver.org/).

## [Unreleased]

### Fixed
- **Settings were lost on every deactivate/activate.** `projektboerse_deactivate()` deleted
  all settings and the settinggroup, so every update reset the configuration to the
  defaults. Settings are now removed only in `projektboerse_uninstall()` (dev kit
  `debugging.md` #21).
- Stylesheet query no longer quotes the integer `sid`.

### Security
- ACP CSRF protection uses MyBB's native mechanism (dev kit `debugging.md` #23): write
  handlers only act on POST and `admin/index.php` validates the `my_post_key` itself –
  no own key check or helper.
- JavaScript confirm texts (ACP and ModCP) are built with `json_encode()` instead of
  `addslashes()`.

### Changed
- **All actions are real buttons** (dev kit `debugging.md` #22): the ModCP project and
  application columns no longer use text links with `|` separators or links that submit a
  hidden form via JavaScript. Navigation (view, edit, approve form, reject form, delete form,
  applications, “New project”) uses GET forms, state changes (open applications, complete,
  reopen, archive, accept, reject) POST forms with `my_post_key` – all as
  `<input type="submit" class="button">`. “Cancel” on the reject and delete forms is a
  button as well. The decorative “+” before “New project” is gone (pseudo-elements do not
  work on input buttons).
- German texts: “Ersteller” → “Eingereicht von”, “Umsetzer” → gender-neutral wording.

## [3.2.1] – 2026-11-01

### Fixed
- **The deadline task blocked all other scheduled tasks.** It was registered with
  minute `*/30`, a syntax MyBB's scheduler does not support: `fetch_next_run()`
  failed (warning on PHP 7, fatal `TypeError` on PHP 8), `nextrun` stayed 0 and the
  task stayed first in the queue forever. It is now registered with `0,30`.
  Existing installations are corrected when the plugin is re-activated.

## [3.2.0] – 2026-10-16

Board-wide broadcast alerts so the whole community notices new projects.

### Added
- **Broadcast alert when a project is published** (`pending → active`): every
  eligible user – not just the creator – is now notified that a new project is
  in the project board and open for feedback. New alert
  `projektboerse_published`, toggle `projektboerse_alert_published`.
- **Broadcast alert when applications open** (project enters the application
  phase, i.e. any transition into `open`, including a reopen from
  `implementation`): every eligible user is notified that the project is now
  open for applications. New alert `projektboerse_open`, toggle
  `projektboerse_alert_open`.
- Recipients for both broadcasts are resolved via the existing master allowlist
  (`projektboerse_allowed_groups`), so pure character accounts outside the
  allowlist receive nothing. An empty allowlist means no restriction (all users),
  matching `Projektboerse::is_eligible()`. New helper
  `ProjektboerseAlert::eligible_member_uids()`.
- The project creator is skipped in both broadcasts – they still receive their
  personal `approved` / `feedback_ended` alert. Both broadcasts can be muted
  per user in the UserCP like every other MyAlerts alert.

## [3.1.0] – 2026-08-15

Projects now feed the plotting groups' event history.

### Added
- When a project is **approved** (`pending → active`) or **completed**, the event
  is written to the *latest events* history of every plotting group the project
  is assigned to. It appears as *"Project approved"* / *"Project completed"*,
  linking back to the project. Implemented via `Plottinggroups_Api::logEvent()`.
- New method `ProjektboerseGroups::logEvent()` wraps the API call; it is a no-op
  when plottinggroups is not active. It additionally guards with
  `method_exists('Plottinggroups_Api', 'logEvent')`, because the events feature
  only exists in plottinggroups 1.1.0+ – on an older install the call would
  otherwise fatal (undefined method) right after the status change was committed.
- Event type labels (`plottinggroups_event_type_projektboerse_approved` /
  `_completed`) live in the Projektbörse language files. They are loaded on every
  page via `global_start`, so the plottinggroups feed shows a readable label
  without any change to the plottinggroups plugin.
- **Maintenance action to backfill the event history** (ACP → Configuration →
  Projektbörse → overview → *Maintenance*). Reconstructs approval/completion
  events for projects that were already approved/completed before this feature
  existed, using their historical `approved_at`/`completed_at` timestamps.
  Idempotent via `ProjektboerseGroups::eventExists()` – safe to run repeatedly
  and it never duplicates events already written live.

## [3.0.0] – 2026-07-19

Tags are replaced by plotting groups from the separate `plottinggroups` plugin.

### Removed
- **BREAKING: the Tags feature is gone.** Tables `projektboerse_tags` and
  `projektboerse_project_tags` are dropped on activation. **Existing tag
  assignments are permanently lost – there is no migration.** Tags and plotting
  groups are different concepts, so nothing could be mapped automatically.
- The ACP tab **Tags** including its CRUD handler and all tag language strings.
  Groups are administered in the plottinggroups plugin instead
  (*Tools & Maintenance → Plotting Groups*); a second place to manage the same
  data would have been a mistake.

### Added
- Projects can be assigned to **plotting groups**, via checkboxes in the project
  form (frontend submit/edit and ModCP). Backed by the new table
  `projektboerse_project_groups` (`pid` ↔ `gid`, no foreign key – the target
  table belongs to another, optional plugin).
- New alert `projektboerse_group_assigned`: members of a group are notified when
  a project is assigned to it. Sent on approval (`pending → active`) and, on
  later edits, only for **newly added** groups, so nobody is notified twice.
  Toggle: `projektboerse_alert_group_assigned`.
- Assigned groups are shown as linked pills in the project detail view **and** in
  the project tile. The tile previously output an empty placeholder that was
  never filled – it now works.
- Bridge class `ProjektboerseGroups` wrapping `Plottinggroups_Api`. plottinggroups
  stays an optional dependency: without it the group section disappears, pills
  render as plain labels, and **existing assignments survive editing** – the
  submitted-gid normaliser distinguishes "plugin unavailable" from "user cleared
  the selection", and the form only sends its marker when the section rendered.

### Changed
- Alert recipients for the new alert still pass through the `allowed_groups`
  master allowlist. In an RPG forum plotting groups are mostly character
  accounts, which the allowlist deliberately excludes – if a group has no
  eligible members, no alert is sent.
- `projektboerse_activate()` now runs a schema upgrade step, because
  `install()` only ever runs once.

## [2.2.0] – 2026-06-17

Multiple applicants can now be accepted for a single project.

### Changed
- Accepting an application no longer auto-rejects the remaining applications.
  Each accepted applicant is added as an implementer, and a project can now have
  any number of implementers. Other applications stay `submitted` and can be
  accepted or rejected individually by the team.
- The project moves to `implementation` on the first accepted applicant and
  stays there for further acceptances (no duplicate status-change alerts).
- ModCP project rows in the `implementation` stage now expose the **Applications**
  link, so the team can keep accepting applicants after the project is already
  in implementation.
- The application list on the project detail page is now also shown to the team
  and submitter during the `implementation` stage (previously only `open`).
- After accepting/rejecting an application the team is redirected back to that
  project's application list instead of the ModCP index.

### Added
- Application status badge (Pending / Accepted / Rejected) in the ModCP and
  frontend application lists; accept/reject buttons are only shown for pending
  applications, processed ones show their review date.
- The statistics "currently being worked on" list now shows the implementer(s)
  assigned to each in-progress project (supports multiple implementers per
  project).

### Changed (statistics)
- Renamed the statistics section heading from "Who is working on what?" to
  "What is currently being worked on" (DE: "Woran wird gerade gearbeitet").

### Changed (project board)
- The main project board (`projektboerse.php`) is now grouped into one section
  per category, ordered by the category display order. Each section shows its
  project count; projects whose category was removed appear under an
  "Uncategorized" section. The global pagination on this view was dropped in
  favour of the category structure (the status-filtered views keep pagination).

## [2.1.0] – 2026-06-05

Adds an opt-in HTML mode, a master allow-list for eligible groups and proper
MyAlerts 2.x integration. Also completes the status workflow in the ModCP and
hardens the alert output.

### Added
- Master allow-list setting `projektboerse_allowed_groups`: only members of the
  selected groups may interact with the project board or receive alerts
  (filters out RPG character accounts). Empty = no restriction (backwards
  compatible with 2.0.0).
- Setting `projektboerse_allow_html`: optional HTML in project content, run
  through a DOMDocument allow-list sanitizer (script tags, event handlers and
  `javascript:`/`data:`/`vbscript:` URLs are stripped).
- Proper MyAlerts 2.x integration: alert types are registered via
  `MybbStuff_MyAlerts_AlertTypeManager`, and a universal
  `Projektboerse_MyAlerts_Formatter` renders all `projektboerse_*` alerts.
- ModCP buttons to **complete**, **archive** and **reopen** projects in the
  `implementation` stage (and archive `completed` projects) — the handlers and
  class methods existed but had no UI trigger, so the workflow dead-ended at
  `implementation`. Each button posts via a CSRF-protected mini form.

### Fixed
- ACP success messages for types and communication channels used the wrong
  language key (`category_added`/`category_updated` without the
  `projektboerse_` prefix), so they always fell back to a hard-coded German
  string. They now use the correct per-table keys.
- Red-alert strings on the forum index ("X projects awaiting approval") were
  hard-coded German; moved to the language files with singular/plural keys.
- Admin permission label is now read from the admin language file instead of a
  hard-coded string.
- Project list excerpts no longer append an ellipsis when the description is
  shorter than the cut-off.
- Inline `style="…"` attributes replaced with CSS classes
  (`projektboerse.css`).

### Security
- Stored XSS in the MyAlerts bell dropdown: the formatter now escapes all
  `extra_details` values (project title, reason, …) with `htmlspecialchars_uni()`
  before substituting them into the HTML alert templates.

## [2.0.0] – 2026-05-26

Full rework of the original 1.0.0 plugin. Same feature surface, but
architecture, security and lifecycle were rewritten from the ground up.

### Added
- ACP module (`config-projektboerse`) now actually wired up via
  `admin_config_menu` / `admin_config_action_handler` / `admin_config_permissions`.
- Templates are now stored as individual `.tpl` files under
  `inc/plugins/projektboerse/templates/` instead of inline PHP heredocs.
- Stylesheet is now an external file (`assets/projektboerse.css`) and is
  installed into every theme via `update_theme_stylesheet_list()`.
- Full English language files (`english/projektboerse.lang.php` +
  `english/admin/projektboerse.lang.php`).
- `CHANGELOG.md` and updated `CLAUDE.md` with the "What NOT to change" list.

### Changed
- `inc/plugins/projektboerse.php` registers all hooks on top level and follows
  the canonical lifecycle (`info`, `install`, `is_installed`, `activate`,
  `deactivate`, `uninstall`).
- All user input goes through `$mybb->get_input(...)` with explicit type hints.
- Schema-bound names cleaned up: `application_text` is used consistently
  everywhere (Form, code, DB) instead of mixing it with `motivation`.
- MyAlerts integration is now a best-effort DB insert (the previously called
  function `myalerts_create_alert()` does not exist); without MyAlerts, alerts
  are still logged to `projektboerse_alertlog`.
- All ACP links point at `projektboerse.php` instead of the non-existent
  `misc.php?action=projektboerse`.

### Fixed
- ACP hooks were defined inside `projektboerse_admin()` but that function was
  never called, so the entire ACP module was unreachable.
- `deactivate()` did not remove templates, settings, settinggroup or
  stylesheets – now mirrors `activate()` exactly.
- Stylesheet was inserted only in `tid=1` and never registered via
  `update_theme_stylesheet_list()`.
- Task insert did not set `nextrun` via `fetch_next_run()` → stale schedule.
- `can_edit_project()` was called with swapped arguments (`$uid, $project`)
  in the view template.
- ACP log view referenced wrong columns (`timestamp`/`uid` instead of
  `sent_at`/`recipient_uid`).
- Re-activation crashed because settings were inserted without an existence
  check; insert is now idempotent with an upgrade path.
- `modcp_applications()` was rendering HTML with `echo … exit;` instead of
  using a template.
- `verify_post_check()` and CSRF tokens added to every state-changing
  ModCP action.
- All raw queries now use `TABLE_PREFIX` consistently (no hard-coded `mybb_`).
- Multi-row queries that previously read only one record (`fetch_array`
  without a `while` loop) now iterate properly.
- All `global` declarations live at the very top of their function.

### Security
- POST + `verify_post_check()` is now required for every state-changing action
  (approve, reject, accept/reject application, edit/update, delete, complete,
  archive, reopen, add_comment, submit_application).
- All output goes through `htmlspecialchars_uni()`; all SQL through
  `$db->escape_string()` / integer casts.
- Permission checks (`error_no_permission()`) are enforced before every
  mutation, both in ModCP and on the frontend.

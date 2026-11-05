# CLAUDE.md – Projektbörse (Plugin-spezifisch)

Plugin-spezifische Anweisungen. Globale Anweisungen unter `~/.claude/CLAUDE.md`
und die Skill `mybb-plugin-dev` (Boilerplate/Conventions/Debugging) haben Vorrang.

## Plugin-Identität

- **Name:** Projektbörse
- **Codename:** `projektboerse`
- **Version:** 2.1.0 (Rework des Original-Plugins 1.0.0)
- **Ziel-MyBB:** 1.8.x
- **Ziel-PHP:** 7.4 + 8.x
- **Architektur:** prozedural-modular
  (`inc/plugins/projektboerse/{classes,database,hooks,templates}.php`)
- **Frontend-Einstieg:** `projektboerse.php` im MyBB-Root.

## Zweck

Vollständige Projektverwaltung für RPG-Foren:
**Einreichung → Team-Freigabe → Feedback-Phase → Bewerbungen → Umsetzung → Abschluss.**
Mit Alerts (MyAlerts/DB-Log), Deadlines (Task), ACP für Stamm-Tabellen.

## Tabellen (9, alle Präfix `projektboerse_`)

| Tabelle | Zweck |
|---|---|
| `projektboerse_projects` | Haupttabelle (Status, Deadlines, Owner) |
| `projektboerse_applications` | Bewerbungen (`UNIQUE pid,uid`) |
| `projektboerse_comments` | Feedback-Kommentare |
| `projektboerse_project_groups` | Projekt ↔ Plotting-Gruppe (n:m, `gid` aus plottinggroups, kein FK) |
| `projektboerse_types` | Projekt-Typen (Stamm) |
| `projektboerse_categories` | Kategorien (Stamm) |
| `projektboerse_communication` | Kommunikationswege (Stamm) |
| `projektboerse_implementers` | Umsetzer (n:m) |
| `projektboerse_alertlog` | Alert-Log (Debug/Nachvollziehbarkeit) |

Status-ENUM in `projects.status`:
`pending, active, open, implementation, completed, rejected, archived, inactive`.

## Workflow

1. **pending** – Ersteller reicht ein (nur Titel/Beschreibung/Kategorie nötig).
2. **active** – Team gibt frei und ergänzt Typ/Kommunikation/Deadlines (Feedback-Phase).
3. **open** – Feedback-Deadline abgelaufen (Task) oder Team setzt manuell → Bewerbungen möglich.
4. **implementation** – Team nimmt eine oder mehrere Bewerbungen an; jede:r Angenommene wird Implementer. Andere Bewerbungen bleiben offen (kein Auto-Reject) und können einzeln angenommen/abgelehnt werden. Status wechselt beim ersten angenommenen Bewerber.
5. **completed** / **archived** / **rejected** / **reopen → open** – Folge-Status.

## Hooks (alle top-level registriert)

- `global_start` → `projektboerse_handle_global` – Sprachdatei laden.
- `index_start` → `projektboerse_handle_index` – Red-Alert für Team.
- `modcp_start` → `projektboerse_handle_modcp` – ModCP-Nav + Aktions-Dispatcher.
- `admin_config_menu` / `admin_config_action_handler` / `admin_config_permissions`
  – ACP-Modul `config-projektboerse`.

## Settings (settinggroup `projektboerse`)

20 Settings: `_enabled`, Gruppen-Listen (`submit/apply/team`), 11 `_alert_*`-Toggles,
2 zusätzliche Empfänger-Listen, 2 Default-Deadlines (Tage), `_per_page`.

## Task

`inc/tasks/projektboerse_deadlines.php` läuft alle 30 Minuten:
- `active`-Projekte mit abgelaufener Feedback-Deadline → `open`.
- `implementation`-Projekte mit überschrittener Deadline → Alert (max. 1×/24h).

## Berechtigungs-Helper (Klasse `Projektboerse`)

- `is_team_member`, `can_submit`, `can_apply`, `can_edit_project($pid,$uid)`,
  `can_delete_project`, `can_manage_applications`, `can_change_status`,
  `can_view_project($project,$uid)`.

## MyAlerts-Integration

Best-Effort über direkten Insert in `mybb_alerts` (kein API-Aufruf, weil MyAlerts
keine stabile öffentliche API hat). Bricht stillschweigend ab, wenn die Tabelle
nicht existiert. Jeder Versand wird zusätzlich in `projektboerse_alertlog`
geloggt (auch ohne MyAlerts brauchbar).

## What NOT to change

- **DATETIME-Felder** statt INT(11) Unix-Time: bewusste Entscheidung, weil das
  Original-Schema diesen Typ nutzte. Migration wäre destruktiv. Lesen mit
  `strtotime(...)`, schreiben mit `date('Y-m-d H:i:s', ...)`.
- **utf8mb4_unicode_ci** statt utf8_unicode_ci: bewusste Entscheidung, weil
  Projektbeschreibungen Emojis/Sonderzeichen enthalten können.
- **modulares Layout** beibehalten: erleichtert Wartung trotz Größe.
- **`ProjektboerseApplication::create($pid, $application_text)`** – Signatur
  (zwei Skalare statt Array); im Original war das genau hier ein Bug.
- **`can_edit_project($pid, $uid)`** – Reihenfolge der Parameter (im Original
  waren sie vertauscht).

## Behobene Bugs des Originals (Stand 1.0.0 → 2.0.0)

1. ACP-Hooks waren registriert, aber `projektboerse_admin()` wurde nie
   aufgerufen → ACP-Modul war komplett tot.
2. `deactivate()` entfernte weder Templates noch Settings noch Stylesheet.
3. Stylesheet nur in `tid=1`, ohne `update_theme_stylesheet_list()`.
4. `update_task_run()` / korrekter `nextrun` fehlten → Stale Schedule.
5. Direkter `$mybb->input[...]`-Zugriff überall → jetzt durchgehend `get_input()`.
6. Schema-Inkonsistenz `application_text` vs. `motivation` (Form/Code).
7. `can_edit_project($uid, $project)` mit vertauschten Parametern.
8. `myalerts_create_alert()` (existiert nicht) → DB-Insert mit Best-Effort.
9. ACP-Log-Anzeige nutzte falsche Spalten (`timestamp`/`uid` statt `sent_at`/`recipient_uid`).
10. ACP-Links auf `misc.php?action=projektboerse` (existiert nicht) → korrekt auf
    `projektboerse.php`.
11. Settings ohne Existenz-Prüfung → Re-Aktivierung crashte.
12. CSS war in PHP-Heredoc eingebettet → jetzt eigene `assets/projektboerse.css`.
13. Templates inline im PHP → jetzt `.tpl`-Dateien.
14. `modcp_applications()` baute HTML mit `echo`/`exit` → jetzt Templates.
15. Hartcodierte deutsche Strings → Sprachdateien (en + deutsch_du).

## Abhängigkeiten

- **Keine harten Abhängigkeiten.**
- **Optional:** MyAlerts (Versand in `mybb_alerts`); ohne MyAlerts werden Alerts
  nur in `projektboerse_alertlog` geschrieben.
- **Optional:** `plottinggroups` – liefert die Plotting-Gruppen, denen Projekte
  zugeordnet werden. Zugriff ausschließlich über die Brücke
  `ProjektboerseGroups` (`inc/plugins/projektboerse/groups.php`), die
  `Plottinggroups_Api` kapselt und ohne das Plugin neutrale Werte (`[]`/`''`)
  liefert. Wichtig: `normalizeSubmittedGids()` gibt `null` zurück wenn die
  Brücke fehlt – dann bleiben bestehende Zuordnungen beim Speichern unangetastet
  (sonst würde ein Edit bei deaktiviertem plottinggroups alles löschen).

## Test-Reihenfolge

Tests laufen auf dem Test-Forum (`<testforum>`), nicht lokal.

1. Plugin per SFTP auf das Test-Forum hochladen.
2. Aktivieren → Settings, Templates, Stylesheet, Task prüfen.
3. Frontend: Projekt einreichen (als Nicht-Team).
4. ModCP: Freigeben → active → open → Bewerbung annehmen → implementation → complete.
5. Deaktivieren → Templates, Styles, Task und Alert-Typen müssen weg sein; Settings bleiben
   erhalten (Dev-Kit `debugging.md` #21).
6. Deinstallieren → alle Tabellen und Settings müssen weg sein.

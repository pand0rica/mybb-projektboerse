<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_guide}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <div class="tborder">
        <div class="thead projektboerse_thead"><strong>{$lang->projektboerse}</strong>{$submit_link}</div>
        <div class="trow1">{$projektboerse_nav}</div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_guide_intro_title}</strong></div>
        <div class="trow1 projektboerse_guide_text">{$lang->projektboerse_guide_intro}</div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_guide_phases_title}</strong></div>
        <div class="trow1 projektboerse_guide_text">{$lang->projektboerse_guide_phases_intro}</div>

        <div class="trow2 projektboerse_guide_phase">
            <span class="projektboerse_guide_step">1</span>
            <div class="projektboerse_guide_phase_body">
                <strong class="projekt_status projekt_status_pending">{$lang->projektboerse_guide_phase_pending_title}</strong>
                <p>{$lang->projektboerse_guide_phase_pending_desc}</p>
            </div>
        </div>

        <div class="trow1 projektboerse_guide_phase">
            <span class="projektboerse_guide_step">2</span>
            <div class="projektboerse_guide_phase_body">
                <strong class="projekt_status projekt_status_active">{$lang->projektboerse_guide_phase_active_title}</strong>
                <p>{$lang->projektboerse_guide_phase_active_desc}</p>
            </div>
        </div>

        <div class="trow2 projektboerse_guide_phase">
            <span class="projektboerse_guide_step">3</span>
            <div class="projektboerse_guide_phase_body">
                <strong class="projekt_status projekt_status_open">{$lang->projektboerse_guide_phase_open_title}</strong>
                <p>{$lang->projektboerse_guide_phase_open_desc}</p>
            </div>
        </div>

        <div class="trow1 projektboerse_guide_phase">
            <span class="projektboerse_guide_step">4</span>
            <div class="projektboerse_guide_phase_body">
                <strong class="projekt_status projekt_status_implementation">{$lang->projektboerse_guide_phase_implementation_title}</strong>
                <p>{$lang->projektboerse_guide_phase_implementation_desc}</p>
            </div>
        </div>

        <div class="trow2 projektboerse_guide_phase">
            <span class="projektboerse_guide_step">5</span>
            <div class="projektboerse_guide_phase_body">
                <strong class="projekt_status projekt_status_completed">{$lang->projektboerse_guide_phase_completed_title}</strong>
                <p>{$lang->projektboerse_guide_phase_completed_desc}</p>
            </div>
        </div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_guide_roles_title}</strong></div>
        <div class="trow1 projektboerse_guide_text">
            <p><strong>{$lang->projektboerse_guide_role_submitter_title}</strong> {$lang->projektboerse_guide_role_submitter_desc}</p>
            <p><strong>{$lang->projektboerse_guide_role_applicant_title}</strong> {$lang->projektboerse_guide_role_applicant_desc}</p>
            <p><strong>{$lang->projektboerse_guide_role_team_title}</strong> {$lang->projektboerse_guide_role_team_desc}</p>
        </div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_guide_tips_title}</strong></div>
        <div class="trow1 projektboerse_guide_text">{$lang->projektboerse_guide_tips}</div>
    </div>
</div>
{$footer}
</body>
</html>

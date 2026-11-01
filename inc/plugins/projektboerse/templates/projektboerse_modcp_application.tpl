<div class="trow1 bewerbung_box">
    <div class="bewerbung_header">
        <span>
            <strong>{$applicant_formatted}</strong>
            <span class="smalltext"> · {$lang->projektboerse_project}:
                <a href="projektboerse.php?do=view&amp;pid={$application['pid']}">{$project_title}</a>
            </span>
            {$application_status_badge}
        </span>
        <span class="smalltext">{$submitted_date}</span>
    </div>
    <div class="bewerbung_body">{$application_text_parsed}</div>
    <div class="bewerbung_actions">{$application_actions}</div>
</div>

<div class="trow1 projekt_box">
    <div class="projekt_header">
        <span class="projekt_header_title">
            <a href="projektboerse.php?do=view&amp;pid={$project['pid']}">{$project['title']}</a>
        </span>
        <span class="projekt_status {$status_class}">{$status_name}</span>
    </div>
    <div class="projekt_meta smalltext">
        {$lang->projektboerse_owner}: {$owner_formatted}<span class="projekt_meta_sep">·</span>
        {$lang->projektboerse_created}: {$created_date}
    </div>
    <div class="projekt_actions projektboerse_actions projektboerse_actions_left">{$action_buttons}</div>
    {$deadline_warning}
</div>

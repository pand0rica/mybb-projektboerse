<html>
<head>
<title>{$mybb->settings['bbname']} - {$project['title']}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <div class="tborder">
        <div class="thead">
            <span class="projekt_status projekt_status_float {$status_class}">{$status_name}</span>
            <strong>{$project['title']}</strong>
        </div>

        <div class="trow1 projektboerse_field">
            <dfn>{$lang->projektboerse_description}</dfn>
            <div class="projekt_description">{$project['description']}</div>
        </div>

        <div class="trow2 projektboerse_field">
            <table class="projektboerse_meta_grid">
                <tr>
                    <td>
                        <dfn>{$lang->projektboerse_owner}</dfn>
                        <div>{$owner_formatted}</div>
                    </td>
                    <td>
                        <dfn>{$lang->projektboerse_contact}</dfn>
                        <div>{$contact_formatted}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <dfn>{$lang->projektboerse_category}</dfn>
                        <div>{$category_name}</div>
                    </td>
                    <td>
                        <dfn>{$lang->projektboerse_type}</dfn>
                        <div>{$type_name}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <dfn>{$lang->projektboerse_feedback_deadline}</dfn>
                        <div>{$feedback_deadline}</div>
                    </td>
                    <td>
                        <dfn>{$lang->projektboerse_implementation_deadline}</dfn>
                        <div>{$implementation_deadline}</div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <dfn>{$lang->projektboerse_communication}</dfn>
                        <div>{$communication_name}</div>
                    </td>
                    <td>
                        <dfn>{$lang->projektboerse_reward}</dfn>
                        <div>{$project['reward']}</div>
                    </td>
                </tr>
            </table>
        </div>

        {$wiki_links_section}
        {$groups_section}
        {$implementers_section}
        {$deadline_warning_display}

        <div class="trow1 projektboerse_actions">{$action_buttons}</div>
    </div>

    {$comments_section}
    {$applications_section}
    {$application_form_section}
</div>
{$footer}
</body>
</html>

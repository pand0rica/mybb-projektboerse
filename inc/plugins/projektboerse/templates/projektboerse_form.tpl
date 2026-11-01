<html>
<head>
<title>{$mybb->settings['bbname']} - {$form_title}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <form action="{$form_action_url}" method="post">
        <input type="hidden" name="do" value="{$form_action}" />
        <input type="hidden" name="pid" value="{$project['pid']}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

        <div class="tborder">
            <div class="thead"><strong>{$form_title}</strong></div>

            <div class="trow1 projektboerse_field">
                <label for="projektboerse_title">{$lang->projektboerse_title}</label>
                <input type="text" id="projektboerse_title" name="title" value="{$project['title']}" required />
            </div>

            <div class="trow2 projektboerse_field">
                <label for="projektboerse_description">{$lang->projektboerse_description}</label>
                <textarea id="projektboerse_description" name="description" rows="10" required>{$project['description']}</textarea>
            </div>

            <div class="trow1 projektboerse_field">
                <label>{$lang->projektboerse_category}</label>
                {$category_select}
            </div>

            {$type_field}
            {$reward_field}
            {$communication_field}
            {$contact_person_field}
            {$feedback_deadline_field}
            {$implementation_deadline_field}
            {$wiki_links_field}
            {$groups_field}

            <div class="trow1 projektboerse_actions projektboerse_actions_center">
                <input type="submit" value="{$submit_button_text}" class="button" />
            </div>
        </div>
    </form>
</div>
{$footer}
</body>
</html>

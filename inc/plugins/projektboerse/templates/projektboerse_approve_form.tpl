<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_approve}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <form action="modcp.php?action=projektboerse" method="post">
        <input type="hidden" name="do" value="submit_approve" />
        <input type="hidden" name="pid" value="{$project['pid']}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

        <div class="tborder">
            <div class="thead"><strong>{$lang->projektboerse_approve}: {$project['title']}</strong></div>

            <div class="trow1 projektboerse_field">
                <label for="approve_title">{$lang->projektboerse_title}<span class="projektboerse_required">*</span></label>
                <input type="text" id="approve_title" name="title" value="{$project['title']}" required />
            </div>

            <div class="trow2 projektboerse_field">
                <label for="approve_description">{$lang->projektboerse_description}<span class="projektboerse_required">*</span></label>
                <textarea id="approve_description" name="description" rows="10" required>{$project['description']}</textarea>
            </div>

            <div class="trow1 projektboerse_field">
                <label>{$lang->projektboerse_category}<span class="projektboerse_required">*</span></label>
                {$category_select}
            </div>

            <div class="trow2 projektboerse_field">
                <label>{$lang->projektboerse_type}<span class="projektboerse_required">*</span></label>
                {$type_select}
            </div>

            <div class="trow1 projektboerse_field">
                <label for="approve_reward">{$lang->projektboerse_reward}</label>
                <input type="text" id="approve_reward" name="reward" value="{$project['reward']}" />
            </div>

            <div class="trow2 projektboerse_field">
                <label>{$lang->projektboerse_communication}<span class="projektboerse_required">*</span></label>
                {$communication_select}
            </div>

            <div class="trow1 projektboerse_field">
                <label>{$lang->projektboerse_contact_person}</label>
                {$contact_person_select}
            </div>

            <div class="trow2 projektboerse_field">
                <label for="approve_feedback_deadline">{$lang->projektboerse_feedback_deadline}</label>
                <input type="date" id="approve_feedback_deadline" name="feedback_deadline" value="{$feedback_deadline_value}" />
            </div>

            <div class="trow1 projektboerse_field">
                <label for="approve_implementation_deadline">{$lang->projektboerse_implementation_deadline}</label>
                <input type="date" id="approve_implementation_deadline" name="implementation_deadline" value="{$implementation_deadline_value}" />
            </div>

            <div class="trow2 projektboerse_field">
                <label for="approve_wiki_links">{$lang->projektboerse_wiki_links}</label>
                <textarea id="approve_wiki_links" name="wiki_links" rows="3" placeholder="{$lang->projektboerse_wiki_links_placeholder}">{$project['wiki_links']}</textarea>
                <span class="projektboerse_hint">{$lang->projektboerse_wiki_links_hint}</span>
            </div>

            {$groups_field}

            <div class="trow1 projektboerse_actions projektboerse_actions_center">
                <input type="submit" value="{$lang->projektboerse_approve}" class="button" />
            </div>
        </div>
    </form>
</div>
{$footer}
</body>
</html>

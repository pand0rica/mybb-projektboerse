<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_reject}: {$project['title']}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <form action="modcp.php?action=projektboerse" method="post">
        <input type="hidden" name="do" value="reject" />
        <input type="hidden" name="pid" value="{$project['pid']}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

        <div class="tborder">
            <div class="thead"><strong>{$lang->projektboerse_reject}: {$project['title']}</strong></div>

            <div class="trow1 projektboerse_field">
                {$lang->projektboerse_reject_intro}
            </div>

            <div class="trow2 projektboerse_field">
                <label for="projektboerse_reject_reason">
                    {$lang->projektboerse_reject_reason_label}<span class="projektboerse_required">*</span>
                </label>
                <textarea id="projektboerse_reject_reason" name="reason" rows="6" required placeholder="{$lang->projektboerse_reject_reason_placeholder}"></textarea>
                <span class="projektboerse_hint">{$lang->projektboerse_reject_reason_hint}</span>
            </div>

            <div class="trow1 projektboerse_actions projektboerse_actions_center">
                <input type="submit" value="{$lang->projektboerse_reject}" class="button" />
                <input type="submit" class="button" form="projektboerse_cancel_form" value="{$lang->projektboerse_cancel}" />
            </div>
        </div>
    </form>
    <form method="get" action="modcp.php" id="projektboerse_cancel_form"><input type="hidden" name="action" value="projektboerse" /></form>
</div>
{$footer}
</body>
</html>

<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_delete}: {$project['title']}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <form action="modcp.php?action=projektboerse" method="post">
        <input type="hidden" name="do" value="delete" />
        <input type="hidden" name="pid" value="{$project['pid']}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />

        <div class="tborder">
            <div class="thead"><strong>{$lang->projektboerse_delete}: {$project['title']}</strong></div>

            <div class="trow1 projektboerse_field">
                <div class="projekt_deadline_expired">
                    <strong>&#9888; {$lang->projektboerse_delete_confirm_title}</strong>
                </div>
                <p>{$lang->projektboerse_delete_confirm_body}</p>
            </div>

            <div class="trow2 projektboerse_actions projektboerse_actions_center">
                <input type="submit" value="{$lang->projektboerse_delete}" class="button" />
                <a href="modcp.php?action=projektboerse">{$lang->projektboerse_cancel}</a>
            </div>
        </div>
    </form>
</div>
{$footer}
</body>
</html>

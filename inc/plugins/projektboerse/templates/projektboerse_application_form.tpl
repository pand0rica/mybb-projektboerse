<div class="tborder projektboerse_section" id="apply">
    <div class="thead"><strong>{$lang->projektboerse_apply}</strong></div>
    <div class="trow1 projektboerse_field">
        <form action="projektboerse.php" method="post">
            <input type="hidden" name="do" value="submit_application" />
            <input type="hidden" name="pid" value="{$project['pid']}" />
            <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
            <label for="projektboerse_application_text">{$lang->projektboerse_application_text}</label>
            <textarea id="projektboerse_application_text" name="application_text" rows="10"></textarea>
            <div class="projektboerse_actions">
                <input type="submit" value="{$lang->projektboerse_submit_application}" class="button" />
            </div>
        </form>
    </div>
</div>

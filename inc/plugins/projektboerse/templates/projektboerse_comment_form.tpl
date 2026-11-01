<div class="trow2 projektboerse_field">
    <form action="projektboerse.php" method="post">
        <input type="hidden" name="do" value="add_comment" />
        <input type="hidden" name="pid" value="{$project['pid']}" />
        <input type="hidden" name="my_post_key" value="{$mybb->post_code}" />
        <label for="projektboerse_comment_input">{$lang->projektboerse_feedback_comments}</label>
        <textarea id="projektboerse_comment_input" name="comment" rows="5" placeholder="{$lang->projektboerse_comment_placeholder}"></textarea>
        <div class="projektboerse_actions">
            <input type="submit" value="{$lang->projektboerse_submit_comment}" class="button" />
        </div>
    </form>
</div>

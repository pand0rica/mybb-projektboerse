<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_my_applications}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <div class="tborder">
        <div class="thead projektboerse_thead"><strong>{$lang->projektboerse_my_applications}</strong>{$submit_link}</div>
        <div class="trow1">{$projektboerse_nav}</div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_applications}</strong></div>
        {$applications_list}
    </div>
</div>
{$footer}
</body>
</html>

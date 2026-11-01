<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <div class="tborder">
        <div class="thead projektboerse_thead"><strong>{$lang->projektboerse}</strong>{$submit_link}</div>
        <div class="trow1">{$projektboerse_nav}</div>
    </div>

    {$projektboerse_filter}

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$page_title}</strong></div>
        {$projektboerse_projects}
    </div>

    {$multipage}
</div>
{$footer}
</body>
</html>

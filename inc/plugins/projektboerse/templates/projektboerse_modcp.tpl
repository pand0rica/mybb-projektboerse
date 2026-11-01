<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_modcp}</title>
{$headerinclude}
</head>
<body>
{$header}
<table width="100%" border="0" align="center">
<tr>
{$modcp_nav}
<td valign="top">
    <div class="tborder">
        <div class="thead"><strong>{$lang->projektboerse_modcp_pending}</strong></div>
        {$pending_projects}
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_modcp_applications}</strong></div>
        {$pending_applications}
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_modcp_all_projects}</strong></div>
        {$all_projects}
    </div>
</td>
</tr>
</table>
{$footer}
</body>
</html>

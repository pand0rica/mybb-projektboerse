<html>
<head>
<title>{$mybb->settings['bbname']} - {$lang->projektboerse_stats}</title>
{$headerinclude}
</head>
<body>
{$header}
<div class="projektboerse_container">
    <div class="tborder">
        <div class="thead projektboerse_thead"><strong>{$lang->projektboerse}</strong>{$submit_link}</div>
        <div class="trow1">{$projektboerse_nav}</div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_global_stats}</strong></div>
        <div class="trow1">
            <table class="statistik_grid">
                <tr>
                    <td>
                        <div class="statistik_box">
                            <span class="statistik_number">{$total_projects}</span>
                            <span class="statistik_label">{$lang->projektboerse_total_projects}</span>
                        </div>
                    </td>
                    <td>
                        <div class="statistik_box">
                            <span class="statistik_number">{$completed_count}</span>
                            <span class="statistik_label">{$lang->projektboerse_completed_projects}</span>
                        </div>
                    </td>
                </tr>
                <tr>
                    <td>
                        <div class="statistik_box">
                            <span class="statistik_number">{$active_count}</span>
                            <span class="statistik_label">{$lang->projektboerse_active_projects}</span>
                        </div>
                    </td>
                    <td>
                        <div class="statistik_box">
                            <span class="statistik_number">{$total_applications}</span>
                            <span class="statistik_label">{$lang->projektboerse_total_applications}</span>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_top_implementers}</strong></div>
        {$top_implementers}
    </div>

    <div class="tborder projektboerse_section">
        <div class="thead"><strong>{$lang->projektboerse_current_work}</strong></div>
        {$current_work_list}
    </div>
</div>
{$footer}
</body>
</html>

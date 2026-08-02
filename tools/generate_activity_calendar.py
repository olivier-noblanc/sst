#!/usr/bin/env python3
"""
Generate an HTML activity calendar from git log.

Usage:
    python tools/generate_activity_calendar.py [--since YYYY-MM-DD] [--output FILE]

Examples:
    python tools/generate_activity_calendar.py
    python tools/generate_activity_calendar.py --since 2026-07-01
    python tools/generate_activity_calendar.py --since 2026-07-23 --output docs/activity.html
"""

import subprocess
import argparse
import html
import re
from datetime import datetime, timedelta
from collections import defaultdict

EVENING_HOUR = 19  # commits after this hour are "evening"

WEEKDAY_FR = ["Lun", "Mar", "Mer", "Jeu", "Ven", "Sam", "Dim"]


def get_git_commits(since: str | None = None) -> list[dict]:
    """Fetch commits from git log."""
    cmd = [
        "git", "log",
        "--format=%ad|%H|%s",
        "--date=format:%Y-%m-%d %H:%M",
        "--reverse",
    ]
    if since:
        cmd.extend(["--since", since])

    result = subprocess.run(cmd, capture_output=True, text=True, check=True)
    commits = []
    for line in result.stdout.strip().split("\n"):
        if not line:
            continue
        parts = line.split("|", 2)
        if len(parts) < 3:
            continue
        date_str, sha, message = parts
        dt = datetime.strptime(date_str.strip(), "%Y-%m-%d %H:%M")
        commits.append({
            "datetime": dt,
            "date": dt.date(),
            "time": dt.strftime("%H:%M"),
            "hour": dt.hour,
            "sha": sha.strip()[:7],
            "message": message.strip(),
            "is_evening": dt.hour >= EVENING_HOUR,
            "is_weekend": dt.weekday() >= 5,  # Saturday=5, Sunday=6
        })
    return commits


def classify_day(commits: list[dict]) -> str:
    """Return CSS class for a day based on its commits."""
    if not commits:
        return "none"
    has_evening = any(c["is_evening"] for c in commits)
    has_weekend = any(c["is_weekend"] for c in commits)
    if has_evening and has_weekend:
        return "combo"
    if has_evening:
        return "evening"
    if has_weekend:
        return "weekend"
    return "workday"


def generate_html(commits: list[dict], since: str | None = None) -> str:
    """Generate the full HTML calendar."""
    if not commits:
        return "<p>Aucun commit trouvé.</p>"

    # Stats
    total = len(commits)
    evening_count = sum(1 for c in commits if c["is_evening"])
    weekend_count = sum(1 for c in commits if c["is_weekend"])
    latest = max(c["datetime"] for c in commits)
    earliest = min(c["datetime"] for c in commits)

    # Group by date
    by_date: dict[datetime.date, list[dict]] = defaultdict(list)
    for c in commits:
        by_date[c["date"]].append(c)

    # Calendar grid
    first_date = min(by_date.keys())
    last_date = max(by_date.keys())

    # Build month grid (first day of first month to last day of last month)
    cal_start = first_date.replace(day=1)
    if last_date.month == 12:
        cal_end = last_date.replace(year=last_date.year + 1, month=1, day=1) - timedelta(days=1)
    else:
        cal_end = last_date.replace(month=last_date.month + 1, day=1) - timedelta(days=1)

    weeks_html = []
    current = cal_start
    # Pad to start on Monday
    weekday = current.weekday()  # 0=Mon
    pad_days = weekday
    current -= timedelta(days=pad_days)

    while current <= cal_end:
        week_days = []
        for _ in range(7):
            if current.month == cal_start.month or (current >= first_date and current <= last_date):
                day_num = current.day
                day_commits = by_date.get(current, [])
                cls = classify_day(day_commits) if current >= first_date and current <= last_date else "none"
                if current < first_date or current > cal_end:
                    cls = "empty"
                    day_num = ""
                elif not day_commits and current >= first_date and current <= cal_end:
                    cls = "none"

                if cls == "empty":
                    week_days.append(f'<div class="day empty"></div>')
                else:
                    tooltip_lines = []
                    if day_commits:
                        times = f"{day_commits[0]['time']} à {day_commits[-1]['time']}"
                        day_name = WEEKDAY_FR[current.weekday()]
                        label = "Soirée" if classify_day(day_commits) in ("evening", "combo") else ""
                        if classify_day(day_commits) == "weekend":
                            label = "Week-end"
                        if classify_day(day_commits) == "combo":
                            label = "Soirée + Week-end"
                        tooltip = f"{current.day} {current.strftime('%b')} ({day_name})<br>{len(day_commits)} commits — {times}"
                        if label:
                            tooltip += f"<br>{label}"
                        week_days.append(
                            f'<div class="day {cls}">'
                            f'{day_num}'
                            f'<div class="tooltip">{tooltip}</div>'
                            f'</div>'
                        )
                    else:
                        week_days.append(f'<div class="day {cls}">{day_num}</div>')
            else:
                week_days.append(f'<div class="day empty"></div>')
            current += timedelta(days=1)
        weeks_html.append(f'<div class="week">{"".join(week_days)}</div>')

    # Commit list
    commits_list_html = []
    for date in sorted(by_date.keys()):
        day_commits = by_date[date]
        cls = classify_day(day_commits)
        day_name = WEEKDAY_FR[date.weekday()]
        label_cls = ""
        if "evening" in cls:
            label_cls = " evening-label"
        if "weekend" in cls:
            label_cls = " weekend-label"

        times = f"{day_commits[0]['time']} → {day_commits[-1]['time']}"
        commits_list_html.append(
            f'<div class="day-group">'
            f'<div class="day-date{label_cls}">{day_name} {date.strftime("%d %B %Y")} — {len(day_commits)} commits ({times})</div>'
        )
        for c in day_commits:
            commit_cls = " evening-commit" if c["is_evening"] else ""
            msg_escaped = html.escape(c["message"])
            commits_list_html.append(
                f'<div class="commit{commit_cls}">'
                f'<span class="commit-time">{c["time"]}</span>'
                f'<span class="commit-msg">{msg_escaped}</span>'
                f'</div>'
            )
        commits_list_html.append("</div>")

    return f"""<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activité Git — SST DREETS BFC</title>
    <style>
        * {{ margin: 0; padding: 0; box-sizing: border-box; }}
        body {{ font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0d1117; color: #c9d1d9; padding: 2rem; }}
        h1 {{ text-align: center; margin-bottom: 0.5rem; color: #58a6ff; font-size: 1.8rem; }}
        .subtitle {{ text-align: center; color: #8b949e; margin-bottom: 2rem; font-size: 0.95rem; }}
        .stats {{ display: flex; justify-content: center; gap: 2rem; margin-bottom: 2rem; flex-wrap: wrap; }}
        .stat {{ background: #161b22; border: 1px solid #30363d; border-radius: 8px; padding: 1rem 1.5rem; text-align: center; min-width: 140px; }}
        .stat-value {{ font-size: 2rem; font-weight: 700; color: #58a6ff; }}
        .stat-label {{ font-size: 0.8rem; color: #8b949e; margin-top: 0.25rem; }}
        .stat.evening .stat-value {{ color: #f0883e; }}
        .stat.weekend .stat-value {{ color: #a371f7; }}

        .calendar {{ max-width: 900px; margin: 0 auto 2rem; }}
        .week {{ display: grid; grid-template-columns: repeat(7, 1fr); gap: 4px; margin-bottom: 4px; }}
        .day-header {{ text-align: center; font-size: 0.7rem; color: #8b949e; padding: 4px 0; font-weight: 600; }}
        .day {{ aspect-ratio: 1; border-radius: 4px; position: relative; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; cursor: default; }}
        .day.empty {{ background: transparent; }}
        .day.none {{ background: #161b22; border: 1px solid #21262d; }}
        .day.workday {{ background: #0e4429; border: 1px solid #238636; }}
        .day.evening {{ background: #9e6a03; border: 1px solid #d29922; }}
        .day.weekend {{ background: #8957e5; border: 1px solid #a371f7; }}
        .day.combo {{ background: linear-gradient(135deg, #9e6a03 50%, #8957e5 50%); border: 1px solid #a371f7; }}

        .day .tooltip {{ display: none; position: absolute; bottom: 110%; left: 50%; transform: translateX(-50%); background: #1c2128; border: 1px solid #30363d; border-radius: 6px; padding: 0.5rem 0.75rem; font-size: 0.7rem; white-space: nowrap; z-index: 10; color: #c9d1d9; box-shadow: 0 4px 12px rgba(0,0,0,0.4); }}
        .day:hover .tooltip {{ display: block; }}

        .legend {{ display: flex; justify-content: center; gap: 1.5rem; margin-bottom: 2rem; flex-wrap: wrap; }}
        .legend-item {{ display: flex; align-items: center; gap: 0.4rem; font-size: 0.8rem; color: #8b949e; }}
        .legend-color {{ width: 14px; height: 14px; border-radius: 3px; }}

        .commits-list {{ max-width: 900px; margin: 0 auto; }}
        .day-group {{ margin-bottom: 1rem; }}
        .day-date {{ font-size: 0.85rem; font-weight: 600; color: #58a6ff; margin-bottom: 0.3rem; padding-left: 0.5rem; border-left: 3px solid #58a6ff; }}
        .day-date.evening-label {{ border-left-color: #f0883e; color: #f0883e; }}
        .day-date.weekend-label {{ border-left-color: #a371f7; color: #a371f7; }}
        .commit {{ padding: 0.25rem 0.5rem 0.25rem 1.5rem; font-size: 0.8rem; color: #8b949e; position: relative; }}
        .commit::before {{ content: '●'; position: absolute; left: 0.3rem; color: #30363d; }}
        .commit.evening-commit::before {{ color: #f0883e; }}
        .commit-time {{ color: #484f58; margin-right: 0.5rem; }}
        .commit-msg {{ color: #c9d1d9; }}

        @media (max-width: 600px) {{
            body {{ padding: 1rem; }}
            .stats {{ gap: 0.75rem; }}
            .stat {{ min-width: 100px; padding: 0.75rem; }}
            .stat-value {{ font-size: 1.5rem; }}
        }}
    </style>
</head>
<body>
    <h1>Activité Git — Application SST</h1>
    <p class="subtitle">DREETS BFC — {earliest.strftime('%d %B %Y')} au {latest.strftime('%d %B %Y')}</p>

    <div class="stats">
        <div class="stat">
            <div class="stat-value">{total}</div>
            <div class="stat-label">Commits totaux</div>
        </div>
        <div class="stat evening">
            <div class="stat-value">{evening_count}</div>
            <div class="stat-label">Commits soirée (après {EVENING_HOUR}h)</div>
        </div>
        <div class="stat weekend">
            <div class="stat-value">{weekend_count}</div>
            <div class="stat-label">Commits week-end</div>
        </div>
        <div class="stat">
            <div class="stat-value">{latest.strftime('%H:%M')}</div>
            <div class="stat-label">Heure la plus tardive</div>
        </div>
    </div>

    <div class="legend">
        <div class="legend-item"><div class="legend-color" style="background:#0e4429;border:1px solid #238636;"></div> Jour ouvré</div>
        <div class="legend-item"><div class="legend-color" style="background:#9e6a03;border:1px solid #d29922;"></div> Soirée (après {EVENING_HOUR}h)</div>
        <div class="legend-item"><div class="legend-color" style="background:#8957e5;border:1px solid #a371f7;"></div> Week-end</div>
        <div class="legend-item"><div class="legend-color" style="background:linear-gradient(135deg,#9e6a03 50%,#8957e5 50%);border:1px solid #a371f7;"></div> Soirée + week-end</div>
    </div>

    <div class="calendar">
        <div class="week">
            <div class="day-header">Lun</div>
            <div class="day-header">Mar</div>
            <div class="day-header">Mer</div>
            <div class="day-header">Jeu</div>
            <div class="day-header">Ven</div>
            <div class="day-header">Sam</div>
            <div class="day-header">Dim</div>
        </div>
        {"".join(weeks_html)}
    </div>

    <div class="commits-list">
        <h2 style="color:#58a6ff;margin-bottom:1rem;font-size:1.2rem;">Détail des commits</h2>
        {"".join(commits_list_html)}
    </div>

    <footer style="text-align:center;color:#484f58;margin-top:3rem;padding-top:1rem;border-top:1px solid #21262d;font-size:0.75rem;">
        Généré le {datetime.now().strftime('%d/%m/%Y à %H:%M')} — Application SST DREETS BFC
    </footer>
</body>
</html>"""


def main():
    parser = argparse.ArgumentParser(description="Generate git activity calendar HTML")
    parser.add_argument("--since", help="Only include commits since this date (YYYY-MM-DD)")
    parser.add_argument("--output", default="docs/activity_calendar.html", help="Output HTML file path")
    args = parser.parse_args()

    commits = get_git_commits(since=args.since)
    html_content = generate_html(commits, since=args.since)

    with open(args.output, "w", encoding="utf-8") as f:
        f.write(html_content)

    print(f"Generated: {args.output}")
    print(f"  {len(commits)} commits since {args.since or 'forever'}")
    evening = sum(1 for c in commits if c["is_evening"])
    weekend = sum(1 for c in commits if c["is_weekend"])
    print(f"  {evening} evening, {weekend} weekend")


if __name__ == "__main__":
    main()

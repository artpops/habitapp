# Habit Tracking Web Application

A PHP 7.4+ habit tracking app designed for shared hosting. Users can create habits, track daily progress with a heatmap, manage date-specific to-dos, and earn collectibles once both lists hit 90%+ for a finished day.
A PHP 7.4+ habit tracking app designed for shared hosting. Users can create habits, track daily progress with a heatmap, and earn collectibles when they complete 90% or more of their tasks for the day.

## Features
- Registration and login with password hashing (`password_hash`).
- Daily checklist with completion percentage and visual progress.
- Separate, date-specific to-do list that resets each day.
- Heatmap view for the current month.
- Habit CRUD (add, edit, delete) and reordering controls.
- Collectible rewards unlocked after midnight when both habits and to-dos reach 90%+ for the finished day, with sample SVG images in `/awards/`.
- Heatmap view for the current month.
- Habit CRUD (add, edit, delete) and reordering controls.
- Collectible rewards unlocked at 90%+ daily completion, with sample SVG images in `/awards/`.
- Public profile pages exposing only aggregated activity, heatmap, and collectibles.
- CSRF tokens on all forms/API calls and server-side validation/sanitization.

## File Structure
```
/habitapp/
├── api/
│   ├── habits.php
│   ├── completions.php
│   ├── collectibles.php
│   └── todos.php
│   └── collectibles.php
├── assets/
│   ├── css/style.css
│   └── js/app.js
├── awards/collectible_001.svg (sample images)
├── includes/
│   ├── auth.php
│   ├── config.php
│   └── functions.php
├── dashboard.php
├── index.php
├── install.php
├── install.sql
├── logout.php
├── profile.php
└── register.php
```

## Installation
1. Create a MySQL/MariaDB database and user, then update credentials in `includes/config.php`.
2. Upload the repository to your shared hosting root (ensuring `awards/` is writable if you plan to add more images).
3. Run `install.php` once in your browser to create tables, then delete the file if desired.
4. Visit `index.php` to register the first account.

## Daily Reward Logic
- Habit completion rate is `completed / total habits` for the day (if you have no habits, it is treated as 100%).
- To-do completion rate is `completed / total to-dos` for that date (if no to-dos, treated as 100%).
- A reward is only considered after midnight for the prior day. Both rates must be 90%+ and the day must be in the past.
- If eligible and no prior reward exists for that date, a random collectible not already owned is awarded. If the collection is complete, users see a congratulatory message instead.
- Completion rate is `completed / total habits` for the day.
- If the percentage is at least 90% and no reward was given that day, a random collectible not already owned is awarded.
- If the collection is complete, users see a congratulatory message instead of a new reward.

## Security Notes
- CSRF tokens are required for all form submissions and fetch requests.
- All SQL queries use prepared statements to prevent injection.
- Sessions use secure cookie flags (HTTPS-only when applicable).

## Optional Enhancements
Suggested extensions include streak counters, weekly/monthly reporting, CSV exports, and a dark mode toggle.

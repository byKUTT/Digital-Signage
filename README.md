# Digital Signage CMS — WordPress Plugin

A WordPress plugin that turns a WordPress install into a full digital signage (CMS) platform: manage channels, screens, playlists/slides and schedules from wp-admin, and drive TVs/kiosks/tablets from a chrome-less, auto-fullscreen frontend player.

The plugin source lives in [`digital-signage/`](digital-signage/) and is also packaged as [`digital-signage.zip`](digital-signage.zip), ready to upload via **Plugins → Add New → Upload Plugin** in wp-admin.

## What's included

- **Channels, Screens, Slides, Schedules** as custom post types, reusing native WP admin list tables, the media library, REST API and capabilities.
- **Custom DB tables** (`ds_heartbeats`, `ds_proof_of_play`, `ds_pairing_codes`) for high-write, append-only data.
- **Scheduling**: recurring day-of-week/time rules, one-off date overrides, per-slide time windows, and priority/emergency channels that interrupt rotation on all screens instantly.
- **Slide types**: image, video (full-length or fixed-duration), webpage/iframe, custom HTML/CSS, RSS/Atom ticker, weather widget, live clock, PDF/Google Slides embed, social embed — each with per-slide duration and transition overrides on top of global defaults.
- **Frontend player** (`/signage/play/{token}/`): unguessable per-screen token, auto-fullscreen with a click-to-start fallback, landscape/portrait/auto orientation, multi-zone layouts (fullscreen, main+ticker, split-screen, grid), REST polling for live updates, next-slide preloading, offline-safe local caching, and periodic heartbeat reporting.
- **Admin**: Screens dashboard with live online/offline status (via the WP Heartbeat API), pairing flow (code generated on the unpaired screen, confirmed in wp-admin), bulk channel assignment, drag-and-drop playlist reordering, weekly calendar view, proof-of-play analytics with CSV export, JSON channel import/export, remote refresh/reload commands, and a **Signage Manager** role for non-technical staff.
- **REST API** under `/wp-json/ds/v1/` for the player and for external kiosk hardware (e.g. a Raspberry Pi running a browser in kiosk mode).

See [`digital-signage/readme.txt`](digital-signage/readme.txt) for the standard WordPress.org-style plugin readme, and the PHPDoc block at the top of each class in `digital-signage/includes/` for how each subsystem fits together.

## Installing

1. Download `digital-signage.zip` from this repo.
2. In wp-admin: **Plugins → Add New → Upload Plugin**, choose the zip, and click **Install Now**, then **Activate**.
3. Go to **Digital Signage → Settings** to set defaults (durations, transition, poll/heartbeat intervals, time zone).
4. Go to **Digital Signage → Pair a Screen** to link your first display.

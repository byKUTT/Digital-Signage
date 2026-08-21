# Digital Signage CMS — WordPress Plugin

A WordPress plugin that turns a WordPress install into a full digital signage (CMS) platform: manage channels, screens, playlists/slides and schedules from a fully custom wp-admin interface (not WordPress's native post editor), and drive TVs/kiosks/tablets from a chrome-less, auto-fullscreen frontend player.

By **byKUTT**.

The plugin source lives in [`digital-signage/`](digital-signage/) and is also packaged as [`digital-signage.zip`](digital-signage.zip), ready to upload via **Plugins → Add New → Upload Plugin** in wp-admin.

## What's included

- **A fully custom admin UI** — custom list and edit pages for Channels, Screens, Slides and Schedules (Channels, Screens, Slides and Schedules are stored as custom post types under the hood, but there's no native WP post-editor screen involved: every page is hand-built and gated by a single `manage_digital_signage` capability).
- **Custom DB tables** (`ds_heartbeats`, `ds_proof_of_play`, `ds_pairing_codes`) for high-write, append-only data.
- **Scheduling**: recurring day-of-week/time rules, one-off date overrides, per-slide time windows, and priority/emergency channels that interrupt rotation on all screens instantly.
- **Slide types**: image, video (full-length or fixed-duration), webpage/iframe, custom HTML/CSS, RSS/Atom ticker, weather widget, live clock, PDF/Google Slides embed, social embed — each with per-slide duration and transition overrides on top of global defaults.
- **Frontend player** (`/signage/play/{token}/`): unguessable per-screen token, auto-fullscreen with a click-to-start fallback, landscape/portrait/auto orientation, multi-zone layouts (fullscreen, main+ticker, split-screen, grid), REST polling for live updates, next-slide preloading, offline-safe local caching, and periodic heartbeat reporting.
- **Admin**: Screens dashboard with live online/offline status (via the WP Heartbeat API), pairing flow (code generated on the unpaired screen, confirmed in wp-admin), bulk channel assignment, drag-and-drop playlist reordering, weekly calendar view, proof-of-play analytics with CSV export, JSON channel import/export, remote refresh/reload commands, and a **Signage Manager** role for non-technical staff.
- **REST API** under `/wp-json/ds/v1/` for the player and for external kiosk hardware (e.g. a Raspberry Pi running a browser in kiosk mode).

See [`digital-signage/readme.txt`](digital-signage/readme.txt) for the standard WordPress.org-style plugin readme, and the PHPDoc block at the top of each class in `digital-signage/includes/` for how each subsystem fits together.

## Installing the plugin

1. Download `digital-signage.zip` from this repo.
2. In wp-admin: **Plugins → Add New → Upload Plugin**, choose the zip, and click **Install Now**, then **Activate**.
3. Go to **Digital Signage → Settings** to set defaults (durations, transition, poll/heartbeat intervals, time zone).
4. From the **Digital Signage** dashboard, click **Pair a New Screen** to link your first display.

The admin menu is deliberately kept to 6 items — **Dashboard, Channels,
Screens, Schedules, Calendar, Settings**. Slides have no tab of their own:
they only ever belong to one channel, so they're added and reordered
directly from that channel's edit screen. Pairing, Proof of Play and
Import/Export are one click away as buttons on the Dashboard/Settings pages
rather than extra sidebar entries.

## Setting up a physical screen

Once a screen is paired in wp-admin, point the display's browser at its
player URL (`/signage/play/{token}/`) in kiosk/full-screen mode. Two
ready-made installers are included for the common cases:

- **[`raspberry-pi-kiosk/`](raspberry-pi-kiosk/)** — one script
  (`install-kiosk.sh`) that turns a Raspberry Pi into a dedicated player:
  boots straight to a full-screen Chromium kiosk showing your player URL, no
  desktop needed, with a watchdog that relaunches it if it ever crashes.
- **[`windows-kiosk/`](windows-kiosk/)** — a PowerShell player
  (`install-kiosk.ps1`) that auto-starts a chrome-less kiosk browser window
  on Windows sign-in, with a configurable global hotkey (default
  `Ctrl+Alt+Shift+Q`) to close it back to the desktop.

See each folder's README for full setup steps.

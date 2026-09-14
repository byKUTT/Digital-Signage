# Screens byKUTT — Digital Signage for WordPress

A WordPress plugin that turns a WordPress install into a group-based digital signage platform: users manage channels, Screens, media, schedules, design, and audio in a custom frontend workspace, while site administrators monitor capacity, services, and controller updates in wp-admin.

The plugin source lives in [`wp/`](wp/). Installable ZIP and Linux controller packages are published as GitHub Release assets instead of being committed to the source tree.

## What's included

- **A focused frontend portal** at `/screens/` for everyday screen, channel, controller, schedule, and people management, protected by normal WordPress login.
- **Custom DB tables** (`ds_heartbeats`, `ds_proof_of_play`, `ds_pairing_codes`) for high-write, append-only data.
- **Scheduling**: recurring day-of-week/time rules, one-off date overrides, per-slide time windows, and priority/emergency channels that interrupt rotation on all screens instantly.
- **Slide types**: image, video (full-length or fixed-duration), webpage/iframe, custom HTML/CSS, RSS/Atom ticker, weather widget, live clock, PDF/Google Slides embed, social embed, and an **infinite scroll gallery** (multiple images, configurable background/spacing/speed, looping top-to-bottom on portrait screens or left-to-right on landscape) — each with per-slide duration and transition overrides on top of global defaults.
- **Frontend player** (`/play/{token}/`): unguessable per-screen token, auto-fullscreen with a click-to-start fallback, landscape/portrait/auto orientation, multi-zone layouts (fullscreen, main+ticker, split-screen, grid), images/video filling the screen edge-to-edge by default (with a per-slide "fit inside" option), REST polling for live updates, next-slide preloading, offline-safe local caching, and periodic heartbeat reporting.
- **Admin**: Screens dashboard with live online/offline status (via the WP Heartbeat API), a pairing screen with step-by-step instructions and a scan-to-pair QR code, thumbnail previews in every channel's playlist, a live no-screen-required channel Preview, bulk channel assignment, drag-and-drop playlist reordering, weekly calendar view, proof-of-play analytics with CSV export, JSON channel import/export, remote refresh/reload commands, and a **Signage Manager** role for non-technical staff.
- **Remote Raspberry Pi device management**: a screen running `ds-agent` (bundled with the Pi installer) can have its WiFi network, screen rotation, browser restart, reboot, and OS updates all controlled from that Screen's edit page in wp-admin — no SSH needed after initial setup.
- **REST API** under `/wp-json/ds/v1/` for the player and for external kiosk hardware (e.g. a Raspberry Pi running a browser in kiosk mode).
- **24-hour time and dd.mm.yyyy (Estonian) date formatting** throughout the player's clock widget and admin timestamps.
- **Licensed background music**: upload commercially licensed audio into a group-private library, search by style, build playlists, assign one playlist to a controller output, and automatically fade music around slide audio.

See [`wp/readme.txt`](wp/readme.txt) for the standard WordPress.org-style plugin readme, and the PHPDoc block at the top of each class in `wp/includes/` for how each subsystem fits together.

## Installing the plugin

1. Download `digital-signage.zip` from the latest GitHub Release.
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
player URL (`/play/{token}/`) in kiosk/full-screen mode. Two
ready-made installers are included for the common cases:

### Ubuntu PC with one or more monitors

Use `ubuntu/` to run Google Chrome inside an unattended Xorg
session. Every connected monitor becomes a separately assignable WordPress
Screen. Install and update with:

```bash
git clone https://github.com/byKUTT/Digital-Signage.git
cd Digital-Signage/ubuntu
sudo bash install-kiosk.sh "https://yourdomain.com" "$(whoami)"
sudo reboot

# Later, from any directory; identity and settings stay unchanged:
sudo digital-signage-update
```

It starts from the logged-in graphical session on every boot and launches one
Chrome kiosk per assigned output without window detection or browser-health
restart loops. WordPress can close all players to show the desktop, start or
restart them again, and queue non-blocking software updates. Kiosk mode hides
the cursor; desktop mode restores it. Locking, blanking, suspend, and
hibernation are disabled. Successful remote software updates and verified URL
migrations reboot automatically; there is no daily reboot or watchdog. See `ubuntu/README.md` for recovery, assignment,
diagnostics, and uninstall instructions.

Version 4.8.1 repairs player and preview routing, reduces Settings and Designer
loading work, simplifies type-specific slide creation, and improves audible-video
playback recovery. Version 4.8.0 adds controller-free browser Screens with stable player URLs,
Screen and Channel previews, a two-Screen default group allowance, Buy more
capacity requests, optional reCAPTCHA for custom authentication, and a focused
site-administrator console for group usage, live state, updates, service keys,
and manual command guides. Version 4.7.0 cleaned the source tree into `wp/`, `ubuntu/`, and `pi/`, removed the
retired Windows packages and committed release archives, introduces short public
routes, improves video recovery, restores site-admin Spotify setup, and makes the
primary Ubuntu output available through Spotify's Web Playback SDK. Its 32 starter
designs are image-led Vellum documents with exact landscape and portrait previews.
Deleted controllers now automatically discard only their rejected identity and
return to the branded pairing screen, ready to be safely added again.
Version 4.3.0 added QR-assisted binding and no-channel recovery, a full-screen
Vellum workflow with reusable built-in templates and personal saved files, a
site-admin-only remote diagnostics console, and an updater that reports its
real failing stage while preserving device settings. Version 4.2.0 added a locally bundled Vellum design studio, saved group designs,
one-click channel publishing, editable media-based slides, native 24-hour time
pickers, searchable city timezones, and controller-specific sleep settings.

### Updating the bundled Vellum designer

Run this on the WordPress server from the repository root. It downloads only
the allow-listed runtime files from the official Vellum repository and records
the exact upstream commit in `wp/vendor/vellum/UPSTREAM_COMMIT`:

```bash
sudo bash wp/bin/update-vellum.sh
```

Commit and deploy the changed vendor files with the plugin. Vellum remains
local-first in the browser; publishing through Digital Signage saves both its
native document and a rendered PNG inside the active group.

### VIDAA 9 TV without external hardware

Open `https://your-site.example/tv/` in the VIDAA browser. The stable
launcher creates and remembers the TV's identity, displays a six-character
pairing code, and opens the normal player automatically as soon as that code
is claimed in WordPress. Bookmark the launcher rather than the generated
token URL. Hold the remote **OK** button for five seconds on the launcher to
reset its pairing identity.

The first playback session can require one **OK** press for fullscreen and
autoplay permission. Consumer VIDAA firmware may also require reopening the
browser/bookmark after a cold boot unless the model exposes Browser/App Auto
Start or a hotel/signage setting. MP4 with H.264 video and AAC audio is the
compatibility target; other containers/codecs depend on the individual TV.

Both installers now generate and remember **the device's own pairing
identity** — point them at your site URL (not a pre-paired player URL) and
the same pairing code/token stays valid across every reboot:

- **[`pi/`](pi/)** — one script
  (`install-kiosk.sh`) that turns a Raspberry Pi into a dedicated player:
  boots straight to a full-screen Chromium kiosk with **console autologin
  configured automatically** — no keyboard/mouse needed after install — and
  installs **`ds-agent`**, so the device's WiFi, screen rotation, browser,
  reboots and updates are all controllable from wp-admin afterward. Also
  includes a first-boot WiFi hotspot + setup form
  (`pi/setup-portal/`) for provisioning a fresh device with
  no SSH at all.
- **[`pi/image-build/`](pi/image-build/)** — a `pi-gen` configuration and
  GitHub Actions workflow that builds an actual flashable Raspberry Pi `.img`
  with everything above pre-installed, for provisioning many identical
  screens from one SD card image.

See each folder's README for full setup steps.

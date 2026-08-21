=== Digital Signage CMS ===
Contributors: bykutt
Author: byKUTT
Tags: digital signage, kiosk, cms, screens, display
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Turn WordPress into a full digital signage (CMS) platform for managing content on TVs, tablets and kiosks.

== Description ==

Digital Signage CMS gives you:

* **Channels** — named playlists of content ("Lobby Menu", "Cafeteria Board") assignable to one or more screens.
* **Screens** — registered displays, paired via an unguessable token / pairing code, each with orientation, location and live online/offline status.
* **Slides** — images, videos, webpages (iframe), custom HTML/CSS, RSS/Atom tickers, weather, live clock, PDF/Google Slides embeds and social embeds, with per-slide duration and transition overrides.
* **Schedules** — recurring day-of-week/time rules and one-off date overrides per screen, plus priority/emergency channels that interrupt normal rotation everywhere instantly.
* **Fullscreen player** — a chrome-less, auto-fullscreen frontend at `/signage/play/{token}/` that polls for updates, preloads the next slide, keeps playing from a local cache when offline, and supports multi-zone layouts (main + ticker + corner clock, split screen, grid).
* **Admin dashboard** — live screen status, pairing flow, bulk channel assignment, drag-and-drop playlist reordering, a weekly calendar view, proof-of-play analytics with CSV export, JSON import/export, and remote refresh/reload commands.
* **REST API** (`/wp-json/ds/v1/...`) for the player and for external kiosk hardware such as a Raspberry Pi running a browser in kiosk mode.
* A **Signage Manager** role for non-technical staff who need content control without full wp-admin access.

== Installation ==

1. Upload the `digital-signage` folder to `/wp-content/plugins/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Digital Signage → Settings** to set global slide durations, transitions, poll/heartbeat intervals and time zone.
4. Go to **Digital Signage → Pair a Screen**, open the generated player URL on the target TV/tablet/browser, and enter the code it displays to link the device.
5. Create a **Channel**, add **Slides** to it, then assign the channel to a **Screen** (or schedule it via **Schedules**).

== Architecture notes ==

* Channels, Screens, Slides and Schedules are custom post types (`ds_channel`, `ds_screen`, `ds_slide`, `ds_schedule`) used purely as a storage layer — there is no native WordPress post-editor screen for any of them (`show_ui => false`). Every list, create and edit screen is a fully custom admin UI (`includes/class-ds-admin.php` + `admin/views/*.php`) built from scratch, writing through `includes/class-ds-crud.php` and gated by a single `manage_digital_signage` capability rather than WordPress's per-post-type meta capabilities.
* High-write, append-only data — heartbeats, the proof-of-play log, and pairing codes — live in three custom `$wpdb` tables (`ds_heartbeats`, `ds_proof_of_play`, `ds_pairing_codes`) created on activation via `dbDelta()`.
* Scheduling housekeeping (expiring pairing codes, trimming the proof-of-play log, clearing stale manual overrides) runs on **WP-Cron**.
* The wp-admin Screens dashboard uses WordPress's own **Heartbeat API** to live-refresh status badges while an admin has the page open.
* The frontend player never touches PHP after first load — it talks entirely to the **REST API**, so it works equally well embedded in a WebView or a plain browser tab.

== Changelog ==

= 2.0.0 =
* Replaced the WordPress native post-editor screens with a fully custom admin UI (dashboard, channel/screen/schedule list + edit pages, slide editor) — no more post.php/meta boxes.
* Fixed duplicate menu items and a capability bug that caused saves to redirect incorrectly.
* Simplified the admin menu; slides are managed from their owning channel instead of a separate tab.
* Added Raspberry Pi and Windows kiosk installers.

= 1.0.0 =
* Initial release.

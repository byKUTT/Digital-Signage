=== Digital Signage CMS ===
Contributors: bykutt
Author: byKUTT
Tags: digital signage, kiosk, cms, screens, display
Requires at least: 5.9
Tested up to: 6.6
Requires PHP: 7.4
Stable tag: 2.4.2
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
4. Go to **Digital Signage → Pair a Screen**, open the generated player URL on the target TV/tablet/browser, then either scan the QR code it displays with your phone or enter the code manually to link the device.
5. Create a **Channel**, add **Slides** to it, then assign the channel to a **Screen** (or schedule it via **Schedules**).

== Architecture notes ==

* Channels, Screens, Slides and Schedules are custom post types (`ds_channel`, `ds_screen`, `ds_slide`, `ds_schedule`) used purely as a storage layer — there is no native WordPress post-editor screen for any of them (`show_ui => false`). Every list, create and edit screen is a fully custom admin UI (`includes/class-ds-admin.php` + `admin/views/*.php`) built from scratch, writing through `includes/class-ds-crud.php` and gated by a single `manage_digital_signage` capability rather than WordPress's per-post-type meta capabilities.
* High-write, append-only data — heartbeats, the proof-of-play log, and pairing codes — live in three custom `$wpdb` tables (`ds_heartbeats`, `ds_proof_of_play`, `ds_pairing_codes`) created on activation via `dbDelta()`.
* Scheduling housekeeping (expiring pairing codes, trimming the proof-of-play log, clearing stale manual overrides) runs on **WP-Cron**.
* The wp-admin Screens dashboard uses WordPress's own **Heartbeat API** to live-refresh status badges while an admin has the page open.
* The frontend player never touches PHP after first load — it talks entirely to the **REST API**, so it works equally well embedded in a WebView or a plain browser tab.

== Changelog ==

= 2.4.2 =
* Fixed the real cause of `install-kiosk.sh` silently dying before it ever created `ds-kiosk.service` (reported as "Unit ds-kiosk.service could not be found"): the device-token generation line (`tr ... | head -c 40`) tripped a classic `pipefail` + `head` interaction — `head` closes the pipe as soon as it has 40 bytes, `tr` gets `SIGPIPE`, and with `pipefail` on that counted as the whole pipeline failing even though the token came out correct, so `set -e` aborted the script right there with no error message. `pipefail` is now disabled for just that one line.

= 2.4.1 =
* Infinite Scroll Gallery: background color, image spacing and scroll speed are now set once per channel (new "Infinite Scroll Gallery defaults" section on the Channel edit page) instead of per slide, so every gallery slide in a channel shares the same look automatically.
* Raspberry Pi installer: `ds-kiosk.service` now runs as root and masks (not just disables) `getty@tty1.service` — the previous non-root/PAM-based approach could still fail to actually own the console on some systems. This removes that whole class of failure at the cost of Chromium needing `--no-sandbox` as root (already set), an acceptable trade for a dedicated single-purpose kiosk device.
* `uninstall-kiosk.sh` now removes everything installed (including `~/.xinitrc` and the pairing config) and fully restores console login.
* Expanded the Raspberry Pi README with a complete numbered install guide and manual `git clone`/`git pull` instructions.

= 2.4.0 =
* New slide type: **Infinite Scroll Gallery** — upload multiple images, set a background color and spacing between them, and they loop continuously with a configurable speed. Direction follows the screen's orientation automatically: top-to-bottom on portrait (images full width), left-to-right on landscape (images full height).
* Raspberry Pi installer: replaced the console-autologin + `~/.bash_profile` autostart mechanism with a `ds-kiosk.service` systemd unit that takes `tty1` directly and starts X — fixes installs that booted to a bare terminal instead of the kiosk on some Raspberry Pi OS builds where the login shell didn't reliably source `.bash_profile`. `install-kiosk.sh` cleans up the old mechanism automatically on re-run.

= 2.3.0 =
* New: remote Raspberry Pi device management. A screen running the new `ds-agent` (bundled with the Raspberry Pi kiosk installer) reports WiFi network/signal, CPU temperature, free disk space and rotation on every heartbeat, and a new "Device" panel on the Screen edit page can change its WiFi network, rotate the display, restart the browser, reboot, or check for updates — all remotely, no SSH needed.
* New `raspberry-pi-kiosk/setup-portal/`: a device with no configuration yet puts up a temporary WiFi hotspot with a one-page setup form (WiFi + site URL) instead of the kiosk — connect from a phone, submit, and it provisions itself and reboots into its pairing screen.
* New `pi-image-build/`: a `pi-gen` configuration plus a GitHub Actions workflow to build an actual flashable `.img` with all of the above pre-installed, for provisioning many screens at once.
* Database: added a `device_info` column to the heartbeats table (auto-migrated).

= 2.2.0 =
* Removed all byKUTT branding from the pairing/setup screen and admin footers — it now shows your own site name instead, so the product reads as one cohesive system rather than a watermarked template.
* Removed the now-unused logo asset.

= 2.1.2 =
* byKUTT logo is now plain white text (no gradient fill, no background), matching the pairing screen's dark backdrop; admin footers wrap it in a small dark chip so it stays visible there too.

= 2.1.1 =
* byKUTT logo is now just the animated-gradient wordmark (no background card), matching how it's used everywhere it appears.
* The unpaired-screen setup display now requests fullscreen on load too, with a tap-to-enable fallback, same as the player.

= 2.1.0 =
* Raspberry Pi and Windows kiosk installers now generate and persist the device's own pairing token, so the pairing code/identity survives every reboot without re-pairing.
* The unpaired-screen setup display now shows step-by-step instructions, a scan-to-pair QR code, and animated byKUTT branding.
* Images/video now fill the screen edge-to-edge by default (cropped to fit), with a per-slide "Fit: contain" option to letterbox instead, plus a per-channel background/letterbox color.
* Channel playlists show a thumbnail preview per slide instead of just a text row.
* Clock widget and admin timestamps now use 24-hour time and dd.mm.yyyy (Estonian) date formatting everywhere.
* Added a live "Preview" button on each channel (wp-admin-only, no screen/pairing required) and a Duplicate action for individual slides.
* Refined the admin visual design with slide-type icon illustrations and an onboarding "how it works" diagram.

= 2.0.0 =
* Replaced the WordPress native post-editor screens with a fully custom admin UI (dashboard, channel/screen/schedule list + edit pages, slide editor) — no more post.php/meta boxes.
* Fixed duplicate menu items and a capability bug that caused saves to redirect incorrectly.
* Simplified the admin menu; slides are managed from their owning channel instead of a separate tab.
* Added Raspberry Pi and Windows kiosk installers.

= 1.0.0 =
* Initial release.

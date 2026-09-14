# Digital Signage architecture notes

## Ubuntu multi-display controller

- Ubuntu Desktop uses `ubuntu/`; the Raspberry Pi installer remains
  exclusive to Raspberry Pi OS. The Ubuntu installer migrates away from the
  Pi service by disabling `ds-kiosk.service`, unmasking `getty@tty1`, restoring
  `graphical.target`, and using the normal GDM Xorg session. It never starts a
  competing root-owned X server on `tty1`.
- One Ubuntu PC owns one authenticated controller identity stored outside Git
  under `/etc/digital-signage-ubuntu/`. Each connected XRandR connector is a
  stable output key and maps to one `ds_controller_displays` row/WordPress
  Screen. A disconnected output is marked offline but its Screen assignment is
  retained for reconnection to the same port.
- GDM autologin starts a normal Xorg desktop, then the system-wide XDG
  autostart entry runs only for the configured kiosk user. This inherits the
  session's real display number, X authority, runtime directory, and D-Bus
  address; no service guesses `DISPLAY=:0` before login is ready.
- The controller launches one isolated Chrome-family profile/process per
  connected output. Chrome receives each XRandR output's exact position and
  dimensions on its command line. There is no window discovery or browser
  health check: boot issues one launch per assigned output and trusts Chrome.
  Relaunch occurs only for an explicit Start/Restart command, URL or geometry
  reconciliation, or output reconnection.
- A controller-local state file persists kiosk versus desktop mode across
  reboot. WordPress can close managed profile processes to expose the desktop
  or launch all assigned screens. An invisible X root cursor plus immediate
  `unclutter` enforcement hides the pointer only in kiosk mode and reports
  failures through telemetry. Managed browser policy and the `basic` password-store flag disable
  sign-in, sync, autofill, password saving, and GNOME keyring prompts.
- Persisted settings and identity never live inside the managed Git checkout.
	`sudo digital-signage-update` verifies the configured origin, backs up tracked
	managed-source edits, synchronizes the checkout to the configured remote branch,
	and reapplies the installer in upgrade mode without rerunning routine APT package
	downloads. Site, user, identity, output assignments, schedules, and browser
	profiles remain unchanged. The atomic status file records the exact failing
	stage and a bounded log tail. WordPress starts the updater through a
	dedicated non-blocking oneshot system service; an atomic status file exposes
	running/succeeded/failed results through controller telemetry.
- Linux software-update commands are queued from WordPress without GitHub
  Release metadata because the Ubuntu updater consumes only its locally pinned,
  validated Git origin and branch. The kiosk user receives passwordless sudo
  access only to a fixed root helper whose internal action allowlist rejects
  arbitrary commands.
- Routine admin views prioritize content, assignments, status, and the primary
	kiosk action. Technical telemetry, logs, rare device controls, layout tuning,
	and destructive actions use native accessible disclosure sections.
- Site administrators can queue four fixed read-only diagnostic requests: update
	service/log, controller process, network, and system resources. The controller
	executes these through the existing root-helper allowlist and stores their bounded
	output in normal command history. There is deliberately no arbitrary web shell.
- The Ubuntu installer writes explicit argument-qualified sudoers entries for each
	root-helper action and validates the policy by executing the harmless `check`
	action as the kiosk user. Remote actions never depend on an interactive policy
	agent or a password stored in WordPress.
- Controller-wide GNOME and systemd configuration disables idle locking,
  blanking, suspend, hybrid sleep, suspend-then-hibernate, and hibernation.
  There is no daily timer, watchdog escalation, `StartLimitAction`, browser
  health relaunch, or automatic computer reboot. A manually queued authenticated
  reboot command remains available.

## VIDAA/private smart-TV bootstrap

- `/tv/` is a stable browser entry point; it creates a pairing identity through `/ds/v1/pair/request`, stores the opaque token in localStorage (with a URL-fragment fallback), and checks `/ds/v1/pair/status/{token}` until paired.
- The launcher is recognized both by its normal WordPress rewrite query variable and by an exact request-path fallback, so an in-place update cannot leave it behind a stale permalink-rule 404.
- Once paired, the launcher redirects to the existing `/play/{token}/` renderer. VIDAA, Ubuntu, and Raspberry Pi therefore share the same playlist, transition, heartbeat, and live-revision code paths.
- Holding OK/Enter for five seconds on the launcher clears only that browser's stored identity and requests a new one. Firmware-level power-on launch remains outside the web player's control.
- VIDAA detection is progressive enhancement only: remote OK activation, TV focus styling, resume recovery and the `vidaa-web/{version}` heartbeat label. Playback must continue on unknown smart-TV user agents.

## Live content propagation

- Every channel has an opaque `ds_content_revision`. Channel settings and slide create/update/delete/duplicate/reorder operations replace it through `DS_CRUD::touch_channel()`.
- A paired player checks `/ds/v1/screen/{token}/changes` once per second. The response contains only the currently resolved channel ID and revision; the full playlist is fetched only when that key changes.
- Checks are non-overlapping and back off to 15 seconds during network failure. The existing configurable full-playlist poll remains active for schedule-time transitions and recovery.
- This short revision request is intentional instead of SSE: it avoids reserving a PHP-FPM worker per display and works through hosts/proxies that buffer streaming responses.

## Browser Screens and group capacity

- Every newly created Screen receives an opaque stable `ds_pairing_token`, even
  when it is not assigned to a controller output. The resulting `/play/{token}/`
  URL is a first-class browser player for smart TVs, tablets, embedded browsers,
  and ordinary computers. It uses the same REST playlist and heartbeat path as a
  controller Screen.
- Channel previews remain authenticated and now enforce group access as well as
  the signage capability. Screen previews use the real stable browser URL so the
  preview exercises the exact production renderer.
- A group includes two Screens by default. A site administrator can change both
  Screen and storage limits per group. Controller output discovery leaves excess
  displays unassigned instead of creating Screens beyond that allowance.
- Capacity requests are nonce-protected, group-scoped, rate-limited per user and
  request type, emailed to the WordPress administrator, and retained in the
  administrator overview until that group's limits are saved.

## Administration boundary

- Routine Channels, Screens, controllers, media, schedules, people, design,
  music, and group settings live only in the authenticated frontend manager.
  Legacy wp-admin content URLs redirect to their closest frontend destination.
- wp-admin is site-administrator-only and exposes only Overview, Guides, and
  Global settings. Overview aggregates group storage/Screen allowances, live
  Screen and controller counts, capacity requests, controller versions, and
  software-update actions. Guides contains fixed copy-ready controller commands;
  it never exposes an arbitrary remote shell.
- Google reCAPTCHA v2 is optional. When both global keys are configured, the
  custom login and registration handlers fail closed unless Google's server-side
  verification succeeds. The secret is never rendered back into the browser.

## Pairing and no-channel recovery

- Unpaired controller outputs and token players show QR codes that point to the
	authenticated frontend pairing page with the rotating code prefilled. A claim
	requires a signed-in user, the signage capability, and an active group.
- A paired output with no Screen assignment shows a QR link to its group-scoped
	controller editor. A paired Screen with no active channel shows a QR link to its
	group-scoped Screen editor. These URLs reveal no content and preserve normal
	portal authorization after login.

## Design studio

- The Designer landing surface lists the current user's saved designs (site admins
	can see all designs in the active group) and four code-defined starter templates
	available to every group. Templates become ordinary user-owned `ds_design` posts
	on first save; the shared template definitions are never mutated.
- Opening a template or saved design creates a viewport-fixed workspace around the
	locally bundled Vellum iframe. Save & close returns to the design list, while Save
	& publish continues to generate a group-scoped PNG slide for the selected channel.
- Starter designs are generated from four visual families. Each family owns four
	compositions with independent landscape and portrait canvases, for 32 editable
	starting points. The catalog is data-driven and remains available to every group.

## Spotify Connect control

- Spotify is an optional controller-scoped interactive control surface. Site-level
	Spotify application credentials authorize one Spotify account per controller;
	access and refresh tokens are stored in non-autoloaded controller options.
- The `ds_spotify_user` role owns only `control_digital_signage_spotify`. Explicit
	controller IDs in user meta determine which Spotify panels that user can open;
	the role cannot reach channels, screens, media, settings, groups, or controllers.
- Browser actions call nonce-protected WordPress AJAX handlers. Server-side API
	requests use a fixed Spotify endpoint/action map for device discovery, track
	search, transfer, play, pause, previous, and next. Users never supply an API URL.
- A user can explicitly enable the controller's primary output as a Web Playback
	SDK device. The short-lived access token is omitted from the offline playlist
	cache, and the SDK is loaded only while that controller output is enabled.
- Audible signage video fades controller-local Spotify playback to zero over 1.5
	seconds and restores the previous volume after every audible video releases its
	duck claim.

## Licensed background music

- `DS_Music` stores group-owned playlists as `ds_music_playlist` posts and filters
	audio attachments through the same group access boundary used by signage media.
- A controller stores one playlist, one output key, volume, and shuffle state in a
	non-autoloaded option. Only the Screen mapped to that output receives the music
	payload, preventing every Chrome window on a multi-screen controller from playing
	the same audio independently.
- The player creates one local HTML audio element, advances the local playlist, and
	carries its revision in the normal change key. A sound-enabled video claims a duck
	reference when playback starts, fades music to zero over 1.5 seconds, and releases
	that reference when it ends or is removed. Music returns only after all audible
	videos release their claims.
- Audio files are self-hosted. The portal links to Pixabay Music only as an external
	browsing destination; it does not use or imply a Pixabay music API. Every upload
	requires a recorded license and an explicit commercial-use confirmation.

## Slider rendering

- Infinite Slider uses the Web Animations API for compositor-owned transforms on supporting browsers. The rAF implementation remains only as a compatibility fallback.
- Image-load and ResizeObserver notifications are coalesced into a single animation-frame measurement. Motion starts only when every image in the first logical sequence has decoded dimensions.
- Remeasurement preserves normalized animation progress and custom-width tracks center images on the cross-axis.

## Channel-owned playback presentation

- A channel owns its slide transition in `ds_transition`. The REST layer resolves that value for every slide in the channel and falls back to the global transition only when the channel has no explicit compatible value.
- `infinite_slider` is a channel transition that consumes the ordinary image slides in each zone as one continuous sequence. It does not create or replace a slide type.
- A channel owns Infinite Slider direction (`auto`, `up`, `down`, `left`, or `right`), image width mode/percentage, portrait spacing, landscape spacing, speed, and image border radius. Up/Down select a vertical column, Left/Right select a horizontal row, and Auto uses actual rendered zone dimensions.
- Infinite Slider activates only when every item in a zone is an image with a source. Mixed-content zones preserve all content by falling back to normal fade rotation.
- The historical `infinite_scroll` slide type remains a separate **Infinite Scroll Gallery** that owns an ordered list of attachment IDs and uses its own channel-level background, spacing, and speed defaults.

## Slide names

- Slide titles remain normal editable WordPress post titles.
- `DS_CRUD::save_slide()` assigns the first unused `Slide N` title within the channel whenever the submitted title is blank.
- Legacy per-slide transition metadata is ignored by REST and removed when a slide is saved.

## Continuous slider lifecycle

- The player determines Infinite Slider and Infinite Scroll Gallery direction from the rendered zone dimensions, not only from the screen metadata.
- Portrait tracks use full-width images and vertical spacing; landscape tracks use full-height images and horizontal spacing.
- A shared dependency-free continuous-track renderer repeats the logical image sequence enough times to cover both the viewport and the wrap distance, then moves by one exact sequence length for a seamless loop.
- Every continuous track owns one animation frame loop and one resize observer/listener. `stopTimers()` must clean both before a slide or zone is removed to prevent hidden duplicate animation work.
- Remeasurement converts the current offset to normalized loop progress and reapplies that progress to the new sequence length in either movement direction, avoiding a visible restart after resize or orientation change.
- Animation frames clamp elapsed time to 50 ms so a stalled kiosk browser cannot jump far ahead on recovery. When `prefers-reduced-motion: reduce` is active, the sequence is laid out but its repetitive animation loop is not started.

## Direct player routing

- `DS_Player` resolves player, short player, controller, TV, and authenticated preview paths at an early `template_redirect` priority. Its path-based fallback therefore runs before WordPress canonical redirects even when rewrite rules have not yet been flushed after an update.
- Successful authentication preserves a same-site requested destination. The frontend manager remains the fallback only when login did not carry a valid target.

## Portal loading boundaries

- `DS_Portal::render()` initializes every template dataset safely but fetches only the records required by the active section. Settings does not enumerate attachments, scan media files, query heartbeats, or load fleet records.
- Heartbeats are fetched in one query limited to accessible Screen IDs. Designer format discovery reuses that bulk result instead of querying once per Screen, and Designer-only assets are not enqueued elsewhere.
- Request-local post caching prevents the enqueue and render phases from repeating the same group-scoped post query.

## Slide creation and audible video

- Add slide presents one selected content type and progressively reveals only its relevant source and playback controls. The custom picker filters the existing group library and upload chooser to the selected image/video type, while the server independently verifies ownership, MIME family, and required source data.
- Audible videos claim background-music ducking only after a real `playing` event and release it on pause, end, error, abort, or DOM cleanup. Ubuntu kiosk playback uses the browser's configured autoplay policy; ordinary browsers fall back to muted visual playback plus a focused user-gesture control that enables sound.
- A one-item zone keeps its existing element and never schedules slide advancement. A single video loops in place.

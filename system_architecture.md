# Digital Signage architecture notes

## Ubuntu multi-display controller

- Ubuntu Desktop uses `ubuntu-kiosk/`; the Raspberry Pi installer remains
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
  `sudo digital-signage-update` verifies the configured origin, refuses dirty
  or divergent state, fast-forwards the configured branch, and reapplies the
  installer in upgrade mode. Site, user, identity, output assignments, and
  browser profiles remain unchanged. WordPress starts the updater through a
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
- Controller-wide GNOME and systemd configuration disables idle locking,
  blanking, suspend, hybrid sleep, suspend-then-hibernate, and hibernation.
  There is no daily timer, watchdog escalation, `StartLimitAction`, browser
  health relaunch, or automatic computer reboot. A manually queued authenticated
  reboot command remains available.

## VIDAA/private smart-TV bootstrap

- `/signage/tv/` is a stable browser entry point; it creates a pairing identity through `/ds/v1/pair/request`, stores the opaque token in localStorage (with a URL-fragment fallback), and checks `/ds/v1/pair/status/{token}` until paired.
- The launcher is recognized both by its normal WordPress rewrite query variable and by an exact request-path fallback, so an in-place update cannot leave it behind a stale permalink-rule 404.
- Once paired, the launcher redirects to the existing `/signage/play/{token}/` renderer. VIDAA, Raspberry Pi and Windows therefore share the same playlist, transition, heartbeat and live-revision code paths.
- Holding OK/Enter for five seconds on the launcher clears only that browser's stored identity and requests a new one. Firmware-level power-on launch remains outside the web player's control.
- VIDAA detection is progressive enhancement only: remote OK activation, TV focus styling, resume recovery and the `vidaa-web/{version}` heartbeat label. Playback must continue on unknown smart-TV user agents.

## Live content propagation

- Every channel has an opaque `ds_content_revision`. Channel settings and slide create/update/delete/duplicate/reorder operations replace it through `DS_CRUD::touch_channel()`.
- A paired player checks `/ds/v1/screen/{token}/changes` once per second. The response contains only the currently resolved channel ID and revision; the full playlist is fetched only when that key changes.
- Checks are non-overlapping and back off to 15 seconds during network failure. The existing configurable full-playlist poll remains active for schedule-time transitions and recovery.
- This short revision request is intentional instead of SSE: it avoids reserving a PHP-FPM worker per display and works through hosts/proxies that buffer streaming responses.

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

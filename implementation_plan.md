# VIDAA 9 private TV player — implementation plan

## Confirmed approach

Build a private, hosted VIDAA-friendly web player inside the existing WordPress plugin. The TV opens one stable URL (`/signage/tv/`) in the built-in VIDAA browser. The launcher creates and persists its own device token, shows the existing six-character pairing flow, and redirects to the normal player after pairing. This reuses the tested player and REST API instead of depending on unavailable VIDAA Store or partner tooling.

This approach does not promise firmware-level launch-on-power: consumer VIDAA 9 may require opening the browser/bookmark after a cold boot. The first session may also require one remote-control **OK** press because fullscreen and media autoplay remain browser-controlled.

## Files

### [MODIFY] `digital-signage/includes/class-ds-cpt.php`

- Register a `ds_tv_launcher` query variable and `/signage/tv/` rewrite rule.
- Keep the existing paired player and preview URLs unchanged.

### [MODIFY] `digital-signage/includes/class-ds-player.php`

- Route `/signage/tv/` to a chrome-less launcher template before token-player resolution.
- Hide the WordPress admin bar on the launcher.
- Pass same-origin pairing and player base URLs to the launcher without exposing credentials.

### [NEW] `digital-signage/public/templates/vidaa-launcher.php`

- Use ES5-compatible JavaScript suitable for VIDAA's embedded browser.
- Reuse a stored device token when present; otherwise call `POST /wp-json/ds/v1/pair/request` once and persist the returned token.
- Poll pairing status with cache busting, update the rotating six-character code, and redirect to the paired player URL.
- Provide clear TV/remote-focused loading, offline, retry, and pairing states with no dependency on an external QR service.
- Add a manual reset action reachable by holding the remote **OK/Enter** key, so the TV can be paired again without clearing all browser data.

### [MODIFY] `digital-signage/public/templates/player-template.php`

- Mark the player as VIDAA-capable and expose the plugin version to JavaScript.
- Adjust the start overlay copy for remote-control **OK**, while preserving ordinary browser behavior.

### [MODIFY] `digital-signage/public/js/player.js`

- Detect VIDAA/Hisense user agents without making playback depend on detection.
- Let **OK/Enter/Space** dismiss the start overlay and request fullscreen.
- On visibility return, online recovery, or VIDAA app/browser resume, refresh playlist state and resume the active muted video safely.
- Report a distinct VIDAA web-player app version in heartbeat data.
- Keep the existing one-second revision probe, normal fallback poll, compositor animation paths, and bounded video preloading.

### [MODIFY] `digital-signage/public/css/player.css`

- Add TV-safe focus and pairing/start-overlay styling for 1080p and 4K viewports.
- Avoid heavy filters, gradients, and animations on VIDAA; preserve compositor-only slide/slider transforms.

### [MODIFY] `digital-signage/digital-signage.php`

- Bump the plugin version so WordPress refreshes rewrite rules and TV browser asset URLs.

### [MODIFY] `digital-signage/readme.txt`, `README.md`

- Document the VIDAA 9 private setup: update plugin, open `/signage/tv/`, pair the code, bookmark the launcher, and enable any available browser/app auto-start setting.
- Document supported media guidance: MP4 with H.264 video and AAC audio is the compatibility target; autoplay is muted; unsupported codecs must be converted before upload.
- State the power-on limitation clearly.

### [MODIFY] `system_architecture.md`, `task.md`

- Record the launcher identity lifecycle and VIDAA compatibility rules.
- Track the implementation and verification checklist.

### [REBUILD] `digital-signage.zip`

- Package the updated plugin from `digital-signage/` and verify the archive contains the launcher and changed player assets.

## Verification

- Run `php -l` on every modified/new PHP file.
- Run `node --check` on `public/js/player.js` and extract/check the launcher JavaScript.
- Verify the rewrite rule and query variable statically, and confirm activation/version upgrade schedules a rewrite flush.
- Test first-run token creation, stored-token reuse, rotating pairing codes, successful redirect, reset, retry after network failure, and no redirect loop.
- Test keyboard/remote Enter behavior, fullscreen fallback, visibility resume, live channel revision refresh, image transitions, infinite sliders, fixed-duration video, and full-length video in a desktop compatibility harness.
- Rebuild and inspect `digital-signage.zip`; verify Git status contains only intended files before committing.

# Digital Signage Raspberry Pi 3 performance — implementation plan

## 2.9.5 Pi 3 acceleration and playback scope

### Confirmed bottleneck

The current Firefox kiosk configuration explicitly disables WebRender, accelerated layers, and hardware video decoding in both `user.js` and the browser environment. Raspberry Pi OS Bookworm added V4L2 H.264 hardware decoding to Firefox specifically to improve playback on older Raspberry Pi models, so the current configuration forces expensive software rendering/decoding on the Pi 3.

### Outcome

- Automatically detect Raspberry Pi 3 and install a dedicated low-power kiosk profile.
- Enable KMS/WebRender/compositor acceleration and Raspberry Pi OS Firefox V4L2 H.264 decoding, while retaining browser/software fallbacks when a capability is unavailable.
- Prioritize the kiosk process over background management work without unsafe overclocking.
- Reduce SD-card I/O, Wi-Fi latency, background services, and decorative GPU effects that do not help signage playback.
- Preload and retain only the next video, keep the current slide visible until the next video can play, and avoid starting duration timers while video is buffering.

### Files

#### [NEW] `raspberry-pi-kiosk/optimize-pi.sh`

- Detect Pi model and Raspberry Pi OS generation; apply the Pi 3 profile only on matching hardware.
- Ensure the full KMS `vc4-kms-v3d` overlay is configured without duplicating or conflicting with an existing VC4 setting.
- Disable Wi-Fi power saving and apply conservative VM writeback/swappiness tuning.
- Install a small boot service that selects the `performance` CPU governor for the dedicated kiosk workload; do not overclock or alter voltage.
- Record and disable only safe, unrelated services that are actually enabled (`bluetooth`, `hciuart`, printing, ModemManager, triggerhappy, and Avahi), so uninstall can restore only what this optimizer changed.
- Use volatile bounded journaling to reduce SD-card contention while retaining current-boot diagnostics.

#### [MODIFY] `raspberry-pi-kiosk/install-kiosk.sh`

- Run the optimizer and persist `DS_KIOSK_PROFILE=pi3` after automatic detection.
- Replace the Firefox software-rendering overrides with WebRender, EGL/DMABUF, and hardware H.264 decoding preferences on the Pi 3 profile.
- Remove `MOZ_WEBRENDER=0`; launch with the accelerated X11/EGL path and safe media sandbox settings.
- Give `ds-kiosk.service` higher CPU/I/O priority and OOM protection.
- Add `profile=pi3` to the kiosk URL so the web player can remove expensive cosmetic effects.
- Keep the existing Firefox restart backoff and translation-blocking policy.
- For Chromium fallback, use shared memory and the Raspberry Pi package's native GPU path instead of forcing disk-backed `/dev/shm` behavior or discarding the shader cache.

#### [MODIFY] `raspberry-pi-kiosk/uninstall-kiosk.sh`

- Remove the performance/sysctl/NetworkManager/journald tuning files.
- Restore only services recorded as enabled before optimization.

#### [MODIFY] `raspberry-pi-kiosk/ds-agent/ds-agent.service`

- Run telemetry/management at low CPU and idle I/O priority so it cannot interrupt animation or video decode.

#### [MODIFY] `raspberry-pi-kiosk/README.md`

- Document the Pi 3 profile, supported H.264 recommendation (1080p30 maximum; lower bitrate/resolution preferred for the 1920×440 display), cooling/power requirements, verification commands, and update procedure.

#### [MODIFY] `digital-signage/public/js/player.js`

- Detect the Pi 3 query profile and mark the document for low-power rendering.
- Keep one bounded next-media preload per zone and reuse the prepared video element.
- Set video preload/inline/Pi-friendly playback attributes.
- Keep the previous slide visible until `canplay`, then start proof-of-play and the slide timer; retain a bounded timeout fallback for broken media.
- Pause and unload removed video elements promptly to release decoder/memory resources.

#### [MODIFY] `digital-signage/public/css/player.css`

- Promote video to its own compositor layer.
- Disable Infinite Slider edge masks, shadows, and nonessential entrance effects only under the `ds-low-power` Pi profile.

#### [MODIFY] `digital-signage/digital-signage.php`, `digital-signage/readme.txt`

- Bump plugin/assets to 2.9.5 and document Pi 3 media behavior.

#### [MODIFY] `system_architecture.md`, `task.md`

- Record the device-profile boundary, reversible OS tuning, media readiness gate, and verification checklist.

#### [REBUILD] `digital-signage.zip`

- Rebuild and byte-check the installable plugin archive.

### Verification

- Shell syntax passes for installer, optimizer, and uninstaller.
- Python compilation passes for the device agent and setup portal.
- JavaScript syntax and static media-lifecycle invariants pass.
- Optimizer tests use a temporary fake root/model so they cannot modify the development host.
- ZIP integrity and byte-for-byte source comparison pass.
- Publish the scripts, plugin, and updated ZIP to `claude/wordpress-digital-signage-plugin-mbfbdt` without a force update.

## 2.9.4 live-update and smooth-motion scope

### Outcome

- Connected screens detect a saved channel/slide change within about one second and fetch the full playlist only when its revision changes.
- The normal full-playlist poll remains as a slower recovery path for network interruptions and time-driven schedule changes.
- Infinite Slider movement runs on the browser compositor through the Web Animations API where available, with the existing `requestAnimationFrame` loop retained as a compatibility fallback.
- Custom-width images stay centered on the cross-axis in both vertical and horizontal movement.
- Image-load and resize measurements are coalesced into one animation frame so repeated copies cannot cause visible animation restarts.

### Architecture decision

Use a lightweight `GET /ds/v1/screen/{token}/changes` revision endpoint at a one-second cadence. Do not use a permanently open SSE request: a typical WordPress PHP-FPM deployment would reserve one PHP worker per screen, and reverse proxies may buffer or terminate the stream. The revision response contains only the currently resolved channel ID and its opaque content revision, so unchanged screens do not rebuild playlists or restart sliders.

### Files

#### [MODIFY] `digital-signage/includes/class-ds-crud.php`

- Add a channel revision helper using an opaque UUID value.
- Touch the owning channel after channel settings, slides, duplication, deletion, or ordering changes.

#### [MODIFY] `digital-signage/includes/class-ds-rest.php`

- Register the token-protected `/changes` route.
- Return the resolved channel ID plus channel revision with explicit no-cache headers.
- Include the same revision in full playlist and preview payloads.

#### [MODIFY] `digital-signage/public/js/player.js`

- Run one non-overlapping one-second revision check with offline backoff.
- Fetch/apply the playlist only when channel ID or revision changes; retain the full fallback poll.
- Replace the normal per-frame transform writer with a compositor-backed Web Animation and preserve normalized progress across remeasurement.
- Debounce image-load/resize measurements and keep a single fallback rAF loop only when Web Animations is unavailable.

#### [MODIFY] `digital-signage/public/css/player.css`

- Strengthen cross-axis centering for custom-width tracks and keep transforms compositor-ready.

#### [MODIFY] `digital-signage/digital-signage.php`

- Bump the plugin and asset version to 2.9.4.

#### [MODIFY] `digital-signage/readme.txt`

- Document live revision checks, fallback polling, centered custom widths, and low-power compositor motion.

#### [MODIFY] `system_architecture.md`

- Record revision invalidation and the two-tier update transport.

#### [MODIFY] `task.md`

- Track implementation, verification, ZIP rebuild, and GitHub publication.

#### [REBUILD] `digital-signage.zip`

- Rebuild and byte-check the installable archive against the plugin source.

### Verification

- JavaScript syntax and repository diff checks pass.
- Direction, centering, revision bump, change endpoint, non-overlapping polling, and animation fallback invariants pass.
- ZIP integrity passes and all 36 plugin source files match the archive byte-for-byte.
- Publish to `claude/wordpress-digital-signage-plugin-mbfbdt` without force-updating the branch.

## 2.9.3 direction and screen-width amendment

- Replace axis-only Auto/Vertical/Horizontal selection with Auto/Up/Down/Left/Right movement direction.
- Map legacy Vertical to Up and Horizontal to Left without requiring channels to be resaved.
- Add Full width and custom 10–100% screen-width image sizing.
- Preserve normalized loop progress and seamless wrapping for both negative (Up/Left) and positive (Down/Right) movement.
- Publish a refreshed installable plugin ZIP with the source update.

## 2.9.2 scope

Add an explicit Infinite Slider direction setting with three choices:

- **Auto** — use the rendered zone proportions, preserving current behaviour.
- **Vertical** — always render one vertical column with full-width images.
- **Horizontal** — always render one horizontal row with full-height images.

The Apple-inspired work targets the actual signage screen. The player remains chrome-free and content-led, but gains restrained visual depth, orientation-aware edge treatment, stable continuous movement, and accessibility-aware motion behaviour.

## Files for this update

### [MODIFY] `digital-signage/admin/views/channel-edit.php`

- Read the saved Infinite Slider orientation, defaulting existing channels to `auto`.
- Add an Auto / Vertical / Horizontal segmented choice inside the Infinite Slider settings card.
- Keep spacing, speed, and border radius together as one clearly grouped configuration surface.

### [MODIFY] `digital-signage/admin/css/admin.css`

- Style the orientation choice as a compact, accessible segmented control with a clear selected state, keyboard focus ring, comfortable targets, and no decorative clutter.
- Give the Infinite Slider settings a contained hierarchy so it reads as one feature rather than unrelated number inputs.

### [MODIFY] `digital-signage/includes/class-ds-crud.php`

- Validate `auto`, `vertical`, or `horizontal` and save it as `ds_infinite_slider_orientation`.

### [MODIFY] `digital-signage/includes/class-ds-rest.php`

- Add the resolved `slider_orientation` to Infinite Slider item payloads, with `auto` as the compatibility fallback.

### [MODIFY] `digital-signage/public/js/player.js`

- Resolve the slider axis from the saved setting before falling back to the actual zone proportions.
- Start motion only after a valid first measurement so images do not visibly jump into place.
- Preserve normalized loop progress when the zone resizes or orientation changes instead of restarting from zero.
- Clamp unusually large frame deltas after browser stalls so the track never leaps forward.
- Continue using one `requestAnimationFrame` loop and GPU transforms, with cleanup on every replacement/removal.
- Respect `prefers-reduced-motion: reduce` by presenting the arranged images without repetitive automatic movement.

### [MODIFY] `digital-signage/public/css/player.css`

- Polish the signage-facing Infinite Slider with orientation-aware soft entry/exit masks, consistent corner rendering, subtle image separation, and clean use of the channel background.
- Keep the effect restrained: no visible controls, labels, glass panels, or interface chrome on the signage output.
- Apply forced vertical/horizontal sizing correctly at every zone aspect ratio.
- Disable nonessential motion treatment when reduced motion is requested.

### [MODIFY] `digital-signage/digital-signage.php`

- Bump the plugin version to `2.9.2`.

### [MODIFY] `digital-signage/readme.txt`

- Document selectable orientation and the smoother/accessibility-aware frontend behaviour.

### [MODIFY] `digital-signage.zip`

- Rebuild the installable archive and verify every archived file against the source.

### [MODIFY] `system_architecture.md`

- Record orientation ownership and the player rules for progress-preserving measurement, frame-delta clamping, and reduced motion.

## Verification

- Auto, Vertical, and Horizontal save and round-trip through REST.
- Forced Vertical uses one full-width column even on a landscape display.
- Forced Horizontal uses one full-height row even on a portrait display.
- Auto continues to follow rendered zone proportions.
- Resizing does not restart the track or create a visible seam.
- A stalled frame does not cause a large jump.
- Reduced-motion mode shows content without continuous automatic motion.
- Existing Infinite Slider spacing, speed, radius, mixed-content fallback, Infinite Scroll Gallery, and generated slide names remain intact.
- JavaScript syntax, diff checks, source invariants, ZIP integrity, and ZIP/source equality pass before publication.

---

## 2.9.1 correction history

## Corrected interpretation

**Infinite Slider is a channel-level Slide transition.** It is separate from the existing `infinite_scroll` multi-image slide type.

When a channel/zone uses Infinite Slider, its ordinary image slides become the children of one continuously moving loop. Portrait zones render one vertical column with every image at full zone width. Landscape zones render one horizontal row with every image at full zone height. Multiple images or adjacent image portions remain visible at the same time.

If a zone contains a non-image slide, it will not be hidden: that zone will use normal fade rotation instead. The channel editor will state that Infinite Slider is intended for image-only playlists.

## Corrective files

### [MODIFY] `digital-signage/admin/views/channel-edit.php`

- Add **Infinite Slider** to the channel Slide transition selector.
- Add a clearly named Infinite Slider settings section for vertical spacing, horizontal spacing, speed, and image border radius.
- Restore the existing Infinite Scroll Gallery settings/type as a separate feature so the two concepts are not conflated.

### [MODIFY] `digital-signage/admin/views/slide-edit.php`

- Restore the existing **Infinite Scroll Gallery** name and help text; it remains a separate multi-image slide type.
- Keep the already implemented automatic editable `Slide N` naming behaviour.

### [MODIFY] `digital-signage/includes/class-ds-crud.php`

- Accept `infinite_slider` as a channel transition.
- Save separate channel metadata for Infinite Slider portrait spacing, landscape spacing, speed, and non-negative border radius.
- Keep existing Infinite Scroll Gallery metadata independent.

### [MODIFY] `digital-signage/includes/class-ds-rest.php`

- Accept `infinite_slider` as a resolved channel transition.
- Include the channel's Infinite Slider spacing, speed, and border-radius values in each zone item payload.
- Leave the existing `infinite_scroll` slide payload independent.

### [MODIFY] `digital-signage/public/js/player.js`

- Detect Infinite Slider at zone startup and build one continuous track from that zone's ordinary image slides.
- Use a single vertical column for portrait zones and a single horizontal row for landscape zones.
- Duplicate the logical sequence enough times to cover the viewport with no empty gap, matching the Motion Primitives behaviour without adding React or a frontend dependency.
- Recalculate after images load and after zone resize/orientation changes.
- Apply the configured channel border radius consistently to every repeated image copy.
- Run one animation loop per zone and clean it up on playlist updates/removal.
- Preserve normal rotation as a safe fallback for mixed/non-image zones.

### [MODIFY] `digital-signage/public/css/player.css`

- Add dedicated Infinite Slider track styles, with full-width portrait images, full-height landscape images, and channel-configurable image border radius.
- Keep existing Infinite Scroll Gallery styles separate.

### [MODIFY] `digital-signage/digital-signage.php`

- Bump the corrective feature release to `2.9.1`.

### [MODIFY] `digital-signage/readme.txt`

- Document Infinite Slider as a channel transition and distinguish it from Infinite Scroll Gallery.

### [MODIFY] `digital-signage.zip`

- Rebuild and byte-verify the installable plugin archive.

### [MODIFY] `system_architecture.md`

- Correct the ownership notes: Infinite Slider consumes ordinary image slides at zone level; Infinite Scroll Gallery remains a separate slide type.

## Corrective verification

- Infinite Slider appears only in the channel transition selector.
- Portrait: one vertical column, full-width images, adjustable vertical spacing, speed, and border radius.
- Landscape: one horizontal row, full-height images, adjustable horizontal spacing, speed, and border radius.
- Short image sequences repeat enough times to avoid blank gaps.
- Playlist polling and orientation changes do not create duplicate loops.
- Mixed-content zones preserve every slide by falling back to normal fade rotation.
- Existing Infinite Scroll Gallery slides retain their stored type and behaviour.
- JavaScript syntax, diff checks, source invariants, ZIP integrity, and ZIP/source byte equality pass before publication.

---

## Superseded plan (kept for change history)

## Scope assumption requiring approval

“Sliding carousel” will be implemented by upgrading the existing **Infinite Scroll Gallery** slide type. Its stored type key (`infinite_scroll`) will remain unchanged so existing gallery slides keep working. Ordinary single-image slides will remain ordinary slides; they will not be automatically merged into a carousel.

## Behaviour to deliver

1. Move transition selection from each slide to its channel. Every slide rendered in a channel uses the channel transition.
2. Rename the existing Infinite Scroll Gallery UI to **Sliding carousel** and keep it as a multi-image, continuously looping slide where multiple images/parts of adjacent images can be visible at once.
3. In portrait zones, size every carousel image to the full zone/page width, preserve its aspect ratio, move vertically, and use a channel-level vertical spacing value.
4. In landscape zones, size every carousel image to the full zone/page height, preserve its aspect ratio, move horizontally, and use a channel-level horizontal spacing value.
5. If a slide is saved with an empty name, generate the next available editable name (`Slide 1`, `Slide 2`, and so on). A user-entered name remains unchanged.
6. Preserve existing installations: old gallery slides remain valid; the current single spacing value is the fallback for both new spacing settings; the global transition remains a fallback until a channel transition is explicitly saved; legacy per-slide transition metadata is ignored.
7. Bump the plugin version, document the feature, test it, and rebuild the root `digital-signage.zip` from the updated plugin directory.

## Files

### [MODIFY] `digital-signage/admin/views/channel-edit.php`

- Add a channel transition selector with `none`, `fade`, `slide`, and `zoom` choices.
- Replace the single gallery spacing control with separate portrait/vertical and landscape/horizontal spacing controls.
- Rename the visible Infinite Scroll Gallery settings section to Sliding carousel settings and explain the orientation-dependent sizing.
- Load legacy/default metadata values safely for existing channels.

### [MODIFY] `digital-signage/admin/views/slide-edit.php`

- Remove the per-slide transition selector.
- Allow an empty submitted title so the server can generate `Slide N`.
- Keep the title field editable on all later edits.
- Rename the visible Infinite Scroll Gallery type and help text to Sliding carousel while retaining the existing internal type key.

### [MODIFY] `digital-signage/includes/class-ds-crud.php`

- Validate and save channel transition metadata.
- Validate and save separate vertical and horizontal carousel spacing metadata.
- Generate the next available `Slide N` title when a slide title is blank, without overwriting later user edits.
- Stop writing per-slide transition overrides and remove obsolete override metadata when an affected slide is saved.

### [MODIFY] `digital-signage/includes/class-ds-rest.php`

- Resolve transition from the slide's channel instead of from slide metadata.
- Fall back to the existing global transition for channels that predate the new setting.
- Return separate vertical and horizontal carousel spacing values, falling back to the legacy single spacing value.

### [MODIFY] `digital-signage/public/js/player.js`

- Apply the channel-resolved transition consistently when rotating channel content.
- Upgrade the existing gallery renderer into the sliding-carousel behaviour without changing its content type key.
- Select vertical or horizontal flow, sizing, spacing, and movement from the actual rendered zone orientation.
- Build enough duplicated carousel content to avoid blank gaps and keep multiple images/adjacent images visible during continuous movement.
- Recalculate carousel dimensions after image load and viewport/orientation resize without creating duplicate animation loops.

### [MODIFY] `digital-signage/public/css/player.css`

- Add/adjust orientation-specific carousel track and image rules.
- Enforce full-width portrait images and full-height landscape images while preserving aspect ratios.
- Apply vertical or horizontal spacing only on the active movement axis.

### [MODIFY] `digital-signage/digital-signage.php`

- Bump the plugin version for this feature release.

### [MODIFY] `digital-signage/readme.txt`

- Update the stable version and document channel transitions, generated slide names, and sliding-carousel controls.

### [MODIFY] `digital-signage.zip`

- Rebuild the installable archive from the final `digital-signage/` directory and verify that its contents match the committed plugin source.

### [NEW] `system_architecture.md`

- Record the resulting ownership boundary: channels own transitions and orientation spacing; slides own content, duration, and editable display names; the player owns orientation-aware carousel layout.

## Verification

- PHP syntax checks for every changed PHP file.
- JavaScript syntax check for the player.
- Save a blank new slide and confirm a unique `Slide N` title is generated and remains editable.
- Confirm existing named slides are not renamed.
- Confirm all slides in a channel receive the same channel transition through the REST payload.
- Confirm existing channels use their old spacing value as both new spacing fallbacks.
- Player tests at portrait and landscape dimensions, including resize/orientation changes, one-image and multi-image carousels, no visible blank loop gap, and no duplicate animation timers.
- Rebuild and inspect `digital-signage.zip`, then verify Git status contains only intended changes before committing and pushing the requested branch.

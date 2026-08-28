# Digital Signage architecture notes

## VIDAA/private smart-TV bootstrap

- `/signage/tv/` is a stable browser entry point; it creates a pairing identity through `/ds/v1/pair/request`, stores the opaque token in localStorage (with a URL-fragment fallback), and checks `/ds/v1/pair/status/{token}` until paired.
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

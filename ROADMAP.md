# Screens byKUTT product roadmap

This map separates shipped 4.5.0 work from the next quality-of-life improvements. It is directional, not a promise that unfinished items are already available.

## P0 — unattended reliability

- Add a controller preflight page that verifies sudo policy, update service, Chrome, XRandR, audio, disk space, and the configured Git remote before deployment.
- Surface update stages and actionable recovery instructions directly in the controller timeline.
- Warn when sleep, special-channel, and content schedules overlap or leave a display without content.
- Add configurable offline/update-failure alerts by email or webhook.

## P1 — faster daily operations

- Clone controller configuration, screen assignments, and schedules to a replacement device.
- Add bulk channel assignment, refresh, restart, tagging, and archive actions with a clear review step.
- Add playlist drag ordering, crossfade, daypart schedules, loudness normalization, and per-track gain.
- Add saved filters, recent items, drafts/approval, undoable trash, and media usage indicators.
- Store license receipts and attribution notes with each music track and warn before a recorded license expiry.

## P2 — visibility and governance

- Add proof-of-play reports by group, screen, campaign, and date with scheduled CSV delivery.
- Add a group audit log for content edits, device actions, access changes, and deletions.
- Add storage quotas, orphaned-media cleanup, and controller/browser compatibility reporting.
- Add role presets for owner, content editor, operator, viewer, and music-only access.

## Designer

- Add autosave history, named versions, safe-area overlays, snapping presets, and reusable brand kits.
- Generate thumbnails and format variants in the background, with progress and retry states.
- Add accessible template tags and filters for industry, campaign, orientation, and content density.

## Music integrations

- Keep local licensed playback as the reliable commercial default with offline caching.
- Add provider adapters only where the provider explicitly permits the intended commercial venue use; paid business-radio services can be evaluated separately.
- Add operating-system audio-device routing when the Ubuntu controller can expose stable sink identifiers safely.

# Screens byKUTT 4.8.1 implementation plan

Status: implemented and verified.

## Outcome

Repair public player and preview routing, make Settings and Designer load only the data they need, simplify slide creation with media-type-specific controls, and make audible video playback reliable on kiosk and browser Screens.

## Changes

1. **Player and preview URLs**
   - Run the direct `/play/{token}`, `/s/{code}`, and `/preview/{channel}` route handler before WordPress canonical redirects.
   - Preserve a valid requested destination after login instead of always sending signage users to `/screens/`.
   - Keep legacy route support and refresh rewrite rules on upgrade.

2. **Settings and Designer performance**
   - Replace the portal's all-sections eager loading with a section dependency map and initialize unused datasets as empty.
   - Avoid media-library filesystem quota scans, schedules, designs, controllers, and heartbeat queries on Settings.
   - Load Designer CSS/JS only on Designer and replace its per-Screen heartbeat lookups with one group-scoped bulk query.
   - Keep authorization checks group-scoped before loading or exposing records.

3. **Simpler Add slide dialog**
   - Replace the dense type selector flow with five clear choices: Image, Video, Website, Text/HTML, and Clock.
   - Enforce progressive disclosure with CSS that cannot be overridden by generic form-label rules.
   - Filter the custom media library and upload accept types to images or videos based on the selected slide type.
   - Show duration only where relevant; for video, default to Play to end and reveal fixed duration only when selected.
   - Keep the slide dialog state when choosing/uploading media, return focus correctly, show the chosen asset, and validate the required source server-side.

4. **Video audio reliability**
   - Explicitly configure video mute/default-mute/volume state from the slide setting.
   - Duck background music only once audible video is actually playing; restore it on ended, pause, error, abort, or slide cleanup.
   - If a browser blocks audible autoplay, show a focused “Enable sound” action and resume the active video after the gesture.
   - Preserve unattended Ubuntu kiosk playback through its existing autoplay launch policy; browser-only Screens receive the standards-compliant gesture fallback.
   - Prevent needless rotation/recreation when a zone contains one item.

5. **Release and verification**
   - Bump the WordPress plugin release to 4.8.1 and add an upgrade note.
   - Add regression coverage for early route interception, login destination preservation, section-aware loading, dialog field visibility/filtering, and video audio lifecycle.
   - Run PHP syntax checks, JavaScript syntax checks, the existing test suite, WordPress plugin checks available in the repository, and the required UI detector.
   - Build a uniquely named 4.8.1 plugin ZIP, verify its version and archive contents, then publish the cleaned source and release artifact to the configured public Git branch.

## Files expected to change

- `wp/includes/class-ds-player.php`
- `wp/includes/class-ds-portal.php`
- `wp/includes/class-ds-auth.php` (only if redirect handling needs a shared helper)
- `wp/public/templates/portal.php`
- `wp/public/js/portal.js`
- `wp/public/js/player.js`
- `wp/public/css/portal.css`
- `wp/public/css/portal-extras.css` or `wp/public/css/ui-refresh.css`
- Plugin version/readme and focused regression tests

## Safety boundaries

- No schema-destructive migration.
- No user media or configuration deletion.
- No change to group authorization semantics.
- No blanket passwordless terminal access or arbitrary remote-command surface.

# Digital Signage channel transitions and sliding carousel — implementation plan

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

# Digital Signage Infinite Slider correction — implementation plan

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

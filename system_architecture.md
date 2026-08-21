# Digital Signage architecture notes

## Channel-owned playback presentation

- A channel owns its slide transition in `ds_transition`. The REST layer resolves that value for every slide in the channel and falls back to the global transition only when the channel has no explicit compatible value.
- `infinite_slider` is a channel transition that consumes the ordinary image slides in each zone as one continuous sequence. It does not create or replace a slide type.
- A channel owns Infinite Slider orientation (`auto`, `vertical`, or `horizontal`), portrait spacing, landscape spacing, speed, and image border radius. Auto uses actual rendered zone dimensions; explicit orientation overrides zone proportions.
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
- Remeasurement converts the current offset to normalized loop progress and reapplies that progress to the new sequence length, avoiding a visible restart after resize or orientation change.
- Animation frames clamp elapsed time to 50 ms so a stalled kiosk browser cannot jump far ahead on recovery. When `prefers-reduced-motion: reduce` is active, the sequence is laid out but its repetitive animation loop is not started.

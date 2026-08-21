# Digital Signage architecture notes

## Channel-owned playback presentation

- A channel owns its slide transition in `ds_transition`. The REST layer resolves that value for every slide in the channel and falls back to the global transition only when the channel has no explicit compatible value.
- A channel owns sliding-carousel background, speed, portrait vertical spacing, and landscape horizontal spacing. Slides only store the ordered carousel attachment IDs.
- The historical slide type key `infinite_scroll` is intentionally stable for existing saved content, although the admin UI and player present it as **Sliding carousel**.

## Slide names

- Slide titles remain normal editable WordPress post titles.
- `DS_CRUD::save_slide()` assigns the first unused `Slide N` title within the channel whenever the submitted title is blank.
- Legacy per-slide transition metadata is ignored by REST and removed when a slide is saved.

## Carousel player lifecycle

- The player determines carousel direction from the rendered zone dimensions, not only from the screen metadata.
- Portrait tracks use full-width images and vertical spacing; landscape tracks use full-height images and horizontal spacing.
- The player repeats a logical image sequence enough times to cover both the viewport and the wrap distance, then moves by one exact sequence length for a seamless loop.
- Every carousel owns one animation frame loop and one resize observer/listener. `stopTimers()` must clean both before a slide or zone is removed to prevent hidden duplicate animation work.

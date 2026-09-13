---
name: Screens byKUTT
description: A quiet operational workspace with unmistakable connection states.
colors:
  canvas: "#081712"
  paper: "#10241d"
  paper-raised: "#152c24"
  ink: "#f8f6ed"
  muted: "#9cafaa"
  line: "rgba(238,246,239,.12)"
  primary: "#e9ff54"
  primary-strong: "#bdde31"
  focus: "#7cc8ff"
  spotify: "#1ed760"
  signal-yellow: "#e9ff54"
typography:
  title:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "46px"
    fontWeight: 750
    lineHeight: 1.05
  body:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "16px"
    fontWeight: 400
    lineHeight: 1.5
  label:
    fontFamily: "system-ui, -apple-system, BlinkMacSystemFont, Segoe UI, sans-serif"
    fontSize: "13px"
    fontWeight: 700
    lineHeight: 1.2
rounded:
  control: "12px"
  card: "16px"
  signal: "24px"
spacing:
  tight: "8px"
  control: "12px"
  panel: "24px"
components:
  button-primary:
    backgroundColor: "{colors.primary}"
    textColor: "{colors.canvas}"
    rounded: "{rounded.control}"
    padding: "10px 18px"
  panel:
    backgroundColor: "{colors.paper}"
    textColor: "{colors.ink}"
    rounded: "{rounded.card}"
    padding: "{spacing.panel}"
---

# Design System: Screens byKUTT

## Overview

**Creative North Star: “The Calm Control Room”**

The signed-in workspace is restrained, compact, and predictable so screen managers can operate it without training. Pairing and reconnect states intentionally change register: they become oversized, high-contrast signals that remain legible and scannable from across a room.

The direction is an editorial split composition generated from concept seed **947**. It favors decisive type, large areas of solid color, dense operational lists, and one clear action at a time.

**Key Characteristics:** deep-forest work surfaces, ivory typography, acid-yellow actions, large QR blocks, and responsive density.

## Colors

Deep forest contains the operational workspace without glare. Warm ivory carries type, acid yellow marks the one primary action, sky blue is the focus color, and green is reserved for healthy connection states.

**The Signal Color Rule.** Acid yellow belongs to primary actions, pairing, and QR emphasis. Spotify green belongs only to Spotify connection and playback controls.

## Typography

**Display and Body Font:** the operating-system sans-serif stack.

**Character:** direct, compact, and reliably rendered on WordPress pages, phones, and Ubuntu kiosks. Pairing codes and countdowns use tabular, heavy numerals for distance legibility.

### Hierarchy

- **Title** (750, responsive 30–46px, 1.05): controller and player context.
- **Body** (400, 16px, 1.5): operational instructions and forms.
- **Label** (700, 13px, 1.2): field labels, metadata, and uppercase connection eyebrows.
- **Pairing code** (850, responsive clamp): the dominant object on connection screens.

## Layout

The portal uses a persistent dark navigation rail, a fluid content canvas, and bordered forest panels. The overview opens with an editorial status hero and then moves into dense operational data. Template galleries move from four columns to three, two, and one at 1050px, 720px, and 460px. Spotify results collapse from two columns to one below 1050px. The designer toolbar moves from four columns to two and then one, keeping at least 620px of canvas height on phones. Connection screens recompose from an editorial two-column split into a centered stack in portrait orientation and use height-led sizing on short, wide displays.

## Elevation & Depth

Depth is ambient and sparse. Portal panels use a dark 20px/60px shadow; visual template artifacts lift farther on hover. QR blocks use a hard offset shadow so the code reads as a physical, high-priority object rather than another card.

**The Flat-by-Default Rule.** Operational surfaces stay flat at rest; stronger depth is reserved for selectable artifacts and QR signals.

## Shapes

Controls use 12px corners, operational cards 16px, and major connection surfaces 22–24px. Borders remain one pixel and neutral. Circular geometry is limited to numbered pairing steps and status indicators.

## Components

### Buttons

- **Shape:** 12px radius with compact horizontal padding.
- **Primary:** acid yellow on deep forest; Spotify actions use Spotify green only within the Spotify surface.
- **Hover / Focus:** a one-pixel lift and a three-pixel sky-blue outline with three-pixel offset.

### Cards / Containers

- **Operational panels:** forest, 16px corners, low-contrast ivory border, low ambient shadow.
- **Template cards:** visual previews with stronger hover lift; metadata stays outside the artwork.
- **QR cards:** white, 22–24px corners, solid-color offset shadow.

### Inputs / Fields

Fields use warm-ivory backgrounds with dark text, 12px corners, minimum 44px touch height, explicit labels, and the shared focus outline. Device and time selectors remain native controls.

### Navigation

Navigation is compact and task-oriented. It becomes horizontally scrollable rather than wrapping unpredictably on small screens. Spotify-only users receive only the Spotify route and their assigned controllers.

## Do's and Don'ts

### Do:

- **Do** keep operational pages calm and status-led.
- **Do** preserve oversized codes and strong QR contrast on every display ratio.
- **Do** use 180–220ms motion only to confirm interaction or state change.

### Don't:

- **Don't** scatter signal yellow across secondary actions or decorative surfaces.
- **Don't** add decorative cards around ordinary settings or table rows.
- **Don't** place Spotify branding or controls outside the isolated Spotify surface.

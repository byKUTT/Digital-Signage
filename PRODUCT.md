# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Digital-signage owners configure controller computers, displays, channels, schedules, media, and access groups. Screen Managers operate only the groups assigned to them. Spotify Users control music only on explicitly assigned controllers.

## Product Purpose

Digital Signage turns WordPress into a multi-tenant control surface for unattended Ubuntu signage controllers and browser-based displays. Success means a controller can be paired without technical knowledge, recover from missing assignments, update safely, and stay understandable from the frontend portal.

## Positioning

One group-scoped frontend joins screen content, controller health, visual design, schedules, pairing, and tightly limited remote device operations without exposing WordPress administration or an arbitrary remote shell.

## Operating Context

The system is used on desktop and mobile browsers while the actual signage screens may be landscape, portrait, unusually wide, or low resolution. Ubuntu controllers run unattended without keyboards or mice. WordPress server time is authoritative for schedules.

## Capabilities and Constraints

- Controllers pair to a group only after a signed-in user confirms the rotating code.
- Controller identity, display assignments, schedules, profiles, and settings survive software updates.
- Remote privileged operations are allow-listed; arbitrary terminal commands are excluded.
- Spotify control is interactive and controller-scoped. Spotify audio is not synchronized with signage visuals or broadcast from the signage player.
- Starter designs must be editable, reusable, and available in landscape and portrait formats.

## Brand Commitments

The product identity is **Screens byKUTT** and it uses `screens.kutt.ee` as its default service URL. The interface should feel modern, direct, and calm while connection states may be more visually expressive.

## Evidence on Hand

The existing WordPress plugin, Ubuntu controller, local Vellum editor, portal, pairing states, screenshot of the current cramped designer toolbar, automated Ubuntu tests, and public Git repository are the source of truth.

## Product Principles

- Make unattended recovery self-explanatory.
- Keep access and ownership controller- and group-scoped.
- Put common actions first and technical detail behind progressive disclosure.
- Preserve device state during updates.
- Prefer visible status and actionable recovery over generic errors.

## Accessibility & Inclusion

Controls must remain keyboard accessible, retain visible focus, meet WCAG AA contrast, and work across mobile, desktop, landscape, and portrait viewports. Reduced-motion preferences must be respected.

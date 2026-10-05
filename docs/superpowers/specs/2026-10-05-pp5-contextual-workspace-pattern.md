# PP5 M6.6 — Contextual workspace pattern

**Date:** 2026-10-05
**Status:** Task 1b pilot; product-owner visual acceptance pending
**Pilot:** Classroom Overview and Students workspace only

## Workspace Context Header

Show the authorized classroom name as the dominant visual identity, with academic year, school, and grade immediately nearby. Use a broad soft blue region, not a decorative card. Keep the existing classroom switcher and local navigation in this region. Later workspace sections can reuse the same shell without inferring a different school or classroom from browser input.

## Task Zone

Represent a real work domain with a short teacher-facing title, an authorized count or status when available, one obvious next action, and a semantic soft surface. Blue identifies student work, sage subject and teaching work, sand score work, and rose important blocked or destructive work. Omit a zone when its domain is unavailable or unauthorized. Avoid speculative destinations and invented analytics.

## Dense Work Surface

Keep rosters, offerings, and score lists in neutral white semantic tables with compact rows. Place long tables in named, keyboard-focusable internal scroll regions. On narrow screens, retain table columns and internal scrolling rather than turning each row into a card. A table remains the primary reading surface; color belongs in context and task zones.

## Contextual Detail / Action Panel

The Students pilot uses a native row disclosure. Its summary is a meaningful action and its panel repeats the selected student's name, code, classroom, and current status. The open row has a structural visual cue. Available actions are composed from current permission and lifecycle hints; their destinations still recheck authority. Opening another row closes the earlier disclosure when JavaScript is available. Escape closes the disclosure and returns focus to its summary. Native disclosure works without JavaScript, with normal Tab order and no focus trap.

## Action hierarchy and progressive disclosure

Use a solid primary action for the main workflow, outlined actions for utilities, and text links for navigation inside a work surface. Destructive actions retain explicit words and the existing destination's confirmation. Show only the initial choice at roster density; reveal detail and less frequent actions on request. Do not expose Enrollment or Placement identifiers or internal permission terms in visible labels.

## Return-to-work focus

Closing a contextual panel keeps the user at the same roster row. Escape returns focus to the row summary. Navigation to a separate authoritative screen retains the existing workspace locator or return path where the destination already supports one; do not invent a client-side history contract. Future in-page mutations should restore focus to the affected row and announce the server-confirmed result.

## Permission-driven composition and server-authoritative mutation

Resolve school and classroom through the authenticated session and existing services. Read projections may combine Student, Enrollment, and Placement for presentation, but their backend models remain separate. The same applies to Subject, Offering, Teaching Assignment, Score Component, and Score. Render action choices only when the current authorization and lifecycle hints permit them. The destination must independently enforce live permission, tenant, status, CSRF, validation, audit, and POST semantics. Never treat a hidden link as authorization. Do not duplicate lifecycle or placement rules in the browser.

This pattern guides Tasks 2–5 only after the product owner accepts the visual and interaction direction. It does not expand Task 1b beyond the two pilot screens.

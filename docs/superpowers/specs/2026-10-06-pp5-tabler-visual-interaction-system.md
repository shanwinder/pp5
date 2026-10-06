# PP5 Tabler visual and interaction system — M6.6 Task 1d

Date: 2026-10-06 (Asia/Bangkok). Production baseline: `efbe9658164d8113de92b8a8555d8d3d738b76bf`. Evaluation source: `f60f12654976652661466104c43d64ec7ba551fd`.

## Decision history

Task 1 piloted a soft pastel government UI and the product owner rejected that visual direction. Task 1b improved the classroom and student workflow, especially same-page context, but did not settle the visual language. Task 1c evaluated Tabler on Dashboard, Classroom Overview and Students. The product owner chose **ADOPT TABLER** for the production application foundation and superseded the pastel-first goal with a **Colorful Professional School Administration UI**. The 2026-10-05 pastel specification remains historical evidence, not a current acceptance criterion.

## Visual contract

Use Tabler 1.6.1 components and utilities for the page shell, navbar, nav, page header, cards, card status strips, badges, buttons, tables, alerts, form controls and responsive layout. Keep the canvas light and data surfaces white. Use saturated accents in small, meaningful places: blue/cyan for students, violet for teaching, amber for scores, green for success or management, and red only for danger. Labels carry status meaning; color supports it. The main dashboard contains only authorized work and real data. Large cards do not need saturated backgrounds or fabricated analytics.

The application shell is one shared Tabler layout. Dashboard, Classroom Overview and Students use Tabler markup directly. Other authenticated pages use the same shell with `app-compat.css` for their existing body markup until migration. Guest and error documents keep their existing local Bootstrap 5.3.8 plus `app.css` for now. No document loads Bootstrap CSS and Tabler CSS together. The `app-compat.css` slice retains old PP5 tokens, form and table semantics, Gradebook presentation, and legacy administration hooks. It excludes the old shell, entry-page styling, dashboard work-area layout and M6.6 pastel pilot. This is migration debt, not the new visual system. Later screen migrations should remove their reliance on compatibility rules and ultimately retire Bootstrap/app.css from guest/error as well.

## Repeated actions and student context

Top-level add/import actions retain text. Dense rows use zero, one or two compact named icon actions as needed; three or more actions belong in one more menu or contextual disclosure. Destructive actions use explicit text in the opened menu. The student roster uses one vertical-dots control per row and a native `<details>` panel in that row. The closed row stays compact. The open panel identifies the selected student and contains visible, permission-driven links for details/history, transfer and withdrawal. Only authorized links are emitted; endpoints remain authoritative. No student lifecycle mutation moves into browser code in Task 1d.

Icon controls have an `aria-label`, visible focus indicator, and decorative SVG with `aria-hidden="true"`. A local help bubble appears on hover and focus; `title` and CSS help provide fallback if JavaScript is unavailable. It is an explanation, not the only discovery path: touch opens a panel with text labels. Escape closes an open student panel and returns focus to its summary. The existing mobile navigation disclosure retains the same Escape/focus behavior. No Tabler/Bootstrap JS or Popper is needed; `app.js` remains the small first-party presentation enhancement.

## Asset and security boundaries

Pin local `@tabler/core@1.6.1` compiled CSS and its MIT license to the exact files/hash from Task 1c. Pin only used Tabler Icons SVGs from `tabler/tabler-icons` release `v3.48.0`, with the release MIT license and per-file hashes in `htdocs/assets/vendor/tabler-icons/README.md`. No CDN, runtime npm, build pipeline, icon font, chart library, or duplicate Bootstrap JS. Tabler CSS includes its Bootstrap foundation.

Presentation templates consume the existing authorized read models. No permission, tenant, CSRF, audit, route, score, schema or migration semantics change. Gradebook JavaScript, Tabulator vendor and state machine remain locked. Shell CSS changes require the complete Gradebook gate and broad authenticated browser review before visual acceptance.

## Acceptance status

Tabler foundation adoption can be reported implemented after regression and authenticated visual checks. **Final colorful visual acceptance: pending product-owner review.**

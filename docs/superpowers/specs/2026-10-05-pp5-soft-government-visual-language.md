# PP5 M6.6 — Soft Government Visual Language

**Date:** 2026-10-05

**Scope:** Presentation pilot based on accepted M6.5 `826a2417a247d9405bdfacc432405d44a61478b2`.
**Status:** Technical pilot; product-owner visual acceptance pending.

## Product-owner correction after Task 1

Task 1 passed engineering and regression checks, but its visual direction was not accepted. The visible change was too small, pastel zoning was not apparent to a normal user, and workflows still felt organized around separate entity pages. M6.6 now means **Soft Government Workspace + Workflow Simplification**. The accepted Task 1 tokens, contrast work, and local asset architecture remain the foundation; this correction does not erase that history.

The Classroom Overview and Students workspace are the only Task 1b pilot. Their classroom identity uses a visibly soft blue band. Work areas use semantic zones: blue for students and classroom context, sage for subjects and teaching, sand for score work and setup attention, and rose for important blocked or destructive states. White neutral tables remain dense for sustained reading. Color supports explicit labels and actions; it never carries state alone. Values may be refined to preserve normal-text contrast.

Each workspace leads with the current classroom, year, and school, then a small number of authorized tasks with one clear next action each. Common student choices appear in a row disclosure so the selected student stays associated with the action area. Existing service and route authority still governs all mutations. Add direct in-page actions only when the current endpoint and permission rules can be reused safely; otherwise the panel explains the choice and links to its authoritative flow. This one-page pilot is a direction test, not a claim that every student task is complete without navigation. Product-owner visual acceptance remains pending.

## Intent and visual inventory

PP5 is a daily work application for Thai school administration. The light interface should feel calm, credible and slightly warm while keeping student rosters, teaching lists and score entry compact. Use charcoal blue-gray text, quiet blue navigation, sage summary support, soft semantic status fills and thin neutral borders. Local/system fonts remain the only font source. A surface earns a border when it contains a distinct task; a table remains a table rather than a collection of cards.

The pilot covers the existing shared login layout, dashboard, classroom workspace, students, subjects/teachers, score setup, Gradebook and My Teaching. Their common foundation is `htdocs/assets/app.css`; Tabulator uses first-party `htdocs/assets/gradebook-grid.css`. The application layout has a sticky topbar and sidebar above 1024 px, then a stacked shell and existing disclosure behavior. The login uses a centered guest surface. Classroom pages share an in-content workspace shell. Administration pages share forms, scrollable tables and status badges. Gradebook has a first-party fallback table and a local pinned Tabulator grid. Those structures and their navigation or score behavior remain authoritative.

The shared app, guest and error layouts append the local `app.css` file modification time to its URL. This ensures the pilot palette reaches returning browsers; the already accepted Gradebook asset versioning remains in place.

## Production token map

The following root tokens are defined in `app.css`. Contrast notes are computed WCAG ratios for the indicated foreground/background pair; decorative and geometry tokens have no text contrast requirement. No page should introduce a raw palette value where one of these roles fits.

| Token | Value | Role and usage | Contrast / accessibility |
| --- | --- | --- | --- |
| `--pp5-background` | `#f3f6f5` | Page canvas, warm cool-gray | Text 10.70:1; muted text 5.50:1 |
| `--pp5-surface` | `#ffffff` | Main content, controls, login and tables | Text 11.64:1 |
| `--pp5-surface-muted` | `#edf2f3` | Read-only and historical areas, neutral badges | Muted text remains above 4.5:1 |
| `--pp5-surface-subtle` | `#f8faf9` | Topbar and sidebar | Muted text 5.71:1 |
| `--pp5-text` | `#263b49` | Body, headings, table data | 10.70:1 on canvas |
| `--pp5-text-muted` | `#526674` | Help text, metadata, secondary labels | 5.50:1 on canvas |
| `--pp5-border` | `#c7d3d8` | Quiet panel and cell boundaries | Decorative; text never relies on it |
| `--pp5-control-border` | `#8296a1` | Inputs and table header rule | Visible control boundary; focus adds a stronger outline |
| `--pp5-primary` | `#365f78` | Primary actions and links | White text 6.85:1; links on white 6.85:1 |
| `--pp5-primary-hover` | `#294b62` | Hover and selected navigation text | White text 9.22:1; on primary soft 7.96:1 |
| `--pp5-primary-soft` | `#e6f0f4` | Active navigation, range controls, quiet hover | Active text 7.96:1; active bar and weight also signal state |
| `--pp5-primary-border` | `#b8cbd5` | Soft blue boundary and breadcrumb underline | Decorative; not sole cue |
| `--pp5-workspace-blue` | `#e5f0f6` | Classroom identity and student work zone | Body text 10.05:1 |
| `--pp5-workspace-sage` | `#e7f1e9` | Subject and teaching work zone | Body text 10.06:1 |
| `--pp5-workspace-sage-border` | `#b7d0bf` | Subject zone boundary | Decorative; not sole cue |
| `--pp5-workspace-sand` | `#f7eedb` | Score work zone | Body text 10.09:1 |
| `--pp5-workspace-sand-border` | `#d9c8a0` | Score zone boundary | Decorative; not sole cue |
| `--pp5-workspace-selected` | `#f0f6f9` | Selected roster row behind contextual actions | Body text 10.67:1; selected summary also changes structure and weight |
| `--pp5-on-primary` | `#ffffff` | Text on solid primary or danger buttons | 6.85:1 on primary; 6.83:1 on danger |
| `--pp5-success` | `#32634c` | Success text and positive badges | 6.06:1 on success fill |
| `--pp5-success-bg` | `#e8f2eb` | Success fill | Paired with success text and explicit wording |
| `--pp5-warning` | `#75551f` | Caution and closed/inactive status text | 6.03:1 on warning fill |
| `--pp5-warning-bg` | `#f9f0dd` | Caution fill | Paired with warning text and explicit wording |
| `--pp5-danger` | `#914248` | Errors, destructive actions, invalid borders | 5.94:1 on danger fill |
| `--pp5-danger-bg` | `#faecec` | Error and destructive hover fill | Paired with danger text, border and explicit wording |
| `--pp5-info` | `#365e70` | Informational or draft status text | 6.16:1 on info fill |
| `--pp5-info-bg` | `#e7f2f5` | Information and saving fill | Paired with info text and status wording |
| `--pp5-focus` | `#075fa8` | 3 px global focus ring; 2 px grid active cell | 6.54:1 against white; 5.65:1 against primary soft |
| `--pp5-table-header` | `#e9f0f2` | Shared and identity column headers | Body text 10.09:1 |
| `--pp5-table-stripe` | `#f9fbfa` | Very light alternate row | Body text remains above 10:1 |
| `--pp5-identity-soft` | `#f3f7f8` | Gradebook frozen identity column | Distinguishes identity without a saturated block |
| `--pp5-summary-soft` | `#edf4ee` | Gradebook summary columns | Sage grouping; read-only text remains legible |
| `--pp5-selected-soft` | `#e4eff5` | Gradebook selected cells | Text 9.96:1; visible boundary remains required |
| `--pp5-space-1` | `0.25rem` | Small icon-free gaps and badge padding | Retains dense work layout |
| `--pp5-space-2` | `0.5rem` | Compact control and navigation spacing | Retains target size through control min-height |
| `--pp5-space-3` | `0.75rem` | Navigation, table-adjacent and action gaps | Retains dense work layout |
| `--pp5-space-4` | `1rem` | Standard field and mobile spacing | Avoid oversized mobile padding |
| `--pp5-space-6` | `1.5rem` | Desktop content and surface spacing | Sections remain distinct |
| `--pp5-space-8` | `2rem` | Guest surface top gap | Applied only where space is available |
| `--pp5-radius-sm` | `0.25rem` | Buttons, controls, navigation, badges, tables | Small, formal radius |
| `--pp5-radius-md` | `0.5rem` | Distinct surfaces and empty states | Avoid nested rounded cards |
| `--pp5-shadow-sm` | `0 1px 3px rgb(38 59 73 / 5%)` | Light surface separation | Never conveys interactivity by itself |
| `--pp5-sidebar-width` | `15rem` | Desktop shell column | Preserves accepted layout |
| `--pp5-header-height` | `4rem` | Sticky topbar and focus scroll margin | Preserves accepted layout |
| `--pp5-content-width` | `90rem` | Main content cap | Preserves table workspace |
| `--pp5-form-width` | `44rem` | Form reading width | Labels and controls remain associated |
| `--pp5-table-cell-padding` | `0.625rem 0.75rem` | Shared table density | Gradebook has its own locked cell geometry |
| `--pp5-font-family` | `system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Tahoma, sans-serif` | Local Thai-capable platform fonts | No network dependency |
| `--pp5-status-text` | `var(--pp5-text)` by default; semantic text token in variants | Contextual alert and badge foreground | Semantic pairs meet 4.5:1; explicit text conveys meaning |
| `--pp5-status-bg` | `var(--pp5-surface-muted)` by default; semantic fill token in variants | Contextual alert and badge background | Never used without text and border |

The contextual status aliases are set on alert/badge variants. The following Bootstrap adapter tokens also run in production; each value delegates to the PP5 source token or the same explicit role.

| Adapter token | Value | Role and usage | Contrast / accessibility |
| --- | --- | --- | --- |
| `--bs-body-font-family` | `var(--pp5-font-family)` | Bootstrap body font | Local/system Thai font stack |
| `--bs-body-color` | `var(--pp5-text)` | Bootstrap body text | 10.70:1 on canvas |
| `--bs-body-bg` | `var(--pp5-background)` | Bootstrap body canvas | Paired with PP5 body text |
| `--bs-border-color` | `var(--pp5-border)` | Bootstrap separators | Decorative only |
| `--bs-link-color` | `var(--pp5-primary)` | Bootstrap links | 6.85:1 on white |
| `--bs-link-hover-color` | `var(--pp5-primary-hover)` | Bootstrap link hover | 9.22:1 on white |
| `--bs-secondary-color` | `var(--pp5-text-muted)` | Bootstrap secondary text | At least 5.3:1 on common surfaces |
| `--bs-border-radius` | `var(--pp5-radius-sm)` | Bootstrap control radius | Decorative only |
| `--bs-primary` | `var(--pp5-primary)` | Bootstrap primary alias | White text 6.85:1 |
| `--bs-primary-rgb` | `54, 95, 120` | Bootstrap RGB alias of primary | Keep synchronized with `--pp5-primary` |
| `--bs-btn-color`, `--bs-btn-hover-color`, `--bs-btn-active-color` | Primary/danger: `var(--pp5-on-primary)` or semantic danger on soft danger hover; secondary: `var(--pp5-text)`; link: primary | Button foreground states | Solid primary and danger white text exceed 4.5:1 |
| `--bs-btn-bg`, `--bs-btn-hover-bg`, `--bs-btn-active-bg` | Primary/hover, surface/muted, or danger/danger-soft PP5 role | Button fills | Text and border remain visible |
| `--bs-btn-border-color`, `--bs-btn-hover-border-color`, `--bs-btn-active-border-color` | Matching primary, control, muted or danger PP5 role | Button boundaries | Secondary controls have an explicit border |
| `--bs-btn-disabled-color`, `--bs-btn-disabled-bg`, `--bs-btn-disabled-border-color` | Muted text/surface/border for secondary; semantic solid for primary/danger | Disabled state | Disabled appearance plus native disabled semantics |
| `--bs-table-color` | `var(--pp5-text)` | Shared table text | Above 10:1 on table fills |
| `--bs-table-bg` | `var(--pp5-surface)`, header, stripe or selected role by context | Shared and fallback table fill | Text and row boundaries remain visible |

Button-scoped adapter values derive from PP5 primary, danger and neutral roles; they are not a separate palette. Their state-specific expressions live in `app.css` to keep the locally pinned Bootstrap sheet unchanged.

## Component rules

- **Typography and hierarchy:** Body uses 1 rem with 1.6 line height; headings use 1.625/1.25/1.125 rem and weight 650. School identity and page title carry hierarchy through weight and spacing. Thai labels must wrap rather than truncate. No remote or bundled font is added.
- **Shell and navigation:** The subtle topbar/sidebar separate from the canvas with one border. Active navigation has a pale blue fill, blue leading rule, stronger text and `aria-current="page"`; hover has its own quiet fill. Classroom navigation retains its underline and `aria-current`.
- **Surfaces:** White content surfaces use a thin border and at most the 5% small shadow. The dashboard work-area list remains open and compact. Empty states use text and a border. Avoid adding a card around every section.
- **Buttons:** Solid muted blue is the main action; bordered neutral is routine; muted brick is destructive; text links are contextual navigation. Disabled controls retain a boundary and readable text. Permission and action meaning come from existing markup and server authority.
- **Forms:** Labels are bold enough to scan; inputs have visible blue-gray borders, a strong focus outline, and clear error borders and text. Read-only controls use a muted surface and dashed border; disabled controls keep a visible boundary. Help and placeholder text remain readable.
- **Tables:** Shared tables use a blue-gray header, a header rule, subtle alternate rows and contained horizontal scroll. Hover helps scanning but never communicates a state alone. Cell padding stays compact. A table never becomes a card grid on small screens.
- **Statuses and alerts:** Success, information, caution and danger each pair a soft fill with high-contrast text. Existing Thai status labels and explicit alert wording remain the source of meaning. Alerts use a leading rule; badges use a soft border. Neutral statuses retain a neutral fill.
- **Gradebook:** Frozen identity is neutral blue-gray; score headers are pale blue; summary headers/cells are pale sage; editable score cells remain nearly white. Historical rows retain their explicit row-type wording and muted fill. Saving and error have soft fills plus existing status text; an error retains a strong inset boundary. Selected ranges use a pale blue fill plus border, and the active/editor cell keeps its strong single focus boundary. Do not alter Tabulator vendor files, column widths, row heights, holder size, editor padding, scroll behavior, or state machine.

## Accessibility and anti-patterns

WCAG normal-text target is at least 4.5:1 for text-bearing combinations; the computed values above are for the production hex values. Test focus by keyboard and after pointer-triggered programmatic moves: the global ring and Gradebook boundary must remain visible. Selection, read-only, saving, success and error always have text, structural or boundary cues in addition to hue. Keep `aria-current`, live status, control labels and focus management unchanged. Check 390, 768, 1024, 1280 and 1440 px for document overflow, clipped controls and Gradebook internal scrolling.

Avoid neon or saturated Bootstrap colors, dominant dark navy, gradients, glass, large shadows, excessive pills, decorative motion, page-specific hex patches, unnecessary cards, and whitespace that reduces information density. This light palette is the source of truth for later M6.6 work; dark mode is outside this task.

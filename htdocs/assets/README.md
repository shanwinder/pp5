# PP5 UI foundation and application shell (Milestone 6, Tasks 2–3)

`View::page()` layouts load local Bootstrap 5.3.8 CSS followed by `app.css`.
Only the app layout loads `app.js` (deferred). Guest/error layouts need no shell
script. Dashboard, system school index, and academic-year list now use the shell.
Other full-document views await later tasks. HTMX and `gradebook.js` stay page-specific; `View::render()` remains raw rendering.

`headAssets`, `scripts`, and layout selection remain trusted server-controlled
inputs. Escape dynamic values inside asset partials; never copy request values
or database display text into these HTML slots.

## Authenticated presentation context

After the existing authentication/context middleware, `AppUiContextService::build()`
uses the established session, `AuthorizationService`, `GradebookReadService`, and
`SchoolRepository` to build plain display data. Pass it as `ui` to `View::page()`;
without it, the app layout retains its minimal Task 1 document behavior. Never
pass browser-selected context, role, or tenant values to this service. Navigation
is a convenience; backend permission/resource checks remain authoritative.

The service checks live permissions on every build, omits empty groups, and
keeps SCHOOL and SYSTEM navigation separate. Controllers select a server-defined
`currentKey` and use returned permission flags for page actions. The layout owns
identity escaping, accessible navigation, the skip link, and POST/CSRF logout.

Task 3 links directly to accessible `/gradebook/{id}` resources, including
historically readable offerings. Task 4 owns `/gradebooks`; replace the temporary
resource list with that landing link only when the route exists, keeping live
resource checks. The dashboard's existing links moved into the shell; its welcome
content is otherwise unchanged.

## CSS hooks

- `--pp5-*` tokens cover semantic colors, status foreground/background pairs,
  spacing, radii, shadow, Thai-capable system fonts and layout dimensions.
  Control borders have a separate contrast token from decorative borders.
- `.pp5-shell` uses topbar/sidebar/content grid areas, switching to a single
  column below 64rem. `.pp5-content` permits wide tables without page overflow.
- `.pp5-page-header`, `.breadcrumb`, `.pp5-actions`, `.pp5-filter-bar`,
  `.pp5-surface`, `.pp5-form`, `.pp5-field`, `.pp5-field-error`, and
  `.pp5-empty-state` support future screen migrations.
- Bootstrap form controls and primary/secondary/danger/link buttons use PP5
  tokens. `.pp5-alert` and `.pp5-badge` accept `--success`, `--warning`, `--danger`,
  and `--info` class suffixes. Always provide status text as well as color.
- `.pp5-table-scroll` wraps `.pp5-table`; give keyboard-scrolling containers
  `tabindex="0"` and an accessible name. `.pp5-gradebook` adds optional dense
  table/sticky identity/save-status hooks without changing score behavior.
- `.pp5-skip-link` links to a focusable content target. Use explicit labels,
  semantic headings and status/error roles in the consuming markup.

## Shell enhancement contract

Use a native `button[data-nav-toggle]` with class `pp5-nav-toggle` and
`aria-controls` pointing to one `[data-nav-panel]` ID. Start with navigation
visible and `aria-expanded="true"`; give the panel `tabindex="-1"` for fallback
focus. An optional `button[data-nav-close].pp5-nav-close` closes the panel.

Without app.js, navigation stays visible and menu controls stay hidden. With
enhancement, narrow screens use an in-page disclosure: open moves focus to the
first navigation link, Escape/close restores trigger focus, and same-page links
focus their destination. Native Tab is never trapped. Breakpoint changes reset
visibility and prevent focus remaining in hidden content. Keep the 64rem
breakpoint aligned in CSS and JS. There is no network, authorization, business
validation, or confirmation behavior in this script.

## Verification without Node

Run `htdocs/vendor/bin/phpunit tests/Feature/UiAssetTest.php tests/Feature/UiLayoutTest.php`.
The contrast tests verify text at 4.5:1 and focus/control borders at 3:1.
For the isolated browser matrix, run:

```sh
php -S 127.0.0.1:18886 tests/Browser/ui-foundation.php
```

Open `http://127.0.0.1:18886/`. It checks 390/768/1024/1440px fixtures and a
no-enhancement fixture with no database or session. Fixtures are not app routes.

The Task 3 production-layout fixture uses long/hostile Thai names and checks
320/390/768/1024/1440px, keyboard focus, and the no-app.js fallback:

```sh
php -S 127.0.0.1:18887 tests/Browser/ui-navigation.php
```

Open `http://127.0.0.1:18887/`. This is also an isolated fixture, not an
authenticated MAMP application session. Live authorization is covered by
`tests/Feature/UiNavigationTest.php` against the test database.

On this MAMP Mac, the built-in JavaScriptCore parser checks syntax without
executing the scripts:

```sh
/System/Library/Frameworks/JavaScriptCore.framework/Versions/A/Helpers/jsc \
  -e 'for (const path of arguments) { checkSyntax(path); print("Syntax OK: " + path); }' \
  -- htdocs/assets/app.js htdocs/assets/gradebook.js tests/Browser/ui-foundation.js \
     tests/Browser/ui-navigation.js tests/Browser/gradebook-autosave.js
```

Other environments can use their browser parser. No build tool is required.

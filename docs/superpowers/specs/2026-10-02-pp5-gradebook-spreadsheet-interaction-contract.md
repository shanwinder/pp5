# PP5 Gradebook spreadsheet interaction contract

**Date:** 2026-10-02
**Status:** Task 10.6d clarified interaction contract; product-owner manual acceptance remains separate.

This specification is mandatory for later Gradebook work, including Task 11. The principle is **Online first, Excel familiar**. The grid handles interaction and display. The PP5 server remains authoritative for permission, tenancy, lifecycle, score validation, NULL versus zero, transactions, audit, and summaries.

## Selection and focus

Only score components and enrollment rows form the selectable rectangle. Identity, row-type and summary cells never enter a normal score selection. A current read-only or historical score can be selected, navigated and copied. Write commands over any non-writable member reject the whole rectangle.

The **anchor** is the score cell where a range starts. The **extent** is its actual current pointer or Shift+Arrow endpoint. The **active cell** receives the next grid command. The **top-left origin** is the normalized rectangle's first score cell and owns multi-cell paste and the typing anchor after range commands. **Browser focus** is the DOM cell currently receiving keys; **editor focus** is the input and owns native text editing. These concepts must not be inferred from a normalized range's last cell.

Single selection A1 has anchor, extent, active and top-left A1. Forward A1→C3 has anchor A1, extent/active C3, top-left A1. Reverse C3→A1 has anchor C3, extent/active A1, top-left A1. After a successful range clear, fill or paste, the selected rectangle remains and the top-left is the active typing cell. Browser focus returns there when the Gradebook still owns the interaction. A deliberately focused external control retains focus through an async response.

One click on a score cell selects and focuses it without opening an editor. Dragging score cells extends the score-only rectangle; focus moves to the final extent after the drag, never during pointer movement. Shift+Arrow extends or shrinks from the actual extent in all four directions. Escape outside the editor removes the range while retaining the active cell and its usable keyboard focus. Tab into the Gradebook host establishes the first score cell in the first row if one exists. Empty grids remain safely focusable. At steady state only the active rendered score cell has `tabindex="0"` among cells.

## Keyboard and editor

Outside the editor, unmodified Arrow keys move one navigable score cell in the requested direction and stop at score boundaries. Navigation works in read-only and historical rows. Tab and Shift+Tab move horizontally among score components; at the horizontal boundary they exit the grid.

**Direct entry** starts when printable typing on a selected writable score cell opens a replacement editor containing only the typed character. Further printable characters remain in the same input until a commit or cancel boundary. In direct entry, Left, Right, Up and Down **commit and move** to the next writable score cell in that direction. Up and Down skip read-only or historical rows. At an Arrow boundary, the commit completes and focus stays on the current score cell; Arrows never enter summary columns. Enter/Shift+Enter commit and move down/up. Tab/Shift+Tab commit and move right/left, exiting the grid at a horizontal boundary. Rapid `5 → Right → 6 → Right → 7` and `5 → Down → 6 → Down → 7` must not wait for each serialized server response or lose keys.

**Explicit edit** starts when double-click, F2, or Enter on a selected writable score cell opens its existing value with the caret at its end. In explicit edit, Left and Right move the **caret** and do not save or navigate. Up and Down commit and move vertically. Enter/Shift+Enter commit and move down/up. Tab/Shift+Tab commit and move right/left, exiting the grid at a horizontal boundary. Post-edit movement targets writable score cells.

In either editor mode, Escape cancels without a request, restores the prior value, and focuses the cell so ordinary Arrow navigation works immediately. Backspace/Delete are native text editing. Text selection copy and paste are native editor operations. Grid clipboard handlers never intercept an open editor. Composition is protected from premature command handling. Normal blur retains the single-cell save behavior with no duplicate commit after a key-driven finish.

Writing requires a current enrollment, score permission, writable lifecycle, and no permission-loss or uncertain lock. Selection and navigation do not imply permission to write. A pending single save makes a range write wait or reject visibly; it must never race or silently disappear. A rejected single score leaves attempted text and its error visible, with old server summary intact and immediate correction possible.

## Clipboard and range commands

Outside an editor, copy sends the selected score rectangle as TSV, including read-only or historical scores. Blank is an empty field; zero is non-empty. It never includes identity or summaries. A single selected cell is the paste origin. A multi-cell selection uses its top-left score cell as the paste origin in either drag direction. A pasted matrix must fit all score rows and columns and be entirely writable. No clipping, partial mutation, or optimistic committed display is allowed. A 2×2 or 3×3 matrix is one Task 8 batch request keyed by stable enrollment and component IDs. Blank fields request NULL; zero variants request real zero.

Delete and Backspace outside an editor clear the entire selected writable rectangle with explicit blank values through the batch endpoint, including 1×1. Fill uses the same atomic infrastructure for one scalar. A mixed current/history or read-only range rejects a write in full while remaining copyable. After successful paste the selected rectangle becomes the target rectangle; after successful clear/fill the existing rectangle remains. The top-left becomes the active typing cell and receives focus if the Gradebook owns the command. A Fill or Clear control is Gradebook-owned even though its form is outside the grid; an unrelated external control is not.

The only write endpoints are the existing single-cell score POST and Task 8 batch POST. The server's success header and authoritative score and summary response must be validated before UI reconciliation. Known batch rejection makes no committed display change; an uncertain outcome (409, timeout/network uncertainty, committed response with invalid refresh) locks further writes pending reload and is never automatically retried. Late responses must not overwrite newer text, range, focus or summaries. Navigation and copy may remain available while writes are locked.

## Scope and verification

Tabulator is a local pinned renderer. Native uncontrolled clearing, drag fill, formulas, row/column insertion or deletion, arbitrary sort/filter/reorder, Undo/Redo and browser totals remain disabled or non-authoritative. The 10.6a single visible editor boundary, stable row height and stable column width remain required. First-party Gradebook adapter and theme URLs carry a content-change-derived version; vendor URLs remain local and pinned.

Tests must exercise pointer clicks and drags, keyboard shortcuts, and clipboard in a real browser without focusing cells or assigning internal active state in the test. Deterministic synthetic event tests may supplement that proof and must be labeled as such. The isolated PHP fixture does not prove authenticated application integration. A real MAMP Gradebook route with a safe authenticated session is a separate acceptance attempt; product-owner manual spreadsheet-feel approval is the final gate.

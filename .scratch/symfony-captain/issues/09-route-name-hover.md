Status: resolved

# 09 — Route name hover in PHP URL-generation calls

**What to build:** Show information about a route when the user hovers over a route name string in a recognized URL-generation call (`generate`, `generateUrl`, `redirectToRoute`). This is the third sibling of go-to-definition (issue 04) and completion (issue 08): the same trigger positions, now offering details instead of a jump or a choice.

**Design decisions (sibling of issues 04 and 08):**

- **Trigger scope — identical to go-to-definition.** Hover fires only while the cursor is inside the first string argument of a recognized URL-generation call, reusing `RouteNameFinder::findAt` exactly. No lenient fallback for half-typed source — hover is triggered by the user deliberately, on complete code, so a completion and a hover at the same cursor position must always agree.
- **Contents.** A markdown `MarkupContent` with the route name, `<METHOD> <path>` (same presentation as workspace symbols and completion), and the controller string when one exists. Routes with an unresolvable controller still hover; unknown route names and non-route positions produce no hover (`null`, never an error).
- **Range.** The hover range is the route name string literal (including its quotes), so the client highlights exactly the reference. Requires a byte-offset → LSP position converter alongside the existing `Position::toByteOffset`.
- **Response.** An LSP `Hover` (`contents` + `range`), or `null` when the cursor is not on a known route reference, when the project is not Symfony, or when the request targets a non-PHP file.

**Acceptance criteria:**
- [ ] Hover shows route name, `<METHOD> <path>`, and controller string for `generate`, `generateUrl`, `redirectToRoute`.
- [ ] Hover stays null outside those positions (other strings, non-first arguments, non-PHP files, or a non-Symfony project).
- [ ] Unknown route names produce no hover; routes without a resolvable controller still hover.
- [ ] The hover range covers the route name string literal.
- [ ] Unit tests at the hover seam cover valid calls, unknown routes, out-of-scope positions, and controller-less routes.
- [ ] LSP-seam tests send `textDocument/hover` and assert the returned `Hover`.
- [ ] Manual test in Zed: hovering a route name shows route info, and stays silent elsewhere.

## Completed

- `RouteNameHover` builds an LSP `Hover` when `RouteNameFinder::findAt` finds a route reference: markdown contents with the route name, `<METHOD> <path>`, and controller string (omitted when empty), plus the range of the string literal so the client highlights the reference. Unknown route names and non-route positions return null.
- `Position::toPosition` (byte offset → LSP line/character in UTF-16 units) added alongside the existing `Position::toByteOffset`.
- LSP advertises `hoverProvider` and answers `textDocument/hover` with a `Hover` or null (never an error).
- `RouteNameHoverTest` (5 tests) and `LspServerTest` (4 new tests) cover valid calls, unknown routes, out-of-scope positions, controller-less routes, and the non-Symfony case. End-to-end verified against the built bundle.

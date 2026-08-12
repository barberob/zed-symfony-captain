Status: resolved

# 08 — Route name completion in PHP URL-generation calls

**What to build:** Suggest Symfony route names while the user types the first string argument of a recognized URL-generation call (`generate`, `generateUrl`, `redirectToRoute`). Selecting a suggestion inserts the route name. This is the sibling of go-to-definition (issue 04): the same trigger positions, now offering choices instead of a jump.

**Blocked by:** 02 — Build the route index from Symfony console and reflection.

**Design decisions (from grilling session):**

- **Trigger scope — strict.** Completion fires only while the cursor is inside the first string argument of a recognized URL-generation call, including the empty string right after the opening quote (`generate('`). No completion anywhere else; a completion and a go-to-definition at the same cursor position must always agree.
- **Parsing.** Reuse the exact `PhpToken`-based analysis of `RouteNameFinder` on clean code. When tokenization throws a `ParseError` (source is half-typed, e.g. `generate('use…`), fall back to a line-scoped backward scan: a recognized method name followed by `(` and an opening quote before the cursor, with only whitespace between. Multi-line broken calls may be missed — that is an acceptable "no completion." Extract the cursor→byte-offset helper (currently private in `RouteNameFinder`) for reuse.
- **Item shape.** `label` = raw route name; `kind` = `CompletionItemKind.Constant`; `detail` = `<METHOD> <path>` (same presentation as workspace symbols); `documentation` = controller string (e.g. `App\Controller\UserController::index`). No `textEdit` in this iteration.
- **Filtering.** Return all route names, sorted alphabetically; let the client fuzzy-filter. Do not pre-filter by prefix server-side.
- **Trigger characters.** Register `'` and `"` as `triggerCharacters` so the suggestion list appears on the opening quote.
- **Response.** `CompletionList` with `isIncomplete: false`. Empty list (never an error) when not at a route reference or when the project is not Symfony.

**Acceptance criteria:**
- [ ] Completion fires inside the first string argument of `generate`, `generateUrl`, `redirectToRoute`, including the empty string after the opening quote.
- [ ] Completion stays silent outside those positions (cursor on other strings, on non-first arguments, in non-PHP files, or in a non-Symfony project).
- [ ] The lenient fallback returns suggestions on half-typed, tokenizer-invalid source (`generate('use…`).
- [ ] Each completion item has `label`, `kind` (Constant), `detail` (`<METHOD> <path>`), and `documentation` (controller string).
- [ ] All route names are returned, sorted alphabetically; routes without a resolvable controller are still completable.
- [ ] `'` and `"` are advertised as trigger characters.
- [ ] Unit tests at the completion-detector seam cover valid calls, empty-string trigger, broken/half-typed source, out-of-scope positions, and non-route strings.
- [ ] LSP-seam tests send `textDocument/completion` and assert the returned `CompletionList`.
- [ ] Manual test in Zed: suggestions appear on `generate('`, filter fuzzy, insert cleanly, and stay silent in non-Symfony files.

## Completed

- `RouteNameCompleter` builds `textDocument/completion` items from the route index when the cursor is on a route reference, sorted alphabetically, with `label` = route name, `kind` = `Constant`, `detail` = `<METHOD> <path>`, and `documentation` = controller string.
- Detection reuses `RouteNameFinder::findAt` on tokenizable source. The PHP tokenizer does not raise `ParseError` for half-typed calls like `generate('use…` (it emits a trailing `T_ENCAPSED_AND_WHITESPACE` token instead), so the line-scoped backward scan runs only when the source is genuinely half-typed (tokenization failed or ends inside an unterminated string) — keeping completion silent inside comments and plain strings of a complete file, in line with the strict trigger scope.
- `Position::toByteOffset` extracted from `RouteNameFinder` and shared; `RouteNameFinder::findOrNull()` reports tokenization failure; `Route::methodsLabel()` shared with `WorkspaceSymbols`.
- LSP advertises `completionProvider` with `triggerCharacters: ["'", '"']` and always answers with a `CompletionList` (`isIncomplete: false`), empty when the cursor is not on a route reference or the project is not Symfony.
- `RouteNameCompleterTest` (12 tests) and `LspServerTest` (4 new tests) cover valid calls, the empty-string trigger, half-typed source, out-of-scope positions, non-route strings, and the non-Symfony case. End-to-end verified against the built bundle.

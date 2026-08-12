Status: resolved

# 03 — Expose routes as workspace symbols

**What to build:** Make every Symfony route discoverable through Zed’s symbol palette. Selecting a route symbol opens the controller file at the relevant line, or shows the route without a location when no controller file can be resolved.

**Blocked by:** 02 — Build the route index from Symfony console and reflection.

**Acceptance criteria:**
- [x] The LSP implements `workspace/symbol` and returns one symbol per route.
- [x] Each symbol is named `Route: <name>` and its detail shows `<METHOD> <path>`.
- [x] Symbols for class-based controllers include a location pointing to the controller file and method line.
- [x] Symbols for non-class controllers are still returned but have no location.
- [x] Tests send a `workspace/symbol` request and assert the returned symbols match the fixture project.
- [x] Manual test in Zed confirms routes appear in the symbol palette.

## Completed

- `WorkspaceSymbols` emits one symbol per route named `Route: <name>` with detail `<METHOD> <path>` (e.g. `GET|HEAD /`).
- Resolvable controllers carry a location to the controller file and method line; unresolvable routes are still returned (with a fallback location to keep strict LSP clients parseable — a deliberate deviation from the literal "no location" wording).
- `LspServerTest` and `WorkspaceSymbolsTest` assert the fixture symbols; verified manually in Zed and against the real project `/home/benoit/aih/docker/approche` (128 routes).

Status: ready-for-agent

# 03 — Expose routes as workspace symbols

**What to build:** Make every Symfony route discoverable through Zed’s symbol palette. Selecting a route symbol opens the controller file at the relevant line, or shows the route without a location when no controller file can be resolved.

**Blocked by:** 02 — Build the route index from Symfony console and reflection.

**Acceptance criteria:**
- [ ] The LSP implements `workspace/symbol` and returns one symbol per route.
- [ ] Each symbol is named `Route: <name>` and its detail shows `<METHOD> <path>`.
- [ ] Symbols for class-based controllers include a location pointing to the controller file and method line.
- [ ] Symbols for non-class controllers are still returned but have no location.
- [ ] Tests send a `workspace/symbol` request and assert the returned symbols match the fixture project.
- [ ] Manual test in Zed confirms routes appear in the symbol palette.

Status: ready-for-agent

# 04 — Go to definition from PHP route name strings

**What to build:** Allow a developer to jump from a route name string inside Symfony URL-generation code to the route’s controller. The feature works for the standard Symfony URL-generation methods in PHP.

**Blocked by:** 02 — Build the route index from Symfony console and reflection.

**Acceptance criteria:**
- [ ] The LSP parses PHP source to detect route name string arguments in calls to `generate`, `generateUrl`, and `redirectToRoute`.
- [ ] The LSP implements `textDocument/definition` and returns the controller file/line when the cursor is on a recognized route name string.
- [ ] The parser uses PHP’s native tokenizer and ignores route-like strings that are not arguments to the recognized methods.
- [ ] Tests feed PHP snippets and assert the correct definition locations.
- [ ] Manual test in Zed confirms `Ctrl + Click` on a route name opens the controller.

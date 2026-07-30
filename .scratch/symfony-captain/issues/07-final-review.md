Status: ready-for-agent

# 07 — Final review and end-to-end manual verification

**What to build:** Close the MVP by running the full test suite, reviewing the diff, and verifying the extension in Zed against a real Symfony project.

**Blocked by:** 06 — Distribute the LSP via GitHub releases and add CI.

**Acceptance criteria:**
- [ ] All PHPUnit tests pass, including unit and integration tests.
- [ ] A code review is performed against the project standards.
- [ ] The extension is loaded in Zed and workspace symbols work on a real Symfony project.
- [ ] Go-to-definition works on route name strings in PHP files of a real Symfony project.
- [ ] The route index refreshes correctly after saving a controller or route configuration file.
- [ ] The extension stays silent and produces no errors when opened in a non-Symfony PHP project.

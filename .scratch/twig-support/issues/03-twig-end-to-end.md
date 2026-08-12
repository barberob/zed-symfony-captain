# 03 — Twig end-to-end

**What to build:** Wire the Twig finder into the language server so a route reference in a Twig template gets the full experience: go to definition jumps to the controller, typing a route name offers completion, and hovering shows route info. Files ending in `.twig` or `.twig.html` are treated as Twig templates and routed to the Twig finder; PHP files keep using the PHP finder. A Twig fixture file and end-to-end protocol tests cover the behaviour, and the Zed extension registers the Twig language so the language server actually attaches to templates.

**Blocked by:** 02 — Twig route reference finder.

**Status:** ready-for-agent

- [ ] Files ending in `.twig` or `.twig.html` are handled with the Twig finder; `.php` files continue to use the PHP finder.
- [ ] Go to definition on a route name inside `path()`/`url()` in a Twig template opens the controller.
- [ ] Completion on a route reference in a Twig template returns the sorted completion list; an empty list outside route references.
- [ ] Hover on a route reference in a Twig template shows the route name, method and path, and controller.
- [ ] Silence is preserved: nothing in non-Symfony projects or at non-route positions.
- [ ] A `.twig` fixture file exists and end-to-end protocol tests cover definition, completion, and hover (including the silent cases).
- [ ] The extension registers the Twig language so Zed attaches the language server to templates.

# 02 — Twig route reference finder

**What to build:** The Twig half of the finder seam. A lexer-based finder that detects route references — `path()` and `url()` with a positional first string argument — in Twig templates using Twig's own lexer, so detection is as precise as the PHP tokenizer side. It recognizes both quote styles, works in output tags and logic blocks, ignores method calls like `app.request.path('…')`, handles the empty string after the opening quote, and falls back to a line-scoped scan on half-typed source so completion keeps working while the user types. Byte offsets of the string literal are exposed for cursor and hover-range purposes.

**Blocked by:** 01 — Route reference finder seam.

**Status:** ready-for-agent

- [ ] The finder implements the `RouteReferenceFinder` seam.
- [ ] Detects `path()` and `url()` with single- and double-quoted route names in both output tags (`{{ … }}`) and logic blocks (`{% set u = path('…') %}`).
- [ ] Ignores method access like `app.request.path('…')`.
- [ ] Handles the empty string right after the opening quote.
- [ ] Returns suggestions on half-typed, lexer-invalid source (`{{ path('use…`) via the line-scoped fallback.
- [ ] Exposes the byte range of the route name string for cursor and hover-range use.
- [ ] `twig/twig` is a dev dependency; unit tests at the detector seam cover the cases above.

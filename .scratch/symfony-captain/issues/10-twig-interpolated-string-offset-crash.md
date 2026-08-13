# 10 — Twig finder byte-offset walker crashes on interpolated strings

**What to build:** Fix `TwigRouteNameFinder::byteOffsets()` so templates using Twig interpolated strings — `"#{expr}"` inside a double-quoted string, e.g. `map(p => "#{p.code} #{p.libelle}")` — are parsed without the offset walker drifting and eventually crashing the language server process.

**Status:** needs-triage

**Symptoms:** opening or scanning a template that contains an interpolated string throws `ValueError: strpos(): Argument #3 ($offset) must be contained in argument #1 ($haystack)` from `nextTagStart()`. The error is uncaught, so the whole PHP LSP process exits. Reproduced on `/home/benoit/aih/docker/approche/templates/demande_intervention/_item.index.html.twig`. This is a pre-existing bug (it predates the removed-route scanner) and also affects hover, completion, and diagnostics on such templates.

**Root cause:** the lexer consumes the quotes of a double-quoted string silently — no token represents them — so the byte walker lands one byte behind for the first interpolation (`"#` misread as `#{`) and the error accumulates across the file until `strpos()` is called with an offset past the end.

**Fix (already in the working tree, uncommitted):** the walker now mirrors the lexer's consumption rules — a stray `"` is skipped as trivia unless it opens the `STRING` token that lexed next, and `STRING` tokens are measured with Twig's own `REGEX_STRING` / `REGEX_DQ_STRING_PART`. `find()` also catches `\Throwable` so no single template can kill the server again.

**Acceptance criteria:**
- [ ] `find()` returns correct byte offsets for templates with interpolated strings (route references before and inside interpolations).
- [ ] A full scan of a real project's `templates/` directory never crashes.
- [ ] Regression tests cover an interpolation followed by a route reference and a route reference inside an interpolation.
- [ ] Full suite green.

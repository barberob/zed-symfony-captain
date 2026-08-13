# 01 — Route reference finder seam

**What to build:** Introduce a shared `RouteReferenceFinder` seam that both the PHP and Twig sides will implement. The existing PHP finder becomes the first implementation: it gains the half-typed completion logic (currently living in the completion builder) and everything downstream — completion, hover, and the LSP's per-request dispatch — depends on the seam instead of on the concrete PHP finder. This is a pure refactor: PHP features behave identically, verified by the existing suite staying green.

**Blocked by:** None — can start immediately.

**Status:** resolved

- [x] A `RouteReferenceFinder` interface exists covering the capabilities the features need: finding occurrences in a source, finding the occurrence at a cursor, and deciding whether a cursor is at a route reference (including half-typed source).
- [x] The PHP finder implements the interface; its half-typed fallback logic moves onto it from the completion builder.
- [x] The completion and hover builders receive the finder by constructor.
- [x] The LSP selects the PHP finder for PHP files.
- [x] All existing PHP features (go to definition, completion, hover) behave identically; the full test suite passes.

## Comments

Resolved by `cea44a6` — refactor: introduce RouteReferenceFinder seam for route references. Full suite green (89 tests, 245 assertions).

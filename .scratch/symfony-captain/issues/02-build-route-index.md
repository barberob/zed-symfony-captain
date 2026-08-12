Status: resolved

# 02 — Build the route index from Symfony console and reflection

**What to build:** Implement the data layer that discovers Symfony routes and maps each controller to a file location. The LSP must execute Symfony’s router debug command against a project and use PHP reflection to resolve controller class and method positions.

**Blocked by:** 01 — Bootstrap and launch the language server.

**Acceptance criteria:**
- [x] The LSP can run `bin/console debug:router --format=json` in the project root and parse the JSON output.
- [x] The route index stores, for each route, at minimum: name, HTTP methods, path, and controller string.
- [x] A controller resolver uses the project autoloader and PHP reflection to map `Class::method` to a file path and start line.
- [x] A minimal Symfony fixture project with sample controllers and routes is included under tests.
- [x] Integration tests verify that the index contains the expected routes and locations for the fixture project.
- [x] Routes whose controller is not a resolvable class/method are kept in the index but without a file location.

## Completed

- `RouteProvider` runs `bin/console debug:router --format=json` in the project root; `DebugRouterParser` parses the JSON (name, methods, path, `defaults._controller`).
- `ControllerResolver` loads the project autoloader and uses reflection to map `Class::method` to file + start line.
- `tests/Fixture/Project` is a minimal Symfony skeleton with controllers; `RouteProviderTest` asserts expected routes and locations, and that unresolvable controllers stay in the index without a location.

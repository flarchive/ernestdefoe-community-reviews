# Community Reviews: working rules

- Commit straight to `main`. Never open a pull request on this repo. Commit subjects are `feat:` / `fix:`.
- `js/dist` is committed and is what forums load: after any change under `js/src`, `npm run build` in `js/` (from a fresh `npm ci`) and commit the bundle.
- Nothing is released or announced until Ernest has tested it on dev and said so; every change goes to the Command Center first. New extension: dev + ernestdefoe.dev only.
- The spec: `~/github/scopes/reviews/mybb-parity.md` (feature-for-feature with MyBB Community Reviews; nothing dropped), `scope.md`, mockups in `~/github/scopes/reviews/mockups/` (approved v3 look). Build plan + status: `~/github/scopes/reviews/build-plan.md`.
- Out of the box = MyBB: every extra (helpful, verified, pros/cons, compare, rich results, approval queues, one-per-member) is OFF.
- Floxum 100 from the first commit (`~/claude-memory/floxum_100_checklist.md`); run pre-floxum + ship-check before any release.
- Tests: `composer test:setup` then `composer test`. Without DB_DRIVER they run on SQLite; MySQL and PostgreSQL runs need the driver set and confirmed.

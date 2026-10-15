# wtgspain

[![CI](https://github.com/MooCot/wtgspain/actions/workflows/ci.yml/badge.svg)](https://github.com/MooCot/wtgspain/actions/workflows/ci.yml)

A REST API on Laravel 12: asynchronous import of housing offers from suppliers, finding the cheapest current offer, safe booking.

**Git repository:** https://github.com/MooCot/wtgspain

## Installation and running

```bash
git clone <repo-url> wtgspain
cd wtgspain
cp .env.example .env
# edit .env: fill in DB_PASSWORD and DB_ROOT_PASSWORD (empty in .env.example on purpose)

docker compose build
docker compose run --rm app composer install
docker compose up -d

docker compose exec -u www-data app php artisan key:generate
docker compose exec -u www-data app php artisan migrate --force
docker compose exec -u www-data app php artisan db:seed
```

The app is available at `http://localhost:8080`.

**Swagger UI** (interactive API testing): `http://localhost:8080/api/documentation`. The schema is auto-generated from PHP attributes on the controllers (`darkaonline/l5-swagger`); `L5_SWAGGER_GENERATE_ALWAYS=true` in `.env` keeps it up to date on every request in dev mode. Manual regeneration: `docker compose exec -u www-data app php artisan l5-swagger:generate`.

> `-u www-data` is required for any `exec` that touches `storage/` (artisan, grumphp). Without it the command runs as `root` (the container's default, needed by `docker-entrypoint.sh` for `chown` on the bind mount) and breaks permissions for the php-fpm worker, which always writes as `www-data` — a typical symptom: `/docs`/`/api/documentation` suddenly returns 404.

## Commands

| Action | Command |
|---|---|
| Migrations | `docker compose exec -u www-data app php artisan migrate` |
| Seeders (supplier-a, supplier-b) | `docker compose exec -u www-data app php artisan db:seed` |
| Queue worker | starts automatically as the `queue` service; manually — `docker compose exec -u www-data app php artisan queue:work` |
| Tests | `docker compose exec -u www-data app php artisan test` |
| Static analysis + architecture boundaries + style | `docker compose exec -u www-data app vendor/bin/grumphp run` (also runs automatically on `git commit`/`git push` via git hooks) |
| Mutation testing | `docker compose exec app vendor/bin/infection --threads=2 --only-covering-test-cases` (manual, NOT in git hooks — a run over `app/Application`+`app/Infrastructure/Persistence/Eloquent/Repositories` takes ~15 min due to Docker/MySQL overhead) |

GrumPHP here is just the orchestrator; the gate itself is three independent tools:

- **PHPStan + Larastan** (`phpstan.neon`, level 6) — static type analysis: wrong argument/return types, references to nonexistent properties/methods, unreachable code. Larastan adds understanding of Laravel specifics (facades, `$model->property` via Eloquent magic, config arrays).
- **Deptrac** (`deptrac.yaml`) — hexagonal layer boundaries. Checks that `Models` depends on nothing, `Application` depends only on `Models`, `Infrastructure` depends on `Application`+`Models`, `Providers` depends on everything; any dependency in the reverse direction (e.g. `Application` reaching into `Infrastructure`) fails the gate.
- **Pint** (`pint.json`, Laravel preset) — code formatting (PSR-12 + Laravel conventions); run as `--test` (check only, no auto-fix — so the hook doesn't change files behind the developer's back).

**Mutation testing** (`infection/infection` + PCOV, `infection.json5`) — separate from GrumPHP, since it's too slow for a git hook. It checks not "do the tests pass" but "can the tests actually fail at all": it introduces small mutations into the code (`>` → `>=`, removing a method call, inverting a condition) and sees whether the existing assertions catch it. A mutant that "survives" (tests pass even on broken code) is a gap in the tests, not in the code.

## Stack

PHP 8.3 · Laravel 12 · MySQL 8.0 · Redis 7 (queue) · Nginx 1.27 — all via Docker Compose.

## Import idempotency

Two layers of protection — application-level (the fast path) and DB-level (a guarantee for edge cases):

- **Import:** the pair `(supplier, external_import_id)` is a unique composite index in the DB. A repeated `POST /api/imports` with the same `external_import_id` first looks up the existing record via `RegisterImportUseCase`; if found, it returns it without creating a new row **and without re-queuing** (checked via Eloquent's `wasRecentlyCreated`: `ProcessImportJob` is only dispatched for a freshly created Import).
- **Offer:** the pair `(supplier, offer.external_id)` is also a unique index. A repeated occurrence of the same `external_id` (even in a new import) updates the existing record rather than duplicating it.
- **Property:** the identity key is `code`, so the same housing object mentioned across different imports/suppliers doesn't produce duplicates.
- **Narrow race window** (two identical requests nearly simultaneously, both passing the application-level "doesn't exist" check before the first commits its INSERT): the DB unique index physically prevents a duplicate from appearing, and `EloquentImportRepository`/`EloquentOfferRepository`/`EloquentPropertyRepository` catch the `UniqueConstraintViolationException` from that INSERT and re-fetch the row that was just committed (the race winner) instead of failing with a `500`. So the application-level check is the fast path for the normal sequential case, while the real guarantee of uniqueness is the DB constraint, and the code reacts to it correctly rather than ignoring it.

## Concurrency protection for booking (the last unit)

`Offer.available_units` is an `unsigned` column. The decrement is executed as **a single atomic SQL statement**:

```sql
UPDATE offers SET available_units = available_units - 1 WHERE id = ? AND available_units > 0 AND expires_at > NOW()
```

`available_units > 0` and `expires_at > NOW()` are the same category of fact (the Offer's validity state, not a request parameter), so both are in the one atomic `WHERE` rather than a separate upfront check (which would open a race window). Previously `expires_at` wasn't checked here — booking an expired offer directly was possible despite search correctly hiding it (fixed, covered by `testItRefusesToDecrementAnExpiredOffer`/`testItReturnsConflictWhenOfferHasExpired`).

The `UPDATE` with its `WHERE` is wrapped in a DB transaction together with creating the `Reservation` (`EloquentReservationRepository::createForOffer`). If neither condition holds, no row is updated (`affected_rows = 0`) — the method returns `false`, the repository throws `OfferUnavailableException` ("not available for booking" — generic, since the reason can be either of the two), the HTTP layer returns `409`.

With two simultaneous requests for the last unit, MySQL (InnoDB) serializes the competing `UPDATE` statements via row-level locking: whichever one reaches the row first decrements and commits; the second sees `available_units = 0` already and correctly gets `affected_rows = 0` — no over-booking. There's no dedicated test with parallel processes (not required by the assignment) — atomicity is verified at the affected-rows behavior level in `tests/Feature/Infrastructure/OfferRepositoryTest.php`.

## Edge case: partial import on a failure mid-batch

If one offer in a batch fails (bad structure, or e.g. a value exceeding a DB column's bounds), the `Import` gets a `failed` status — but the offers processed **before** the failure remain recorded in the system; processing isn't rolled back. This is intentional behavior, not an oversight:

**Domain.** `Property` (identity — `code`) and `Offer` (identity — `(supplier, external_id)`) have a longer lifecycle than any single `Import` — both are legitimately updated from later, independent imports. `Import` isn't a transactional boundary around them, but a fact of a one-time batch intake — essentially an event/log, not an owning container.

**Why rolling back the whole batch is worse, not better.** A batch of 20 offers, one of which is broken, would under all-or-nothing roll back all 20 — including 19 valid records about real housing — over one unrelated bad row in the same feed.

**The assignment itself anticipates this.** The example response for `GET /api/imports/{id}` (item 5) already contains separate `total_offers`/`processed_offers` fields — a direct signal that partial completion is expected, normal behavior, not an anomaly to be hidden behind a full rollback.

**Why not a "Property+Offer aggregate" instead.** `Offer.available_units` is mutated by two independent actors with no mutual coordination — import writes the initial value (`ImportOffersUseCase`), booking decrements it via a separate atomic `UPDATE` (`CreateReservationUseCase`, see above). If `Offer` belonged to one "aggregate" together with `Import`, you'd either have to drag booking across that same boundary (breaking the atomic decrement, which is deliberately thin for the sake of the last-unit concurrency guarantee), or admit the aggregate boundary is inconsistent. The correct atomicity boundary here is narrower than "the whole Import" — at the level of a single record (`Property`+`Offer` together, so as not to leave an orphan `Property` with no `Offer` — see `MODEL-wtgspain.md`), not wider.

**Transparency instead of concealment.** The `error` message explicitly states: `"(N of M offers were already processed and remain in the system.)"` — partial processing isn't hidden, it's described literally.

**Fix: skip one offer, not abort the rest of the batch.** Previously a `try/catch` in `ImportOffersUseCase` wrapped the entire `foreach`, so a broken offer aborted processing of the rest of the batch entirely — valid offers positioned AFTER it in the same batch were never even attempted, purely due to array position. This contradicted `MODEL-wtgspain.md`'s own P3 ("a violation → the offer **is skipped**", not "the rest of the batch is cancelled"). Fixed: the `try/catch` is now inside the loop — `InvalidOfferPayloadException` (a structural problem with one offer) skips only that offer and continues the loop; a generic `\Exception` (an infrastructure failure) still aborts the rest, since continuing after it is unsafe. Covered by the test `testItProcessesValidOffersAfterASkippedOneInTheSameBatch`. Note: `InvalidOfferPayloadException` is currently unreachable via the real `POST /api/imports` — `StoreImportRequest` already blocks the one condition that throws it (`property` not an array) at the `422` level — the fix covers this path defensively (a future validation change, a different `ProcessImportJob` producer), not an active bug in the current API surface.

## Architecture

Hexagonal (lightweight version, no separate Domain layer) — details, layer boundaries and rationale: `CLAUDE.md`.

## Review: domain boundaries beyond the assignment

An external review raised 4 points. None of them violate the literal text of the assignment (checked point-by-point against the original) — but the letter of the assignment isn't enough; the domain (a housing booking aggregator) also needs to be looked at separately, and there the picture is mixed.

| # | Finding | Against the assignment | Against the domain |
|---|---|---|---|
| 1 | An expired offer (`expires_at` in the past) can be booked directly via `POST /api/offers/{offer}/reservations` — only `available_units` is checked | not required (assignment item 6 ties `expires_at > now()` only to `GET /api/properties`) | **a real hole — fixed.** `available_units > 0` and `expires_at > now()` are the same category of fact (a derived validity condition of the Offer at the moment of the action), but the first became an invariant enforced on write (F5), the second remained a predicate only for search (F4). The boundary between them was drawn not by the nature of the fact but by where the parameter came from — inconsistent with the model's own definition of an invariant in `MODEL-wtgspain.md` ("holds across ALL reachable states"). Now both conditions are in the one atomic `UPDATE` (see "Concurrency protection for booking" above) |
| 2 | Search hides an expired offer, a direct POST doesn't | the same fact as #1 | the same fact as #1 |
| 3 | A failed import leaves partially recorded offers in the system | not required (the opposite, actually: `total_offers`/`processed_offers` in the assignment explicitly anticipate partial progress) | **not a hole.** `Offer`/`Property` outlive any single `Import` and get updated from different imports over time — this isn't one transactional boundary with the batch. All-or-nothing would roll back valid offers over one bad row in the same batch. `processed_offers`/`error` honestly report how much went through |
| 4 | Offer upsert (`(supplier, offer.external_id)`) ignores `sent_at` — an older import processed later (queue/retry) silently overwrites fresher data | not required (`sent_at` in the assignment is just metadata, not a guard) | **the most serious one.** A classic out-of-order delivery risk for systems ingesting supplier snapshots through a queue — not hypothetical, real channel-manager integrations explicitly guard against this by comparing `incoming.sent_at > stored.last_applied_sent_at` |

**Conclusion:** matching the assignment and domain correctness are two different checks, and matching the assignment alone (fully passed here) isn't enough to claim there are no problems. Of the four review points, one (#4) is a legitimate production risk regardless of the assignment, one (#1/#2) is an inconsistency in the model's own domain model (`MODEL-wtgspain.md`), and only one (#3) is genuinely unfounded even as a domain complaint.

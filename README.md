# wtgspain

REST API на Laravel 12: асинхронний імпорт пропозицій житла від постачальників, пошук найдешевшої актуальної пропозиції, безпечне бронювання.

**Git-репозиторій:** _TODO — посилання на віддалений репозиторій з історією комітів_

## Стек

PHP 8.3 · Laravel 12 · MySQL 8.0 · Redis 7 (черга + кеш) · Nginx 1.27 — усе через Docker Compose.

## Кешування

Redis (`CACHE_STORE=redis`, окрема БД-індекс від черги — `REDIS_CACHE_DB=1`):

- **Supplier lookup** (`EloquentSupplierRepository::findByCode`) — TTL 1 година. Статичні дані (2 seed-постачальники, практично не міняються), читається на кожен `POST /api/imports`.
- **Пошук `GET /api/properties`** (`EloquentPropertyRepository::searchWithBestOffer`) — Redis cache tags (`SEARCH_CACHE_TAG`), ключ включає всі критерії пошуку, TTL 5 хв — лише страхувальна сітка. Активна інвалідація: `EloquentOfferRepository` фляшить тег на кожен запис у `available_units`/ціну (`updateOrCreate`, `decrementAvailableUnits`), без прямого зв'язку між репозиторіями — Offer знає лише назву тега, не викликає Property-репозиторій. Первісний варіант (голий TTL 30с без інвалідації) був недостатній саме для цього домену: "остання одиниця" — критичний сценарій (заради нього й будувався атомарний `C4`-декремент), і 30с застарілої "доступності" в кеші систематично призводили б до фантомних 409 на бронюванні. Інвалідація не бачить запис в обхід репозиторію (пряме `DB::table()->update()`) — це свідома межа стратегії, покрита тестом.

## Встановлення і запуск

```bash
git clone <repo-url> wtgspain
cd wtgspain
cp .env.example .env
# відредагувати .env: заповнити DB_PASSWORD і DB_ROOT_PASSWORD (пусті в .env.example навмисно)

docker compose build
docker compose run --rm app composer install
docker compose up -d

docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed
```

Застосунок доступний на `http://localhost:8080`.

**Swagger UI** (інтерактивне тестування API): `http://localhost:8080/api/documentation`. Схема автогенерується з PHP-атрибутів у контролерах (`darkaonline/l5-swagger`); `L5_SWAGGER_GENERATE_ALWAYS=true` у `.env` тримає її актуальною при кожному запиті в dev-режимі. Ручна регенерація: `docker compose exec app php artisan l5-swagger:generate`.

> Якщо `/docs` чи `/api/documentation` раптом віддає 404 — ймовірно, `storage/api-docs/` створився під `root` (наприклад після ручного `docker compose exec app ...` без `--user www-data`), а php-fpm-воркер пише як `www-data`. Фікс: `docker compose exec app chown -R www-data:www-data storage/api-docs` (або просто `docker compose restart app` — `docker-entrypoint.sh` перевстановлює права на весь `storage/` при старті).

## Команди

| Дія | Команда |
|---|---|
| Міграції | `docker compose exec app php artisan migrate` |
| Seeders (supplier-a, supplier-b) | `docker compose exec app php artisan db:seed` |
| Queue worker | піднімається автоматично як сервіс `queue`; вручну — `docker compose exec app php artisan queue:work` |
| Тести | `docker compose exec app php artisan test` |
| Статичний аналіз + межі архітектури + стиль | `docker compose exec app vendor/bin/grumphp run` (запускається й автоматично на `git commit`/`git push` через git-хуки) |

## Ідемпотентність імпорту

Два шари захисту — прикладний (швидкий шлях) і БД-рівень (гарантія на межових випадках):

- **Import:** пара `(supplier, external_import_id)` — унікальний композитний індекс у БД. Повторний `POST /api/imports` з тим самим `external_import_id` спершу шукає існуючий запис через `RegisterImportUseCase`; якщо знайдено — повертає його без створення нового рядка **і без повторної постановки в чергу** (перевірка через Eloquent `wasRecentlyCreated`: `ProcessImportJob` диспетчиться лише для щойно створеного Import).
- **Offer:** пара `(supplier, offer.external_id)` — теж унікальний індекс. Повторна поява того самого `external_id` (навіть у новому імпорті) оновлює існуючий запис, не дублює.
- **Property:** ключ ідентичності — `code`, тому один і той самий об'єкт житла, згаданий у різних імпортах/постачальників, не породжує дублікатів.
- **Вузьке вікно гонки** (два ідентичні запити майже одночасно, обидва проходять прикладну перевірку "не існує" ДО того, як перший закомітить INSERT): unique-індекс у БД не дасть з'явитися дублікату фізично, а `EloquentImportRepository`/`EloquentOfferRepository`/`EloquentPropertyRepository` ловлять `UniqueConstraintViolationException` з цього INSERT і повторно вибирають щойно закомічений (переможцем гонки) рядок замість падіння в `500`. Тобто прикладна перевірка — швидкий шлях для звичайного послідовного випадку, а справжній гарант унікальності — БД-обмеження, і код на нього коректно реагує, а не ігнорує.

## Concurrency-захист бронювання (остання одиниця)

`Offer.available_units` — `unsigned`-колонка. Декремент виконується **одним атомарним SQL-запитом**:

```sql
UPDATE offers SET available_units = available_units - 1 WHERE id = ? AND available_units > 0
```

Це не «прочитати → перевірити в PHP → записати» (класична TOCTOU-вразливість), а єдиний `UPDATE` з `WHERE`-охоронцем, обгорнутий у DB-транзакцію разом зі створенням `Reservation` (`EloquentReservationRepository::createForOffer`). Якщо `available_units` вже `0`, жодного рядка не оновлюється (`affected_rows = 0`) — метод повертає `false`, `CreateReservationUseCase` кидає `OfferUnavailableException`, HTTP-шар повертає `409`.

При двох одночасних запитах на останню одиницю MySQL (InnoDB) серіалізує конкуруючі `UPDATE`-запити через row-level lock: перший, що дістанеться рядка, декрементує й комітить; другий бачить вже `available_units = 0` і коректно отримує `affected_rows = 0` — без перевищення резерву. Окремого тесту з паралельними процесами немає (не вимагалось ТЗ) — атомарність перевірена в `tests/Feature/Infrastructure/OfferRepositoryTest.php` на рівні affected-rows поведінки.

## Архітектура

Гексагональна (легка версія, без окремого Domain-шару) — деталі, межі шарів і причини рішень: `CLAUDE.md`.

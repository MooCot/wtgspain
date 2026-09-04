# wtgspain

REST API на Laravel 12: асинхронний імпорт пропозицій житла від постачальників, пошук найдешевшої актуальної пропозиції, безпечне бронювання.

**Git-репозиторій:** _TODO — посилання на віддалений репозиторій з історією комітів_

## Стек

PHP 8.3 · Laravel 12 · MySQL 8.0 · Redis 7 (черга) · Nginx 1.27 — усе через Docker Compose.

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

## Команди

| Дія | Команда |
|---|---|
| Міграції | `docker compose exec app php artisan migrate` |
| Seeders (supplier-a, supplier-b) | `docker compose exec app php artisan db:seed` |
| Queue worker | піднімається автоматично як сервіс `queue`; вручну — `docker compose exec app php artisan queue:work` |
| Тести | `docker compose exec app php artisan test` |
| Статичний аналіз + межі архітектури + стиль | `docker compose exec app vendor/bin/grumphp run` (запускається й автоматично на `git commit`/`git push` через git-хуки) |

## Ідемпотентність імпорту

- **Import:** пара `(supplier, external_import_id)` — унікальний композитний індекс у БД. Повторний `POST /api/imports` з тим самим `external_import_id` шукає існуючий запис через `RegisterImportUseCase`; якщо знайдено — повертає його без створення нового рядка **і без повторної постановки в чергу** (перевірка через Eloquent `wasRecentlyCreated`: `ProcessImportJob` диспетчиться лише для щойно створеного Import).
- **Offer:** пара `(supplier, offer.external_id)` — теж унікальний індекс. Повторна поява того самого `external_id` (навіть у новому імпорті) оновлює існуючий запис (`updateOrCreate`), не дублює.
- **Property:** ключ ідентичності — `code` (`firstOrCreate`), тому один і той самий об'єкт житла, згаданий у різних імпортах/постачальників, не породжує дублікатів.

## Concurrency-захист бронювання (остання одиниця)

`Offer.available_units` — `unsigned`-колонка. Декремент виконується **одним атомарним SQL-запитом**:

```sql
UPDATE offers SET available_units = available_units - 1 WHERE id = ? AND available_units > 0
```

Це не «прочитати → перевірити в PHP → записати» (класична TOCTOU-вразливість), а єдиний `UPDATE` з `WHERE`-охоронцем, обгорнутий у DB-транзакцію разом зі створенням `Reservation` (`EloquentReservationRepository::createForOffer`). Якщо `available_units` вже `0`, жодного рядка не оновлюється (`affected_rows = 0`) — метод повертає `false`, `CreateReservationUseCase` кидає `OfferUnavailableException`, HTTP-шар повертає `409`.

При двох одночасних запитах на останню одиницю MySQL (InnoDB) серіалізує конкуруючі `UPDATE`-запити через row-level lock: перший, що дістанеться рядка, декрементує й комітить; другий бачить вже `available_units = 0` і коректно отримує `affected_rows = 0` — без перевищення резерву. Окремого тесту з паралельними процесами немає (не вимагалось ТЗ) — атомарність перевірена в `tests/Feature/Infrastructure/OfferRepositoryTest.php` на рівні affected-rows поведінки.

## Архітектура

Гексагональна (легка версія, без окремого Domain-шару) — деталі, межі шарів і причини рішень: `CLAUDE.md`.

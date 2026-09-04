# CLAUDE.md — wtgspain

Bootstrap-контекст проєкту (завантажується щосесії).

## Що це

REST API на Laravel для тестового завдання: асинхронний імпорт пропозицій житла від постачальників, пошук найдешевшої актуальної пропозиції, безпечне бронювання.

**Не frontend-завдання.** Фокус оцінки: структура БД, Laravel-практики, SQL-запити, черги, транзакції, автотести.

**Орієнтовний час виконання: 4 години** — рішення має бути прагматичним, без штучної overengineering (без DDD/Clean Architecture/зайвих абстракцій).

## Стек

- PHP 8.2+
- Laravel 11 або 12
- MySQL 8+
- Черга: **Redis**
- **Docker Compose (власний, з нуля)** — сервіси: `app` (php-fpm 8.2+), `nginx`, `mysql`, `redis`, `queue` (той самий образ що app, `php artisan queue:work`)

## Інструменти якості коду

- **Статичний аналіз:** [PHPStan](https://phpstan.org/) + [Larastan](https://github.com/larastan/larastan), рівень ~6-8.
- **Code style:** [Laravel Pint](https://laravel.com/docs/pint) (входить у скелет Laravel 11/12, нуль конфігурації).
- **Git hooks (pre-commit):** [GrumPHP](https://github.com/phpro/grumphp) — YAML-конфіг (`grumphp.yml`), запускає Pint (`--test`) + PHPStan (+ за потреби `composer validate`, `phpunit`) перед комітом.

Встановлення (після scaffold Laravel):
```
composer require --dev phpstan/phpstan larastan/larastan phpro/grumphp
./vendor/bin/grumphp git:init
```

## Сутності (проектування структури — на розсуд)

- **Supplier** — постачальник пропозицій
- **Property** — об'єкт житла (унікальність за `code`)
- **Offer** — пропозиція постачальника на конкретні дати
- **Import** — факт імпорту даних
- **Reservation** — бронювання пропозиції

Структура таблиць, зв'язки, індекси, назви моделей — довільні, проектуються самостійно.

**Seeder:** два постачальники — `supplier-a`, `supplier-b`.

## Endpoints

### `POST /api/imports` — імпорт пропозицій

Запит:
```json
{
  "supplier": "supplier-a",
  "external_import_id": "import-2026-09-01-001",
  "sent_at": "2026-09-01T10:00:00Z",
  "offers": [
    {
      "external_id": "offer-a-10001",
      "property": { "code": "BCN-0001", "name": "Apartment near Sagrada Familia", "city": "Barcelona" },
      "check_in": "2026-10-10",
      "check_out": "2026-10-15",
      "max_guests": 4,
      "price": 72500,
      "currency": "EUR",
      "available_units": 2,
      "expires_at": "2026-09-10T23:59:59Z"
    }
  ]
}
```

Логіка:
- Валідація структури + перевірка існування постачальника (Form Request).
- Створити запис Import, поставити обробку в чергу (Job), одразу повернути `202` з `{"data": {"id": ..., "status": "pending"}}`.
- Обробка офферів — **всередині Job**, не в HTTP-запиті.

**Правила ідемпотентності (критично):**
- `supplier + external_import_id` — унікальна пара. Повторне надсилання того самого імпорту НЕ створює дублікат і НЕ перезапускає обробку.
- `supplier + offer.external_id` — унікальна пара. Якщо offer з таким external_id вже існує (в іншому імпорті) — його дані оновлюються, не дублюються.
- `property.code` — find-or-create.
- `sent_at` — час формування імпорту постачальником (не час отримання).
- Статуси Import: `pending → processing → completed | failed`.

### `GET /api/imports/{import}` — статус імпорту

```json
{
  "data": {
    "id": 15,
    "supplier": "supplier-a",
    "external_import_id": "import-2026-09-01-001",
    "sent_at": "2026-09-01T10:00:00Z",
    "status": "completed",
    "total_offers": 20,
    "processed_offers": 20,
    "error": null,
    "created_at": "2026-09-01T10:00:02Z",
    "completed_at": "2026-09-01T10:00:04Z"
  }
}
```
`total_offers`, `processed_offers`, `error`, `completed_at` — реалізація полів на власний розсуд.

### `GET /api/properties` — пошук житла

```
GET /api/properties?city=Barcelona&check_in=2026-10-10&check_out=2026-10-15&guests=2&page=1
```

Пропозиція актуальна, якщо:
- дати збігаються з параметрами пошуку;
- `max_guests >= guests`;
- `available_units > 0`;
- `expires_at > now()`;
- `city` збігається, якщо переданий.

**Важливо:** пошук найдешевшої пропозиції, сортування, пагінація — **на рівні БД** (SQL/query builder), НЕ через збір усіх записів і групування в PHP-колекціях.

Відповідь:
```json
{
  "data": [
    {
      "code": "BCN-0001",
      "name": "Apartment near Sagrada Familia",
      "city": "Barcelona",
      "best_offer": { "id": 125, "supplier": "supplier-a", "price": 72500, "currency": "EUR", "available_units": 2, "expires_at": "2026-09-10T23:59:59Z" }
    }
  ]
}
```
Плюс пагінація: `next`, `prev`, `per_page`.

### `POST /api/offers/{offer}/reservations` — бронювання

```json
{
  "client_reference": "web-order-9f782b1c",
  "customer_name": "John Smith",
  "customer_email": "john@example.com"
}
```

`201 Created` зі створеним бронюванням.

**Concurrency-захист (обов'язково пояснити в README):** механізм, що запобігає двом одночасним бронюванням останньої одиниці (`available_units`). Кандидати: pessimistic lock (`lockForUpdate()` в транзакції), atomic `UPDATE ... WHERE available_units > 0` з перевіркою affected rows, або DB-рівневий constraint. Окремий тест із паралельними процесами не обов'язковий — достатньо пояснення механізму.

## Архітектура — гексагональна, легка версія

Два шари (без окремого Domain — рішення свідоме, щоб не суперечити вимозі ТЗ уникати зайвих абстракцій): **Application** (ports + use-case сервіси) та **Infrastructure** (адаптери). Плюс **Providers** — composition root, єдиний, кому дозволено бачити обидва шари.

```
app/
  Application/
    Imports/
      ImportOffersUseCase.php
      Ports/SupplierRepository.php, PropertyRepository.php, OfferRepository.php, ImportRepository.php
    Properties/
      SearchPropertiesUseCase.php
    Reservations/
      CreateReservationUseCase.php
      Ports/ReservationRepository.php
  Infrastructure/
    Persistence/Eloquent/Models/       — Supplier, Property, Offer, Import, Reservation
    Persistence/Eloquent/Repositories/ — Eloquent*Repository implements *Port
    Http/Controllers|Requests|Resources/
    Queue/Jobs/ProcessImportJob.php    — тонкий адаптер, кличе ImportOffersUseCase
  Providers/
    RepositoryServiceProvider.php      — composition root, біндить Port → Eloquent-реалізацію
```

**Правило напрямку залежностей:** Application нічого не знає про Illuminate\Database\Eloquent чи Illuminate\Http — лише про власні Ports. Infrastructure знає про Application (реалізує його Ports, викликає його UseCase). Providers — єдиний виняток, знає про обидва (composition root, точка збірки шарів докупи).

**HTTP-контролер і Queue Job рівноправні** — обидва "driving adapters" в Infrastructure, обидва лише викликають UseCase з Application, ніколи навпаки.

**Enforcement — Deptrac** (`depfile.yaml`, `qossmic/deptrac`), таск у GrumPHP:
```yaml
layers:
  - name: Application
    collectors: [{type: className, regex: ^App\\Application\\.*}]
  - name: Infrastructure
    collectors: [{type: className, regex: ^App\\Infrastructure\\.*}]
  - name: Providers
    collectors: [{type: className, regex: ^App\\Providers\\.*}]
ruleset:
  Application: []                          # не бачить Infrastructure — лише власні Ports
  Infrastructure: [Application]            # адаптери реалізують Ports Application-шару
  Providers: [Application, Infrastructure] # composition root — легітимний виняток
```

Кожне правило межі супроводжується коментарем-поясненням "чому" (не просто заборона, а причина).

## Очікування щодо коду

- Migrations + foreign keys
- Eloquent models + relationships (Infrastructure/Persistence/Eloquent)
- Form Requests (валідація, Infrastructure/Http)
- API Resources (форматування відповідей, Infrastructure/Http)
- Jobs + queues — Job = тонкий адаптер, викликає Application UseCase
- Database transactions (де є race conditions — насамперед бронювання)
- Factories + seeders
- Feature-тести
- Контролери тонкі — бізнес-логіка в Application/UseCase, без штучної надбудови понад описану 2-шарову межу

## Процес розробки — TDD, фіча-тести

Розробка йде **test-first**: для кожного endpoint/правила спочатку пишеться **Feature-тест** (`tests/Feature/`), що фейлиться, потім — реалізація (Application UseCase + Infrastructure адаптери), поки тест не стане зеленим.

- Одиниця роботи — сценарій ендпоінта, не клас. Наприклад: "POST /api/imports — 202 + pending", "повторний імпорт з тим самим external_import_id не дублює", "бронювання останньої одиниці атомарне" — кожен це окремий Feature-тест, написаний ДО коду.
- Feature-тести — основний рівень тестування для цього завдання (ганяють через HTTP-шар, реальну БД, реальну чергу в sync/queue-режимі). Unit-тести — точково, для Application-логіки, де це доречно (наприклад чиста перевірка ідемпотентності), не обов'язково для кожного класу.
- Порядок роботи над фічею: red (Feature-тест) → green (мінімальна реалізація) → refactor (розкладання по Application/Infrastructure межі, Deptrac лишається зеленим).

## Правила комітів

**Conventional Commits** (`type(scope): опис`), enforced через GrumPHP `git_commit_message` task на `commit-msg` хуку.

Формат: `type(scope): опис`
- `type` — один з: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `style`, `perf`
- `scope` — шар або сутність: `imports`, `properties`, `reservations`, `deptrac`, `docker`, `ci` тощо
- `опис` — imperative, малими літерами, без крапки в кінці

Приклади:
```
feat(imports): idempotent import endpoint + queue dispatch
fix(reservations): lockForUpdate race на останню одиницю
refactor(deptrac): Application → Infrastructure ports межа
docs(readme): concurrency-механізм бронювання
```

Конфіг `grumphp.yml` (додається при scaffold):
```yaml
grumphp:
  tasks:
    git_commit_message:
      allow_empty_message: false
      matchers:
        Must match Conventional Commits: /^(feat|fix|refactor|docs|test|chore|style|perf)\(.+\): .{1,72}$/
      case_insensitive: false
```

## README (фінальний, обов'язковий вміст)

- Посилання на Git-репозиторій з історією комітів
- Інструкція встановлення й запуску
- Команди: міграції, seeders, queue worker, тести
- Коротке пояснення ідемпотентності імпорту
- Пояснення concurrency-захисту бронювання (див. вище)
- `.env.example` без секретів

## Статус проєкту

Ще не scaffolded (Laravel не встановлено). Наступний крок — `laravel new` / `composer create-project` в цій папці.

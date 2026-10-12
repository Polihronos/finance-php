# Finance PHP

A JSON API for tracking personal spending: users, categories and transactions. Plain PHP with
MariaDB, no framework. A Vue web app and a Flutter mobile app are planned as clients.

## Status

| Endpoint | State |
|---|---|
| `POST /register` | Done, tested |
| `POST /login` | Done, tested |
| `POST /logout` | Done, tested |
| `POST /categories` | Done, tested |
| `GET /categories` | Done, tested |
| `PUT /categories/{id}` | Done, tested |
| `DELETE /categories/{id}` | Done, tested |
| `POST /transactions` | Done, tested |
| `GET /transactions` | Done, tested |
| `PUT /transactions/{id}` | Done, tested |
| `DELETE /transactions/{id}` | Done, tested |

Planned endpoints are tracked in [issue #3](https://github.com/Polihronos/finance-php/issues/3).

## Database design

![Database design](docs/images/database-design.png)

| Table | Holds |
|---|---|
| `users` | Name, email, password hash, role, creation date |
| `sessions` | One row per login: the user, a SHA-256 hash of the token, creation and expiry date |
| `categories` | A user's own spending categories. Names are unique per user, capitals ignored. |
| `transactions` | Amount, currency, date, payment method, description, place, optional photo path |

Deletion rules:

- Deleting a user also deletes that user's sessions, categories and transactions.
- A category cannot be deleted while transactions still use it.

## API request flow

![API flow](docs/images/api-flow.png)

## Project structure

```
public/index.php      Entry point. Loads config, turns on error handling, registers routes.
src/Router.php        Matches method and path to a handler, with one numeric {id} in the path.
                      Unknown routes answer 404.
src/Request.php       Reads the JSON request body.
src/Auth.php          Register, login, logout, and the token check for protected endpoints.
src/Categories.php    Create, list, rename and delete the logged-in user's categories.
src/Transactions.php  Create, list, edit and delete the logged-in user's transactions.
src/HttpException.php An error with an HTTP status. Throwing it sends that status and message.
src/Database.php      Opens the database connection from the .env settings.
src/ErrorHandler.php  Answers a thrown HttpException with its status and message. Turns any other
                      failure into a 500 JSON answer and logs the details.
create_tables.php     Creates the tables. Safe to run again.
tests/                PHPUnit tests that call the real API.
docs/images/          Diagrams.
```

Classes in `src/` use the `App\` namespace and are loaded by Composer, so no file needs a
`require` for them.

## Setup

Requires PHP 8.4 or newer with `pdo_mysql`, Composer, and MariaDB.

### Windows

Install [Composer](https://getcomposer.org/Composer-Setup.exe) and [MariaDB](https://mariadb.org/download/)
with its Windows service enabled. Start the service from an administrator PowerShell if needed:

```powershell
Start-Service MariaDB
```

If it refuses to start, check that the service is not disabled:

```powershell
Set-Service MariaDB -StartupType Manual
```

Clone and install:

```powershell
git clone https://github.com/Polihronos/finance-php.git
cd finance-php
composer install
Copy-Item .env.example .env
Copy-Item .env.example .env.testing
```

### macOS

With [Homebrew](https://brew.sh/) installed:

```bash
brew install composer mariadb
brew services start mariadb
```

Homebrew may also install its own PHP dependency.

Clone and install:

```bash
git clone https://github.com/Polihronos/finance-php.git
cd finance-php
composer install
cp .env.example .env
cp .env.example .env.testing
```

### Databases

The app and the tests each use their own database, so tests never touch your real data.

```sql
CREATE DATABASE finance_app;
CREATE DATABASE finance_app_test;
```

If the user in your env files is not `root`, give it access to both:

```sql
GRANT ALL PRIVILEGES ON finance_app.* TO 'your_user'@'localhost';
GRANT ALL PRIVILEGES ON finance_app_test.* TO 'your_user'@'localhost';
```

Set your credentials in `.env` and `.env.testing`. In `.env.testing`, set
`DB_DATABASE=finance_app_test`. Neither file is committed.

### Tables

Create the tables in both databases. Running it again only adds what is missing and keeps
existing data.

macOS:

```bash
php create_tables.php
APP_ENV=testing php create_tables.php
```

Windows:

```powershell
php create_tables.php
$env:APP_ENV = 'testing'; php create_tables.php; Remove-Item Env:APP_ENV
```

`APP_ENV=testing` makes the script read `.env.testing` instead of `.env`.

The script does not change tables that already exist. Databases created before category names
became unique need this once, in both databases:

```sql
ALTER TABLE categories ADD UNIQUE KEY user_category_name (user_id, name);
```

## Run

From the project root:

```sh
php -S localhost:8000 -t public
```

The API answers on `localhost:8000`. Errors and their details appear in this terminal.

## API

Request and response bodies are JSON.

| Status | Meaning |
|---|---|
| `200` | Success |
| `201` | Success, something was created |
| `400` | The request is missing something or has the wrong format |
| `401` | Wrong email or password, or a missing, invalid or expired token |
| `404` | No such endpoint, or the item does not exist or belongs to another user |
| `409` | Conflicts with existing data |
| `500` | Server error. The caller only sees `{"error": "Internal server error"}` |

Every error answer has the same shape:

```json
{"error": "email is required"}
```

### Sessions

Register and login both return a `token`: 64 random characters that identify one logged-in
device. Only a SHA-256 hash of it is stored, so the token cannot be read back from the
database. A session lasts 30 days. Each login creates a new session, so a user can be logged in
on several devices at once.

Protected endpoints need the token in the `Authorization` header:

```
Authorization: Bearer 9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e
```

A missing header, a malformed or unknown token, or an expired session all get the same answer:
`401` with `{"error": "not logged in"}`. When an expired token is used, all expired sessions are
deleted at that moment.

### `POST /register`

Creates a user and logs them in. The password is stored as a hash.

```json
{"name": "Nikola", "email": "nikola@example.com", "password": "secret123"}
```

`201 Created`:

```json
{"success": true, "user_id": 1, "token": "9a59d267832e358d55aef1712779caf7094709418ca846d86fa7fa3ba9ca1b3e"}
```

| Status | Error | When |
|---|---|---|
| `400` | `request body must be JSON` | The body is not a JSON object |
| `400` | `name is required` (or `email`, `password`) | Missing, `null` or empty. A name of only spaces counts as empty. |
| `400` | `name must be text` (or `email`, `password`) | A number, list or other non-text value |
| `400` | `invalid email` | Not shaped like an email address |
| `400` | `password must be at least 8 characters` | Shorter than 8 characters |
| `400` | `password must be less than 36 characters` | Longer than 36 characters |
| `409` | `email already registered` | The email is taken. Capitals are ignored. |

The user and the session are saved together: if one fails, neither is kept.

### `POST /login`

```json
{"email": "nikola@example.com", "password": "secret123"}
```

`200 OK`:

```json
{"success": true, "user_id": 1, "token": "5948660cb188c060357444e44e778f274eebfb19f56736a0084224a2508897a0"}
```

| Status | Error | When |
|---|---|---|
| `400` | `request body must be JSON` | The body is not a JSON object |
| `400` | `email is required` (or `password`) | Missing or `null` |
| `400` | `email must be text` (or `password`) | A number, list or other non-text value |
| `401` | `invalid email or password` | Unknown email or wrong password |

Capitals in the email are ignored. An unknown email and a wrong password get the same answer in
the same time, so the API does not reveal which emails are registered.

### `POST /logout`

Protected. Ends the session of the token in the `Authorization` header. The user's other
sessions, on other devices, stay logged in. No request body.

`200 OK`:

```json
{"success": true}
```

| Status | Error | When |
|---|---|---|
| `401` | `not logged in` | Missing, malformed, unknown or expired token, or a token that was already logged out |

### Categories

All category endpoints are protected and only see the logged-in user's own categories. A
category of another user gets the same `404` as one that does not exist, so ids of other users
cannot be discovered. Every category endpoint can also answer `401` with `not logged in`.

Names are checked the same way on create and rename:

| Status | Error | When |
|---|---|---|
| `400` | `request body must be JSON` | The body is not a JSON object |
| `400` | `name is required` | Missing, `null` or empty. A name of only spaces counts as empty. |
| `400` | `name must be text` | A number, list or other non-text value |
| `400` | `name must be at most 100 characters` | Longer than 100 characters, counted after trimming |

Spaces at the start and end of a name are removed before it is saved.

### `POST /categories`

```json
{"name": "Food"}
```

`201 Created`:

```json
{"success": true, "category_id": 1, "name": "Food"}
```

| Status | Error | When |
|---|---|---|
| `409` | `category already exists` | The user already has a category with this name. Capitals are ignored. |

Two users can each have a category with the same name.

### `GET /categories`

No request body. `200 OK`, sorted by name:

```json
{"categories": [{"id": 2, "name": "Bills"}, {"id": 1, "name": "Food"}]}
```

A user without categories gets `{"categories": []}`.

### `PUT /categories/{id}`

Renames a category.

```json
{"name": "Groceries"}
```

`200 OK`:

```json
{"success": true, "category_id": 1, "name": "Groceries"}
```

| Status | Error | When |
|---|---|---|
| `404` | `category not found` | No such category for this user |
| `409` | `category already exists` | Another category of this user already has this name |

Saving the current name again, or changing only its capitals, succeeds.

### `DELETE /categories/{id}`

No request body. `200 OK`:

```json
{"success": true}
```

| Status | Error | When |
|---|---|---|
| `404` | `category not found` | No such category for this user |
| `409` | `category is used by transactions` | Transactions still use this category |

### Transactions

All transaction endpoints are protected and only see the logged-in user's own transactions. A
transaction of another user gets the same `404` as one that does not exist. Every transaction
endpoint can also answer `401` with `not logged in`.

Create and edit check the fields the same way:

| Field | Required | Rule |
|---|---|---|
| `category_id` | yes | A whole number: the id of one of your own categories |
| `amount` | yes | A number above 0 with at most 2 decimals, at most `9999999999.99` |
| `currency` | no | Left out means `EUR`. Only `EUR` is allowed for now. Capitals are ignored. |
| `date` | yes | A real date written as `YYYY-MM-DD` |
| `payment_method` | yes | `cash` or `card`. Capitals are ignored. |
| `description` | no | Text, at most 255 characters |
| `place` | no | Text, at most 255 characters |

Spaces at the start and end of text are removed before saving. A `description` or `place` that is
empty or only spaces is saved as `null`.

| Status | Error | When |
|---|---|---|
| `400` | `request body must be JSON` | The body is not a JSON object |
| `400` | `category_id is required` (or `amount`, `date`, `payment_method`) | Missing or `null`. For text fields also empty or only spaces. |
| `400` | `category_id must be a whole number` | Text, a number with decimals, or another non-whole-number value |
| `400` | `category not found` | No such category for this user |
| `400` | `amount must be a number` | Text, `true`/`false`, a list or another non-number value |
| `400` | `amount must be more than 0` | 0 or negative |
| `400` | `amount must be at most 9999999999.99` | Too large for the database column |
| `400` | `amount must have at most 2 decimals` | For example `12.345` |
| `400` | `currency must be text` (or `date`, `payment_method`, `description`, `place`) | A number, list or other non-text value |
| `400` | `currency must be at most 3 characters` (or `date` 10, `payment_method` 50, `description` and `place` 255) | Longer than allowed, counted after trimming |
| `400` | `currency must be one of: EUR` | Any currency other than EUR |
| `400` | `date must be in YYYY-MM-DD format` | For example `12.10.2026` |
| `400` | `date is not a real date` | For example `2026-02-30` or `2026-13-01` |
| `400` | `payment_method must be one of: cash, card` | Any other payment method |

The fields are checked in the order of the field table, and the first wrong one is answered.

Every answer that contains a transaction shows it the same way. `amount` is text with 2 decimals,
so cents stay exact:

```json
{
  "id": 1,
  "category_id": 1,
  "amount": "12.50",
  "currency": "EUR",
  "date": "2026-10-12",
  "payment_method": "card",
  "description": "Lunch",
  "place": null
}
```

### `POST /transactions`

```json
{"category_id": 1, "amount": 12.5, "date": "2026-10-12", "payment_method": "card", "description": "Lunch"}
```

`201 Created`, with the saved transaction:

```json
{"success": true, "transaction": {"id": 1, "category_id": 1, "amount": "12.50", "currency": "EUR", "date": "2026-10-12", "payment_method": "card", "description": "Lunch", "place": null}}
```

### `GET /transactions`

No request body. `200 OK`, newest date first. Transactions on the same date show the one added last
first.

```json
{"transactions": [{"id": 2, "category_id": 1, "amount": "4.20", "currency": "EUR", "date": "2026-10-12", "payment_method": "cash", "description": null, "place": null}, {"id": 1, "category_id": 1, "amount": "12.50", "currency": "EUR", "date": "2026-10-01", "payment_method": "card", "description": "Lunch", "place": null}]}
```

A user without transactions gets `{"transactions": []}`.

### `PUT /transactions/{id}`

Edits a transaction. The body has the same fields as create and replaces all of them, so a
`description` or `place` that is left out becomes `null`.

```json
{"category_id": 1, "amount": 15, "date": "2026-10-12", "payment_method": "cash", "description": "Lunch", "place": "Office"}
```

`200 OK`, with the saved transaction:

```json
{"success": true, "transaction": {"id": 1, "category_id": 1, "amount": "15.00", "currency": "EUR", "date": "2026-10-12", "payment_method": "cash", "description": "Lunch", "place": "Office"}}
```

| Status | Error | When |
|---|---|---|
| `404` | `transaction not found` | No such transaction for this user |

The fields are checked before the transaction is looked up, so a wrong body gets `400` even for an
unknown id. Saving the same values again succeeds.

### `DELETE /transactions/{id}`

No request body. `200 OK`:

```json
{"success": true}
```

| Status | Error | When |
|---|---|---|
| `404` | `transaction not found` | No such transaction for this user |

## Tests

macOS:

```bash
vendor/bin/phpunit
```

Windows:

```powershell
vendor\bin\phpunit
```

Add `--testdox` to see every test by name.

The tests start their own server on port 8001 using `.env.testing`, so your dev server on port
8000 can keep running. Before each test, the test database is emptied, so every test starts
from the same state.

| File | Covers |
|---|---|
| `tests/RegisterTest.php` | Successful registration, body and field checks, email format, password length, duplicate emails |
| `tests/LoginTest.php` | Successful login, body and field checks, wrong and unknown credentials, email capitals |
| `tests/LogoutTest.php` | Logout of one session, and the token check: missing, malformed, unknown, reused and expired tokens |
| `tests/CategoriesTest.php` | Create, list, rename and delete: name checks, duplicates, sorting, other users' categories, categories used by transactions, token check |
| `tests/TransactionsTest.php` | Create, list, edit and delete: checks for every field, defaults and cleanup of values, sorting, other users' transactions and categories, token check |
| `tests/ApiTestCase.php` | Shared setup: test server, database reset, `post()`, `put()`, `get()` and `delete()` (plus `postRaw()` and `putRaw()` for bodies that are not JSON) with optional extra headers, `countUsers()`, `countSessions()` |

# Finance PHP

A JSON API for tracking personal spending: users, categories and transactions. Plain PHP with
MariaDB, no framework. A Vue web app and a Flutter mobile app are planned as clients.

## Status

| Endpoint | State |
|---|---|
| `POST /register` | Done, tested |
| `POST /login` | Done, tested |
| `POST /logout` | Planned |
| Transactions: create, list, edit, delete | Planned |
| Categories: create, list, edit, delete | Planned |

Planned endpoints are tracked in [issue #3](https://github.com/Polihronos/finance-php/issues/3).

## Database design

![Database design](docs/images/database-design.png)

| Table | Holds |
|---|---|
| `users` | Name, email, password hash, role, creation date |
| `sessions` | One row per login: the user, a SHA-256 hash of the token, creation and expiry date |
| `categories` | A user's own spending categories |
| `transactions` | Amount, currency, date, payment method, description, place, optional photo path |

Deletion rules:

- Deleting a user also deletes that user's sessions, categories and transactions.
- A category cannot be deleted while transactions still use it.

## API request flow

![API flow](docs/images/api-flow.png)

## Project structure

```
public/index.php      Entry point. Loads config, turns on error handling, registers routes.
src/Router.php        Matches method and path to a handler. Unknown routes answer 404.
src/Request.php       Reads the JSON request body.
src/Auth.php          Register and login, and the sessions they create.
src/Database.php      Opens the database connection from the .env settings.
src/ErrorHandler.php  Turns any unplanned failure into a 500 JSON answer and logs the details.
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
| `401` | Wrong email or password |
| `404` | No such endpoint |
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

Protected endpoints (transactions, categories, logout) will expect this token. None exist yet.

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
| `tests/LoginTest.php` | Successful login, body and field checks, wrong and unknown credentials |
| `tests/ApiTestCase.php` | Shared setup: test server, database reset, `post()`, `postRaw()`, `countUsers()`, `countSessions()` |

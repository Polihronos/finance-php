# Finance

Tracks personal spending: users, categories and transactions. The JSON API in `api/` is plain
PHP with MariaDB, no framework. The Vue web app in `web/` is its first client and is in progress.
A Flutter mobile app is planned in `mobile/`.

## Status

API:

| Endpoint | State |
|---|---|
| `POST /register` | Done, tested |
| `POST /login` | Done, tested |
| `POST /logout` | Done, tested |
| `POST /categories` | Done, tested |
| `GET /categories` | Done, tested |
| `GET /categories/stats` | Done, tested |
| `PUT /categories/{id}` | Done, tested |
| `DELETE /categories/{id}` | Done, tested |
| `POST /transactions` | Done, tested |
| `GET /transactions` | Done, tested |
| `PUT /transactions/{id}` | Done, tested |
| `DELETE /transactions/{id}` | Done, tested |

Planned endpoints are tracked in [issue #3](https://github.com/Polihronos/finance-php/issues/3).

Web app:

| Screen | State |
|---|---|
| Log in | Done, logic tested |
| Create account | Done, logic tested |
| Log out and expired sessions | Done, logic tested |
| Protected screens | Done |
| Transactions | Started: the screen and its header exist, the list does not yet |
| Add and edit transaction | Planned |
| Categories | Planned |

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
api/                  The PHP API. Every PHP command in this README runs from this folder.
web/                  The Vue web app. Every npm command in this README runs from this folder.
docs/images/          Diagrams.
README.md             This file.
```

Planned next to them: `mobile/` for the Flutter app.

Inside `api/`:

```
public/index.php      Entry point. Loads config, turns on error handling, registers routes.
src/Router.php        Matches method and path to a handler, with one numeric {id} in the path.
                      Unknown routes answer 404.
src/Request.php       Reads the JSON request body.
src/Auth.php          Register, login, logout, and the token check for protected endpoints.
src/Categories.php    Create, list, rename and delete the logged-in user's categories, and their
                      spending stats.
src/Transactions.php  Create, list, edit and delete the logged-in user's transactions.
src/HttpException.php An error with an HTTP status. Throwing it sends that status and message.
src/Database.php      Opens the database connection from the .env settings.
src/ErrorHandler.php  Answers a thrown HttpException with its status and message. Turns any other
                      failure into a 500 JSON answer and logs the details.
create_tables.php     Creates the tables. Safe to run again.
tests/                PHPUnit tests that call the real API.
composer.json         PHP packages and the App\ autoloading.
phpunit.xml           Test settings.
.env.example          Template for .env and .env.testing.
```

Classes in `src/` use the `App\` namespace and are loaded by Composer, so no file needs a
`require` for them.

Inside `web/`:

```
src/main.ts                       Entry point. Loads the font and styles, starts Vue with Pinia and
                                  the router, and sends the user to log in when the API answers 401.
src/App.vue                       Shows the screen of the current route.
src/router/index.ts               The routes, and the guard that keeps logged-out users on log in.
src/api/client.ts                 request(): every API call goes through it. Adds the token, sends
                                  and reads JSON, and turns an error answer into an ApiError.
src/api/types.ts                  The shapes of the API's answers.
src/api/auth.ts                   register(), login() and logout(): one function per endpoint.
src/stores/auth.ts                The token: kept in memory, in localStorage and in client.ts.
src/views/                        One file per screen: LoginView, RegisterView, TransactionsView.
src/components/AppLayout.vue      Header with the Log out button, around every protected screen.
src/components/AppButton.vue      The only button: always an icon, with text or icon only.
src/components/AppError.vue       The error note shown in forms.
src/components/PasswordInput.vue  Password field with a button to show or hide the password.
src/assets/main.css               Colors, font, page layout, form fields and links.
src/**/__tests__/                 Vitest tests.
vite.config.ts                    Dev server, and the /api forwarding to the PHP API.
package.json                      npm packages and scripts.
```

## Setup

The API requires PHP 8.4 or newer with `pdo_mysql`, Composer, and MariaDB. The web app requires
Node.js 22.18 or newer (or 24.12 or newer).

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
cd finance-php\api
composer install
Copy-Item .env.example .env
Copy-Item .env.example .env.testing
```

A clone from before the API moved into `api/` keeps `vendor`, `.env` and `.env.testing` at the
top, because Git does not track them. After pulling, move them once from the `finance-php` folder:

```powershell
Move-Item vendor, .env, .env.testing api
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
cd finance-php/api
composer install
cp .env.example .env
cp .env.example .env.testing
```

A clone from before the API moved into `api/` keeps `vendor`, `.env` and `.env.testing` at the
top, because Git does not track them. After pulling, move them once from the `finance-php` folder:

```bash
mv vendor .env .env.testing api/
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

Set your credentials in `api/.env` and `api/.env.testing`. In `.env.testing`, set
`DB_DATABASE=finance_app_test` and `BCRYPT_COST=4`. Neither file is committed.

### Tables

Create the tables in both databases, from the `api` folder. Running it again only adds what is
missing and keeps existing data.

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

### Web app

From the `web` folder, the same on macOS and Windows:

```bash
npm install
```

## Run

The API, from the `api` folder:

```sh
php -S localhost:8000 -t public
```

The API answers on `localhost:8000`. Errors and their details appear in this terminal.

The web app, in a second terminal, from the `web` folder:

```bash
npm run dev
```

Open `localhost:5173`. The web app forwards every request that starts with `/api` to the API on
port 8000, so the browser only ever talks to one address and the API needs no CORS settings.

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

### `GET /categories/stats`

No request body. `200 OK`, one row for every category of the user, including categories without
transactions. The order of the rows is not fixed; match them to categories by `category_id`.

```json
{"stats": [{"category_id": 1, "transaction_count": 2, "monthly_average": "30.00"}]}
```

`monthly_average` is the total of the category's transactions divided by the number of months from
the month of its first transaction up to the current month, both included. Months without
transactions count too: 30 in August and 60 in October give 90 / 3 = `30.00`. It is rounded to
cents and sent as text, like amounts. A category without transactions gets `0` and `"0.00"`. When
all transactions of a category are dated in the future, it counts as one month.

A user without categories gets `{"stats": []}`.

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

## Web app

Plain Vue 3 with TypeScript, Vue Router and Pinia. No UI library: the look comes from
`src/assets/main.css` and four small components.

### How a request travels

```
Screen (LoginView)          shows the form, shows the error
   ↓
Store (stores/auth.ts)      keeps the token
   ↓
API function (api/auth.ts)  one function per endpoint
   ↓
request() (api/client.ts)   token header, JSON, errors
   ↓
/api/login → Vite forwards it → PHP API /login
```

Each layer only calls the one below it. `client.ts` knows nothing about the store or the router.

### Login in the browser

| Moment | What happens |
|---|---|
| Log in or create account | The token from the API is saved in the store, in `localStorage` and in `client.ts` |
| Page reload | The token is read back from `localStorage`, so the user stays logged in |
| Opening a screen | Every screen needs a token unless its route has `meta: { guest: true }`. Log in and create account are the only guest screens. Without a token the user goes to log in, with one a guest screen sends them to Transactions |
| Log out | The API ends the session. The token is removed on this device even when that call fails |
| Any answer `401` while logged in | The session expired or was ended elsewhere: the token is removed and the user goes to log in. A `401` without a token, a wrong password, does not do this |

The API stays the only judge of what is valid. The browser only checks what is cheap to check
early: required fields, the email format and the password length.

### Design

| | |
|---|---|
| Colors | `--text`, `--background`, `--primary`, `--secondary`, `--accent` in `main.css` |
| Font | Montserrat, Latin and Cyrillic, served from the app itself through `@fontsource-variable/montserrat` |
| Icons | Lucide through `@lucide/vue`. Only the icons that are imported end up in the app |
| Buttons | Always `AppButton`: an icon plus text, or only an icon with its text as the accessible name and tooltip. `variant="quiet"` drops the red fill |
| Errors | `AppError`: a yellow note with a stripe and a ghost icon, above the form's button |

Text is always shown with `{{ }}`, which Vue escapes. ESLint refuses `v-html`, so text from the API
can never run as code in the page.

## Tests

### API

From the `api` folder. macOS:

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
| `tests/CategoriesTest.php` | Create, list, rename and delete: name checks, duplicates, sorting, other users' categories, categories used by transactions, token check. Stats: monthly average, empty months, rounding, future dates, other users' data |
| `tests/TransactionsTest.php` | Create, list, edit and delete: checks for every field, defaults and cleanup of values, sorting, other users' transactions and categories, token check |
| `tests/ApiTestCase.php` | Shared setup: test server, database reset, `post()`, `put()`, `get()` and `delete()` (plus `postRaw()` and `putRaw()` for bodies that are not JSON) with optional extra headers, `countUsers()`, `countSessions()` |

### Web app

From the `web` folder:

```bash
npm run test:unit
```

It keeps watching and runs the tests again on every save. Press `q` to stop.

The tests run without the API: `fetch` is replaced by a fake that gives the answer each test
needs.

| File | Covers |
|---|---|
| `src/api/__tests__/client.spec.ts` | The path, JSON body and token header of every request, success and error answers, answers that are not JSON, and which `401` answers call the handler set with `setUnauthorizedHandler()` |
| `src/api/__tests__/auth.spec.ts` | Register, login and logout: what each sends, what it returns, and that API errors reach the caller |
| `src/stores/__tests__/auth.spec.ts` | The token after start, login, register, logout and forget, in the store, in `localStorage` and in the requests that follow, also when the API fails |
| `src/api/__tests__/fakeFetch.ts` | Shared helper: replaces `fetch` with a fake that answers with a given status and body |

Other checks, from the same folder:

| Command | Does |
|---|---|
| `npm run type-check` | Checks the TypeScript types |
| `npm run lint` | Runs Oxlint and ESLint. It also fixes what it can, so it may change files |
| `npm run format` | Formats `src/` with Prettier |

# Finance PHP

## Database relationships

![Database design](docs/images/database-design.png)

## API request flow

![API flow](docs/images/api-flow.png)

## Setup

Requires PHP 8.4 with `pdo_mysql`, Composer, and MariaDB.

### Windows

Install [Composer](https://getcomposer.org/Composer-Setup.exe) and [MariaDB](https://mariadb.org/download/) with its Windows service enabled. Start the service from administrator PowerShell if needed:

```powershell
Start-Service MariaDB
```

Clone and install:

```powershell
git clone https://github.com/polihronos/finance-php.git
cd finance-php
composer install
Copy-Item .env.example .env
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
git clone https://github.com/polihronos/finance-php.git
cd finance-php
composer install
cp .env.example .env
```

## Run

Create a `finance_app` database. Set your local credentials in `.env`.

From the project root:

```sh
php -S localhost:8000 -t public
```

Open [localhost:8000](http://localhost:8000).

## Create the tables

From the project root:

```sh
php create_tables.php
```

Safe to run again: existing tables and data are kept.

Deletion rules:

- Deleting a user also deletes that user's categories and transactions.
- A category cannot be deleted while transactions still use it.

## API

All responses are JSON. Request bodies are JSON.

### `POST /register`

Creates a user. The password is stored as a hash.

Request:

```json
{"name": "Nikola", "email": "nikola@example.com", "password": "secret"}
```

Response:

```json
{"success": true, "user_id": "1"}
```

Example:

```sh
curl -X POST localhost:8000/register -H "Content-Type: application/json" -d '{"name":"Nikola","email":"nikola@example.com","password":"secret"}'
```

Other endpoints are tracked in [issue #3](https://github.com/Polihronos/finance-php/issues/3).

# Symfony Starter-Kit

## Installation

1. Clone the repository
2. Run `composer install`

## Configuration

1. Configure/confirm `.env`:
    - `APP_URL` - the _base_ URL of your application
    - `APP_FROM_EMAIL` - email address to send from
    - `DATABASE_URL` - your database connection string
2. Create Database: `symfony console doctrine:database:create`
3. Create migrations: `symfony console doctrine:migrations:diff`
4. Run migrations: `symfony console doctrine:migrations:migrate`
5. Download Tailwind CSS: `symfony console tailwind:build`
6. Load `dev` fixtures: `symfony console doctrine:fixtures:load`

## Local Development

1. _(optional)_ `docker compose up -d`
2. `symfony server:start -d`

## Testing

Run `bin/phpunit` to run the test suite.

## Schedule

Add recurring tasks to `src/Schedule.php`.
See [Scheduler Documentation](https://symfony.com/doc/current/scheduler.html)

## Usage

### Messenger Monitoring

Visit `/admin/messenger` (as an admin user) to view your messenger/scheduler
monitor dashboard.

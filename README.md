# Symfony Starter-Kit

## Installation

1. Clone the repository
2. Run `composer install`

## Configuration

1. Configure/confirm `.env`:
    - `APP_URL` - the _base_ URL of your application
    - `APP_FROM_EMAIL` - email address to send from
2. Database setup:
    ```
    symfony console doctrine:database:create
    symfony console make:migration
    symfony console doctrine:migrations:migrate
    symfony console doctrine:fixtures:load
    ```

## Local Development

1. `symfony server:start -d`
2. Head to https://127.0.0.1:8000

## Other Goodies

- Run test suite: `bin/phpunit`
- Run PHPStan _(static analysis)_: `vendor/bin/phpstan`
- Run php-cs-fixer _(code style)_: `vendor/bin/php-cs-fixer fix -v`

## Customization

### Security

- Login Throttling: `config/packages/security.yaml` (`security.firewalls.main.login_throttling`)

### Schedule

Add recurring tasks to `src/Schedule.php`.
See [Scheduler Documentation](https://symfony.com/doc/current/scheduler.html)

## Usage

### Messenger Monitoring

Visit `/admin/messenger` (as an admin user) to view your messenger/scheduler
monitor dashboard.

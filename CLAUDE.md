# Development environment

This app runs under [Laravel Sail](https://laravel.com/docs/sail) (Docker). Always use Sail-wrapped commands instead of bare `php`, `composer`, or `npm` — the host machine does not have the app's PHP/MySQL stack installed natively.

```bash
./vendor/bin/sail up -d          # start containers
./vendor/bin/sail artisan ...    # instead of: php artisan ...
./vendor/bin/sail composer ...   # instead of: composer ...
./vendor/bin/sail npm ...        # instead of: npm ...
./vendor/bin/sail test           # instead of: php artisan test
```

If `./vendor/bin/sail` or `docker-compose.yml` is missing from a checkout, it hasn't been published yet — run `php artisan sail:install` (via a one-off local PHP, or `composer install` first) to generate it before using the commands above.

`.env`'s `DB_HOST=mysql` etc. are Docker Compose service hostnames — they only resolve inside the Sail network, not on the host.

# UI copy conventions

Short confirmation-style status messages (e.g. toast titles like "Message Sent", "Reply Sent") should always be Title Case — capitalize each significant word. This applies to brief status/result messages, not longer sentences or descriptions (e.g. "Add at least one recipient" stays sentence case).

This also applies to backend response messages (e.g. a controller's JSON `message` field returned after an action succeeds) — a short result message like "Label Created" or "Password Updated" should be Title Case for the same reason, matching what the frontend displays.

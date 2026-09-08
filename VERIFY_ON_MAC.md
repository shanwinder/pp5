# Verify Task 1 on macOS / MAMP

The project source is prepared for Composer, FastRoute 1.3 and PHPUnit 11. The automated dependency install could not be executed in the assistant sandbox because that runtime has no outbound package-download access.

On the Mac, from the project root:

```bash
cd htdocs
composer install
cd ..
htdocs/vendor/bin/phpunit tests/Feature/FrontControllerTest.php
```

Expected PHPUnit result: 2 tests pass.

Then point MAMP's document root to the `htdocs` directory (or place the project under the MAMP web root) and verify:

- `/` returns `PP5`
- `/missing` returns HTTP 404
- direct paths such as `/app/Application.php` and `/routes/web.php` return HTTP 403 when `.htaccess` overrides are enabled

PHP target: 8.2 or newer.

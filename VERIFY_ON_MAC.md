# Verify Task 1 on macOS / MAMP

## Verified on 2026-09-09 (Asia/Bangkok)

Task 1's existing implementation was inspected against the foundation plan and
technical architecture. No application or PHPUnit test code changes were needed.
Task 2 has not been started.

- Branch: `milestone/1-foundation`
- Composer: 2.8.12
- PHP CLI and MAMP FastCGI: 8.3.14
- MAMP Apache: 2.4.62 (Unix)
- FastRoute: 1.3.1
- PHPUnit: 11.5.56

`composer install` succeeded in `htdocs`, installed 28 packages, generated the
autoload files, and created `htdocs/composer.lock`. The first sandboxed attempt
failed to resolve Packagist and could not write Composer's cache; the authorized
network-enabled retry succeeded.

The existing feature test command produced:

```text
PHPUnit 11.5.56 by Sebastian Bergmann and contributors.

Runtime:       PHP 8.3.14
Configuration: /Applications/MAMP/htdocs/pp5/phpunit.xml

..                                                                  2 / 2 (100%)

Time: 00:00.016, Memory: 4.00 MB

OK (2 tests, 3 assertions)
```

All 7 project PHP files passed `php -l` (application, routes, entry point,
test bootstrap, and feature test; third-party vendor files excluded).

## MAMP / Apache verification and configuration issue

The existing MAMP server on port 8888 has DocumentRoot
`/Applications/MAMP/htdocs`, `AllowOverride All` for that directory, and a
commented-out `mod_rewrite` load directive. Its Apache config passes `httpd -t`,
but requesting `/pp5/htdocs/` returned HTTP 500. Apache's error log confirmed:

```text
.htaccess: Invalid command 'RewriteEngine', perhaps misspelled or defined by a module not included in the server configuration
```

HTTP verification used a temporary second instance of the installed MAMP Apache
and its PHP 8.3.14 FastCGI handler, listening only on `127.0.0.1:18888`, with
DocumentRoot `/Applications/MAMP/htdocs/pp5/htdocs`. Its config was copied from
MAMP, with separate temporary PID, error log, and FastCGI socket paths.

An HTTP assertion script first reproduced HTTP 500 for all eight checks with
rewrite disabled. Enabling only `mod_rewrite` in that temporary configuration
and reloading it made the same checks pass. Apache configuration syntax was
also checked successfully.

| Request | Expected | Observed |
| --- | --- | --- |
| `GET /` | 200, body `PP5` | 200, body `PP5` |
| `GET /missing` | 404, body `Not Found` | 404, body `Not Found` |
| `GET /app/` | 403 | 403 |
| `GET /app/Application.php` | 403 | 403 |
| `GET /routes/` | 403 | 403 |
| `GET /routes/web.php` | 403 | 403 |
| `GET /vendor/` | 403 | 403 |
| `GET /vendor/autoload.php` | 403 | 403 |

The temporary server was stopped after verification. The normal MAMP server's
configuration was not modified: port 8888 still needs the setup below before
it can serve PP5 correctly. These successful HTTP results apply to the temporary
MAMP Apache instance with the correct project DocumentRoot and rewrite enabled.

## Repeat locally

On the Mac, from the project root:

```bash
cd htdocs
composer install
cd ..
htdocs/vendor/bin/phpunit tests/Feature/FrontControllerTest.php
```

Expected PHPUnit result: 2 tests pass.

Enable `LoadModule rewrite_module modules/mod_rewrite.so` in MAMP's Apache
configuration. Set MAMP's DocumentRoot (or a dedicated virtual host's DocumentRoot)
to `/Applications/MAMP/htdocs/pp5/htdocs`, retain `AllowOverride All` for that
directory, and restart Apache. Merely placing the repository below MAMP's web
root does not provide the root-mounted URLs expected by this application.

Verify:

- `/` returns `PP5`
- `/missing` returns HTTP 404
- `/app/`, `/routes/`, `/vendor/`, `/app/Application.php`, `/routes/web.php`,
  and `/vendor/autoload.php` return HTTP 403

PHP target: 8.2 or newer.

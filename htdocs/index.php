<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;

require __DIR__ . '/vendor/autoload.php';

session_set_cookie_params([
    'httponly' => true,
    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
    'path' => '/',
]);

session_start();

(new Application())
    ->handle(Request::fromGlobals())
    ->send();

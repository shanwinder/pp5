<?php
declare(strict_types=1);

use App\Application;
use App\Http\Request;

require __DIR__ . '/vendor/autoload.php';

session_start();

(new Application())
    ->handle(Request::fromGlobals())
    ->send();

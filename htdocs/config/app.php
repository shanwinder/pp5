<?php
declare(strict_types=1);

return [
    'name' => 'ระบบ ปพ.5',
    'env' => getenv('APP_ENV') ?: 'development',
    'debug' => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOL),
];

<?php
declare(strict_types=1);

namespace App\Support;

use App\Http\Session;

final class Csrf
{
    private const KEY = 'csrf_token';

    public function token(Session $session): string
    {
        $current = $session->get(self::KEY);

        if (is_string($current) && $current !== '') {
            return $current;
        }

        $token = bin2hex(random_bytes(32));
        $session->put(self::KEY, $token);

        return $token;
    }

    public function verify(Session $session, ?string $token): bool
    {
        $current = $session->get(self::KEY);

        return is_string($current)
            && is_string($token)
            && $token !== ''
            && hash_equals($current, $token);
    }
}

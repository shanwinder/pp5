<?php
declare(strict_types=1);

namespace App\Services;

use DomainException;

/** Location is supplied only after live authorization and all target relationships pass. */
final class GradebookBatchException extends DomainException
{
    public function __construct(string $message, public readonly array $location)
    {
        parent::__construct($message);
    }
}

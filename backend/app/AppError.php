<?php
declare(strict_types=1);

namespace App;

final class AppError extends \RuntimeException
{
    public function __construct(
        public string $errorCode,
        string $message,
        public int $http = 422,
        public ?array $fields = null
    ) {
        parent::__construct($message);
    }
}

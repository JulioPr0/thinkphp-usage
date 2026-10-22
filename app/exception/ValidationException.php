<?php
declare(strict_types=1);

namespace app\exception;

use RuntimeException;

final class ValidationException extends RuntimeException
{
    public function __construct(private array $errors)
    {
        parent::__construct('Periksa kembali isian formulir.');
    }

    public function errors(): array
    {
        return $this->errors;
    }
}

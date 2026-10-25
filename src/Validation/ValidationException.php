<?php

namespace Dromos\Validation;

/**
 * Class ValidationException
 *
 * Thrown when validation fails and the caller attempts to retrieve
 * validated data via Validator::validated().
 */
class ValidationException extends \Exception
{
    /**
     * @var array<string, string[]> Validation errors keyed by field name
     */
    private array $errors;

    /**
     * @param array<string, string[]> $errors Validation errors keyed by field name
     */
    public function __construct(array $errors)
    {
        parent::__construct('Validation failed');
        $this->errors = $errors;
    }

    /**
     * Get the validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        return $this->errors;
    }
}

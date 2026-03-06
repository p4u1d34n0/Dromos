<?php

namespace Dromos\Validation;

/**
 * Class Validator
 *
 * A straightforward input validator for API data. Parses pipe-delimited
 * rule strings and validates each field independently.
 *
 * Usage:
 *   $validator = new Validator($data, [
 *       'name'  => 'required|string|min:2|max:100',
 *       'email' => 'required|email',
 *       'age'   => 'integer|min:0|max:150',
 *       'role'  => 'in:admin,user,editor',
 *   ]);
 *
 *   if ($validator->fails()) {
 *       $errors = $validator->errors();
 *   }
 *
 *   $clean = $validator->validated();
 */
class Validator
{
    /**
     * @var array<string, mixed> The data to validate
     */
    private array $data;

    /**
     * @var array<string, string> The validation rules keyed by field name
     */
    private array $rules;

    /**
     * @var array<string, string[]> Validation errors keyed by field name
     */
    private array $errors = [];

    /**
     * @var bool Whether validation has already been run
     */
    private bool $validated = false;

    /**
     * @param array<string, mixed>  $data  The input data to validate
     * @param array<string, string> $rules The validation rules (pipe-delimited)
     */
    public function __construct(array $data, array $rules)
    {
        $this->data = $data;
        $this->rules = $rules;
    }

    /**
     * Determine if validation has failed.
     *
     * @return bool
     */
    public function fails(): bool
    {
        $this->validate();

        return !empty($this->errors);
    }

    /**
     * Determine if validation has passed.
     *
     * @return bool
     */
    public function passes(): bool
    {
        return !$this->fails();
    }

    /**
     * Get all validation errors.
     *
     * @return array<string, string[]>
     */
    public function errors(): array
    {
        $this->validate();

        return $this->errors;
    }

    /**
     * Get only the validated data (fields present in rules), stripping unknown keys.
     *
     * @return array<string, mixed>
     *
     * @throws ValidationException If validation fails
     */
    public function validated(): array
    {
        $this->validate();

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        return array_intersect_key($this->data, $this->rules);
    }

    /**
     * Run validation against all rules. Only executes once.
     *
     * @return void
     */
    private function validate(): void
    {
        if ($this->validated) {
            return;
        }

        $this->validated = true;

        foreach ($this->rules as $field => $ruleString) {
            $rules = explode('|', $ruleString);
            $value = $this->data[$field] ?? null;
            $fieldErrors = [];

            foreach ($rules as $rule) {
                $error = $this->applyRule($field, $value, $rule);

                if ($error !== null) {
                    $fieldErrors[] = $error;
                }
            }

            if (!empty($fieldErrors)) {
                $this->errors[$field] = $fieldErrors;
            }
        }
    }

    /**
     * Apply a single rule to a field value.
     *
     * @param string $field The field name
     * @param mixed  $value The field value
     * @param string $rule  The rule string (e.g. 'min:2')
     *
     * @return string|null An error message, or null if the rule passes
     */
    private function applyRule(string $field, mixed $value, string $rule): ?string
    {
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $param = $parts[1] ?? null;

        return match ($ruleName) {
            'required' => ($value === null || $value === '')
                ? "The {$field} field is required."
                : null,

            'string' => ($value !== null && !is_string($value))
                ? "The {$field} field must be a string."
                : null,

            'integer' => ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_INT) === false)
                ? "The {$field} field must be an integer."
                : null,

            'numeric' => ($value !== null && $value !== '' && !is_numeric($value))
                ? "The {$field} field must be numeric."
                : null,

            'email' => ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false)
                ? "The {$field} field must be a valid email address."
                : null,

            'url' => ($value !== null && $value !== '' && filter_var($value, FILTER_VALIDATE_URL) === false)
                ? "The {$field} field must be a valid URL."
                : null,

            'boolean' => ($value !== null && !is_bool($value) && !in_array($value, [0, 1, '0', '1', true, false], true))
                ? "The {$field} field must be a boolean."
                : null,

            'array' => ($value !== null && !is_array($value))
                ? "The {$field} field must be an array."
                : null,

            'min' => $param !== null
                ? $this->validateMin($field, $value, (int) $param)
                : "The {$field} rule 'min' requires a parameter.",
            'max' => $param !== null
                ? $this->validateMax($field, $value, (int) $param)
                : "The {$field} rule 'max' requires a parameter.",
            'in'  => $param !== null
                ? $this->validateIn($field, $value, $param)
                : "The {$field} rule 'in' requires a parameter.",

            'regex' => ($param === null)
                ? "The {$field} rule 'regex' requires a pattern."
                : (($value !== null && $value !== '' && !preg_match($param, (string) $value))
                    ? "The {$field} field format is invalid."
                    : null),

            default => null,
        };
    }

    /**
     * Validate a minimum constraint.
     *
     * For strings, checks strlen. For numeric values, checks the value itself.
     * For arrays, checks count. Skips null/empty values (let 'required' handle presence).
     *
     * @param string $field The field name
     * @param mixed  $value The field value
     * @param int    $min   The minimum constraint
     *
     * @return string|null
     */
    private function validateMin(string $field, mixed $value, int $min): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) < $min) {
            return "The {$field} field must be at least {$min} characters.";
        }

        if (is_numeric($value) && $value < $min) {
            return "The {$field} field must be at least {$min}.";
        }

        if (is_array($value) && count($value) < $min) {
            return "The {$field} field must have at least {$min} items.";
        }

        return null;
    }

    /**
     * Validate a maximum constraint.
     *
     * For strings, checks strlen. For numeric values, checks the value itself.
     * For arrays, checks count. Skips null/empty values (let 'required' handle presence).
     *
     * @param string $field The field name
     * @param mixed  $value The field value
     * @param int    $max   The maximum constraint
     *
     * @return string|null
     */
    private function validateMax(string $field, mixed $value, int $max): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value) && strlen($value) > $max) {
            return "The {$field} field must not exceed {$max} characters.";
        }

        if (is_numeric($value) && $value > $max) {
            return "The {$field} field must not exceed {$max}.";
        }

        if (is_array($value) && count($value) > $max) {
            return "The {$field} field must not have more than {$max} items.";
        }

        return null;
    }

    /**
     * Validate that the value is within a comma-separated list of allowed values.
     *
     * Skips null/empty values (let 'required' handle presence).
     *
     * @param string      $field   The field name
     * @param mixed       $value   The field value
     * @param string|null $param   Comma-separated list of allowed values
     *
     * @return string|null
     */
    private function validateIn(string $field, mixed $value, ?string $param): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $allowed = explode(',', $param ?? '');

        if (!in_array((string) $value, $allowed, true)) {
            return "The {$field} field must be one of: " . implode(', ', $allowed) . ".";
        }

        return null;
    }
}

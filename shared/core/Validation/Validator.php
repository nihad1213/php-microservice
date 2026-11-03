<?php
declare(strict_types=1);

namespace Core\Validation;

use Core\Exceptions\HttpException;
use InvalidArgumentException;

class Validator
{
    private array $errors = [];

    private array $validated = [];

    public function __construct(private array $data, private array $rules)
    {
        $this->run();
    }

    public static function validate(array $data, array $rules): array
    {
        $validator = new self($data, $rules);

        if ($validator->fails()) {
            throw HttpException::unprocessable($validator->errors());
        }

        return $validator->validated();
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    public function errors(): array
    {
        return $this->errors;
    }

    public function validated(): array
    {
        return $this->validated;
    }

    private function run(): void
    {
        foreach ($this->rules as $field => $fieldRules) {
            $field = (string) $field;
            $rules = $this->parseRules($fieldRules);

            $present  = array_key_exists($field, $this->data);
            $value    = $this->data[$field] ?? null;
            $required = in_array('required', $rules, true);

            if ($this->isEmpty($value)) {
                if ($required) {
                    $this->errors[$field] = 'The ' . $this->label($field) . ' field is required.';
                } elseif ($present) {
                    $this->validated[$field] = $value;
                }

                continue;
            }

            $numeric = in_array('integer', $rules, true) || in_array('numeric', $rules, true);

            foreach ($rules as $rule) {
                if ($rule === 'required') {
                    continue;
                }

                [$name, $param] = array_pad(explode(':', $rule, 2), 2, null);

                $message = $this->check($field, $value, $name, $param, $numeric);
                if ($message !== null) {
                    $this->errors[$field] = $message;
                    continue 2;
                }
            }

            $this->validated[$field] = $value;
        }
    }

    private function parseRules(string|array $rules): array
    {
        $list = is_string($rules) ? explode('|', $rules) : $rules;

        return array_values(array_filter(array_map('trim', $list), static fn ($r) => $r !== ''));
    }

    private function isEmpty(mixed $value): bool
    {
        return $value === null
            || $value === []
            || (is_string($value) && trim($value) === '');
    }

    private function label(string $field): string
    {
        return str_replace('_', ' ', $field);
    }

    private function check(string $field, mixed $value, string $rule, ?string $param, bool $numeric): ?string
    {
        $label = $this->label($field);

        return match ($rule) {
            'string'  => is_string($value) ? null : "The {$label} must be a string.",
            'integer' => $this->isInteger($value) ? null : "The {$label} must be an integer.",
            'numeric' => $this->isNumeric($value) ? null : "The {$label} must be a number.",
            'boolean' => $this->isBoolean($value) ? null : "The {$label} must be true or false.",
            'array'   => is_array($value) ? null : "The {$label} must be an array.",
            'email'   => (is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false)
                            ? null : "The {$label} must be a valid email address.",
            'url'     => (is_string($value) && filter_var($value, FILTER_VALIDATE_URL) !== false)
                            ? null : "The {$label} must be a valid URL.",
            'date'    => $this->isDate($value) ? null : "The {$label} must be a date in Y-m-d format.",
            'in'      => $this->checkIn($label, $value, $param),
            'min', 'max' => $this->checkSize($label, $value, $rule, $param, $numeric),
            default   => throw new InvalidArgumentException("Unknown validation rule: {$rule}"),
        };
    }

    private function checkIn(string $label, mixed $value, ?string $param): ?string
    {
        if ($param === null || $param === '') {
            throw new InvalidArgumentException('Rule "in" needs a list, for example in:todo,doing,done');
        }

        $allowed = explode(',', $param);
        $ok = (is_string($value) || is_int($value) || is_float($value))
            && in_array((string) $value, $allowed, true);

        return $ok ? null : "The {$label} must be one of: " . implode(', ', $allowed) . '.';
    }

    private function checkSize(string $label, mixed $value, string $rule, ?string $param, bool $numeric): ?string
    {
        if ($param === null || !is_numeric($param)) {
            throw new InvalidArgumentException("Rule \"{$rule}\" needs a number, for example {$rule}:5");
        }

        $limit = $param + 0;

        if ($numeric && is_numeric($value)) {
            $size = $value + 0;
            $unit = '';
        } elseif (is_int($value) || is_float($value)) {
            $size = $value;
            $unit = '';
        } elseif (is_string($value)) {
            $size = mb_strlen($value);
            $unit = ' characters';
        } elseif (is_array($value)) {
            $size = count($value);
            $unit = ' items';
        } else {
            return "The {$label} has an invalid value.";
        }

        if ($rule === 'min') {
            return $size >= $limit ? null : "The {$label} must be at least {$limit}{$unit}.";
        }

        return $size <= $limit ? null : "The {$label} must not be greater than {$limit}{$unit}.";
    }

    private function isInteger(mixed $value): bool
    {
        return is_int($value) || (is_string($value) && preg_match('/^-?\d+$/', $value) === 1);
    }

    private function isNumeric(mixed $value): bool
    {
        return is_int($value) || is_float($value) || (is_string($value) && is_numeric($value));
    }

    private function isBoolean(mixed $value): bool
    {
        return is_bool($value) || in_array($value, [0, 1, '0', '1'], true);
    }

    private function isDate(mixed $value): bool
    {
        if (!is_string($value)) {
            return false;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value;
    }
}

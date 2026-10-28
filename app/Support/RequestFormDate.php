<?php

namespace App\Support;

use DateTimeImmutable;

class RequestFormDate
{
    public static function matchesFormat(string $value, string $format): bool
    {
        if (! array_key_exists($format, config('request_forms.date_formats', []))) {
            return false;
        }

        $date = DateTimeImmutable::createFromFormat('!'.$format, $value);
        $errors = DateTimeImmutable::getLastErrors();

        return $date !== false
            && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))
            && $date->format($format) === $value;
    }
}

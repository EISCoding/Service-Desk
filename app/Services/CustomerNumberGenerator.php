<?php

namespace App\Services;

use App\Models\Customer;

/**
 * Next automatic customer number: prefix + (highest existing number + 1),
 * starting at the configured start value. Manually entered numbers that do
 * not follow the pattern are ignored.
 */
class CustomerNumberGenerator
{
    public function next(): string
    {
        $prefix = config('servicedesk.customer_number.prefix');
        $start = (int) config('servicedesk.customer_number.start');
        $pattern = '/^'.preg_quote($prefix, '/').'(\d+)$/';

        $highest = Customer::query()
            ->where('number', 'like', str_replace(['%', '_'], ['\%', '\_'], $prefix).'%')
            ->pluck('number')
            ->map(fn (string $number) => preg_match($pattern, $number, $match) ? (int) $match[1] : 0)
            ->max() ?? 0;

        return $prefix.max($highest + 1, $start);
    }
}

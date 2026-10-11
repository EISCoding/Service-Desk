<?php

namespace App\Services;

use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Ticket numbers "JJJJ-NNNNN" (e.g. 2026-00123) with a counter per year.
 * The counter row is locked (SELECT … FOR UPDATE), so parallel requests and
 * mail imports never get the same number.
 */
class TicketNumberGenerator
{
    public function next(): string
    {
        $year = (int) now()->format('Y');

        return DB::transaction(function () use ($year) {
            $counter = DB::table('ticket_counters')->where('year', $year)->lockForUpdate()->first();

            if (! $counter) {
                try {
                    DB::table('ticket_counters')->insert(['year' => $year, 'last_number' => 1]);

                    return $this->format($year, 1);
                } catch (UniqueConstraintViolationException) {
                    // Another process created this year's row in the meantime.
                    $counter = DB::table('ticket_counters')->where('year', $year)->lockForUpdate()->first();
                }
            }

            $number = $counter->last_number + 1;
            DB::table('ticket_counters')->where('year', $year)->update(['last_number' => $number]);

            return $this->format($year, $number);
        }, attempts: 3);
    }

    private function format(int $year, int $number): string
    {
        return sprintf('%d-%05d', $year, $number);
    }
}

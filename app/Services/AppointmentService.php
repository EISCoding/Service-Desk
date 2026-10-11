<?php

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Appointment;
use App\Models\Ticket;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Plans on-site appointments; a ticket with an appointment is in status VOS.
 */
class AppointmentService
{
    public function __construct(private TicketStatusService $statuses) {}

    public function schedule(Ticket $ticket, CarbonInterface $startsAt, CarbonInterface $endsAt, ?User $technician, string $location, ?string $note, ?User $creator): Appointment
    {
        return DB::transaction(function () use ($ticket, $startsAt, $endsAt, $technician, $location, $note, $creator) {
            $appointment = new Appointment([
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'location' => trim($location),
                'note' => filled($note) ? trim($note) : null,
            ]);
            $appointment->ticket()->associate($ticket);
            $appointment->technician()->associate($technician);
            $appointment->creator()->associate($creator);
            $appointment->save();

            if ($ticket->status !== TicketStatus::OnSite) {
                $this->statuses->change($ticket, TicketStatus::OnSite);
            } else {
                $ticket->touchActivity();
            }

            return $appointment;
        });
    }

    /**
     * Prefilled place of service: the customer's address.
     */
    public static function defaultLocation(Ticket $ticket): string
    {
        $customer = $ticket->customer;

        return $customer ? implode("\n", [$customer->company, ...$customer->addressLines()]) : '';
    }
}

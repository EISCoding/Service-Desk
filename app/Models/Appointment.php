<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * On-site service appointment (VOS) of a ticket.
 */
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory, LogsActivity;

    protected $fillable = ['starts_at', 'ends_at', 'location', 'note'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function technician(): BelongsTo
    {
        return $this->belongsTo(User::class, 'technician_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function activityParent(): ?Model
    {
        return $this->relationLoaded('ticket') ? $this->ticket : $this->ticket()->first();
    }

    /**
     * Appointments that have not ended yet.
     */
    #[Scope]
    protected function upcoming(Builder $query): void
    {
        $query->where('ends_at', '>=', now());
    }

    /**
     * e.g. "Do, 15. Okt 2026, 09:00–10:30"
     */
    public function timeRange(): string
    {
        return $this->starts_at->isoFormat('dd, D. MMM YYYY, HH:mm').'–'.$this->ends_at->format('H:i');
    }
}

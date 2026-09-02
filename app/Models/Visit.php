<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends Model
{
    use HasFactory;

    protected $fillable = [
        'visitor_id',
        'host_id',
        'purpose',
        'check_in_at',
        'check_out_at',
        'status',
        'badge_number',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'check_in_at'  => 'datetime',
            'check_out_at' => 'datetime',
        ];
    }

    public function visitor(): BelongsTo
    {
        return $this->belongsTo(Visitor::class);
    }

    public function host(): BelongsTo
    {
        return $this->belongsTo(Host::class);
    }

    public function scopeCheckedIn($query)
    {
        return $query->where('status', 'checked_in');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('check_in_at', today());
    }

    public function scopeThisMonth($query)
    {
        return $query->whereMonth('check_in_at', now()->month)
            ->whereYear('check_in_at', now()->year);
    }

    /**
     * Scope to filter visits within a date range
     * 
     * DATA ACCURACY (Requirement 15.2, 15.4):
     * - Only includes visits with valid check_in_at timestamps
     * - Uses timezone-aware Carbon date boundaries (startOfDay/endOfDay)
     * - Ensures consistent date bucketing across all queries
     */
    public function scopeInPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('check_in_at', [
            $startDate->copy()->startOfDay(),
            $endDate->copy()->endOfDay()
        ]);
    }

    /**
     * Scope to exclude cancelled visits
     * 
     * DATA ACCURACY (Requirement 15.3):
     * - Cancelled visits are systematically excluded from all analytics
     * - Ensures only meaningful visit data is included in calculations
     */
    public function scopeExcludingCancelled($query)
    {
        return $query->where('status', '!=', 'cancelled');
    }

    /**
     * Scope to get only completed visits (with check-out)
     * 
     * DATA ACCURACY (Requirement 15.5):
     * - Safely handles null check_out_at values
     * - Only includes visits with valid completion timestamps
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'checked_out')
            ->whereNotNull('check_out_at');
    }

    public function isActive(): bool
    {
        return $this->status === 'checked_in';
    }

    public function duration(): ?string
    {
        if (! $this->check_out_at) {
            return null;
        }

        $minutes = $this->check_in_at->diffInMinutes($this->check_out_at);

        if ($minutes < 60) {
            return $minutes . ' min';
        }

        $hours = floor($minutes / 60);
        $rem   = $minutes % 60;

        return $rem > 0 ? "{$hours}h {$rem}min" : "{$hours}h";
    }

    public function statusBadgeClass(): string
    {
        return match ($this->status) {
            'checked_in'  => 'bg-green-100 text-green-800',
            'checked_out' => 'bg-gray-100 text-gray-700',
            'cancelled'   => 'bg-red-100 text-red-700',
            default       => 'bg-gray-100 text-gray-700',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'checked_in'  => 'Checked In',
            'checked_out' => 'Checked Out',
            'cancelled'   => 'Cancelled',
            default       => ucfirst($this->status),
        };
    }
}

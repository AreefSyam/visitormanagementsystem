<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Visitor extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'company',
        'id_type',
        'id_number',
        'photo',
    ];

    protected function casts(): array
    {
        return [
            'id_type' => 'string',
        ];
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function activeVisit(): HasMany
    {
        return $this->hasMany(Visit::class)->where('status', 'checked_in');
    }

    public function isCurrentlyCheckedIn(): bool
    {
        return $this->visits()->where('status', 'checked_in')->exists();
    }

    public static function idTypeLabel(string $type): string
    {
        return match ($type) {
            'ic'              => 'IC / MyKad',
            'passport'        => 'Passport',
            'driving_license' => 'Driving License',
            default           => 'Other',
        };
    }
}

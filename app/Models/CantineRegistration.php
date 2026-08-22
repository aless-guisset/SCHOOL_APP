<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CantineRegistration extends Model
{
    use SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'school_id', 'section_user_id', 'day_of_week',
        'status', 'is_active', 'created_by', 'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'day_of_week' => 'integer',
    ];

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function sectionUser(): BelongsTo
    {
        return $this->belongsTo(SectionUserSchoolRole::class, 'section_user_id');
    }

    public function presences(): HasMany
    {
        return $this->hasMany(CantinePresence::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}

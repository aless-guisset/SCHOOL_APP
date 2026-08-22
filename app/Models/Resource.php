<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Resource extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'school_id',
        'name',
        'type',
        'reference',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
    ];

    // ---- Relations ----

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}

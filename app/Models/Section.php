<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Database\Factories\SectionFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SectionFactory::class)]
class Section extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'school_id',
        'name',
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

    public function sectionUsers()
    {
        return $this->hasMany(SectionUserSchoolRole::class, 'section_id');
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->where('school_id', $schoolId);
    }
}

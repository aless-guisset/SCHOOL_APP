<?php

namespace App\Models;

use App\Concerns\ScopesRouteBindingToActiveSchool;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SubjectFactory::class)]
class Subject extends Model
{
    use HasFactory, SoftDeletes, ScopesRouteBindingToActiveSchool;

    protected $fillable = [
        'course_id',
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

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    protected function applySchoolScope(Builder $query, int $schoolId): Builder
    {
        return $query->whereHas('course', fn ($q) => $q->where('school_id', $schoolId));
    }
}

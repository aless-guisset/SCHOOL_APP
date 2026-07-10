<?php

namespace App\Models;

use Database\Factories\SectionUserFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(SectionUserFactory::class)]
class SectionUserSchoolRole extends Model
{
    protected $table = 'section_users';
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'section_id',
        'user_school_role_id',
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

    public function section()
    {
        return $this->belongsTo(Section::class);
    }

    // Alias pour compatibilité avec le code existant
    public function sections()
    {
        return $this->belongsTo(Section::class);
    }

    public function userschoolrole()
    {
        return $this->belongsTo(UserSchoolRole::class);
    }

    public function sectionCourses()
    {
        return $this->hasMany(SectionCourse::class, 'section_user_id');
    }
}

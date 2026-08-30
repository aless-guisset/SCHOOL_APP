<?php

namespace App\Models;

use Database\Factories\UserSchoolRoleFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[UseFactory(UserSchoolRoleFactory::class)]
class UserSchoolRole extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'users_schools_roles';

    protected $fillable = [
        'user_id',
        'school_id',
        'role_id',
        'student_access_code',
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function school()
    {
        return $this->belongsTo(School::class);
    }

    public function role()
    {
        return $this->belongsTo(Role::class);
    }

    public function parentLinks(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'parent_user_school_role_id');
    }

    public function linkedAsChildIn(): HasMany
    {
        return $this->hasMany(ParentStudentLink::class, 'student_user_school_role_id');
    }

    public function sectionUserRoles(): HasMany
    {
        return $this->hasMany(SectionUserSchoolRole::class, 'user_school_role_id');
    }
}

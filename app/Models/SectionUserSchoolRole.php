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
        return $this->belongsTo(UserSchoolRole::class, 'user_school_role_id');
    }

    public function sectionCourses()
    {
        return $this->hasMany(SectionCourse::class, 'section_user_id');
    }

    public function cantineTransactions()
    {
        return $this->hasMany(CantineTransaction::class, 'section_user_id');
    }

    /**
     * Le solde n'est jamais stocké comme compteur à part — toujours recalculé
     * depuis le registre de transactions, pour éliminer tout risque de
     * désynchronisation. Le volume par élève reste trop faible pour que ça
     * coûte en performance.
     */
    public function cantineBalance(): float
    {
        return (float) $this->cantineTransactions()->where('is_active', true)->sum('amount');
    }
}

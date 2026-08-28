<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, SoftDeletes, TwoFactorAuthenticatable;

    protected $fillable = [
        'firstname',
        'lastname',
        'email',
        'password',
        'profile',
        'phone_number',
        'reference',
        'description',
        'status',
        'is_active',
        'created_by',
        'updated_by',
        'default_school_id',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_deleted' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }

    // ---- Relations ----

    public function defaultSchool(): BelongsTo
    {
        return $this->belongsTo(School::class, 'default_school_id');
    }

    public function schoolRoles(): HasMany
    {
        return $this->hasMany(UserSchoolRole::class);
    }

    // ---- Helpers ----

    /**
     * Retourne toutes les écoles auxquelles l'utilisateur est rattaché.
     */
    public function schools()
    {
        return School::whereIn('id', $this->schoolRoles()->pluck('school_id'))->get();
    }

    /**
     * Vérifie si l'utilisateur appartient à au moins une école.
     */
    public function hasSchool(): bool
    {
        return $this->schoolRoles()->exists();
    }

    /**
     * Retourne l'école à utiliser par défaut après le login.
     * Priorité : default_school_id → première école active.
     */
    public function resolveDefaultSchool(): ?School
    {
        if ($this->default_school_id) {
            $school = $this->defaultSchool;
            if ($school && $this->schoolRoles()->where('school_id', $this->default_school_id)->where('status', 'A')->exists()) {
                return $school;
            }
        }

        $firstRole = $this->schoolRoles()->where('is_active', true)->where('status', 'A')->first();

        return $firstRole ? $firstRole->school : null;
    }

    /**
     * Rôle actuellement actif (status='A', is_active=true) de l'utilisateur dans une école donnée —
     * priorité au rôle le plus privilégié si plusieurs lignes existent (ne devrait normalement pas
     * arriver, mais protège contre le cas où c'est arrivé).
     */
    public function activeRoleAt(int $schoolId): ?string
    {
        $privilegeOrder = ['Administrateur', 'Directeur', 'Power User', 'Secrétariat', 'Professeur', 'Élève'];

        $roles = $this->schoolRoles()
            ->with('role')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', 'A')
            ->get()
            ->pluck('role.name')
            ->filter();

        foreach ($privilegeOrder as $candidate) {
            if ($roles->contains($candidate)) {
                return $candidate;
            }
        }

        return $roles->first();
    }
}

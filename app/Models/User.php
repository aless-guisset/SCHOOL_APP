<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
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
     * Ordre de privilège départageant plusieurs lignes UserSchoolRole d'un même
     * utilisateur dans une même école. Ce cas est légitime : un Professeur peut
     * être aussi le parent d'un élève de son établissement. 'Parent' est en
     * dernier — le rôle le moins privilégié ne doit jamais éclipser un rôle
     * exercé en propre.
     */
    private const PRIVILEGE_ORDER = [
        'Administrateur', 'Directeur', 'Power User', 'Secrétariat', 'Professeur', 'Élève', 'Parent',
    ];

    /**
     * Lignes actives (status='A', is_active=true) de l'utilisateur dans une école,
     * triées du rôle le plus privilégié au moins privilégié. Les rôles inconnus de
     * PRIVILEGE_ORDER sont placés en fin de liste, dans leur ordre en base.
     *
     * @return Collection<int, UserSchoolRole>
     */
    private function activeSchoolRolesAt(int $schoolId): Collection
    {
        return $this->schoolRoles()
            ->with('role')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', 'A')
            ->get()
            ->sortBy(function (UserSchoolRole $usr) {
                $rank = array_search($usr->role?->name, self::PRIVILEGE_ORDER, true);

                return $rank === false ? PHP_INT_MAX : $rank;
            })
            ->values();
    }

    /**
     * Rôle actuellement actif (status='A', is_active=true) de l'utilisateur dans une école donnée —
     * priorité au rôle le plus privilégié si plusieurs lignes existent.
     */
    public function activeRoleAt(int $schoolId): ?string
    {
        return $this->activeSchoolRolesAt($schoolId)
            ->pluck('role.name')
            ->filter()
            ->first();
    }

    /**
     * Ligne UserSchoolRole dont les données doivent être affichées à cet
     * utilisateur pour cette école : la sienne pour la plupart des rôles,
     * celle de l'enfant lié pour un Parent. Retourne null si aucune ligne
     * active n'existe, ou si l'enfant lié a lui-même perdu son accès actif
     * entre-temps (l'école a désactivé l'élève : le parent ne doit plus
     * rien voir plutôt que d'afficher les données figées d'un élève parti).
     *
     * La ligne de départ est la plus privilégiée (même tri que activeRoleAt()) :
     * le rôle rapporté à l'appelant et les données qu'on lui montre proviennent
     * ainsi toujours de la MÊME ligne. Un compte qui est à la fois Professeur et
     * Parent dans une école voit donc ses propres données de prof, jamais celles
     * de son enfant ; la branche Parent ne s'applique que si toutes ses lignes
     * actives dans cette école sont des lignes PARENT.
     */
    public function scopedUserSchoolRole(int $schoolId): ?UserSchoolRole
    {
        $usr = $this->activeSchoolRolesAt($schoolId)->first();

        if ($usr?->role?->reference === 'PARENT') {
            return $usr->linkedStudentUserSchoolRole()
                ->where('status', 'A')
                ->where('is_active', true)
                ->first();
        }

        return $usr;
    }
}

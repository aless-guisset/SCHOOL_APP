<?php

namespace App\Concerns;

use App\Models\UserSchoolRole;

trait GrantsSchoolRoles
{
    /**
     * Octroie (ou restaure) un UserSchoolRole pour ce triplet user/school/role.
     * Gère deux pièges : une ligne soft-deleted invisible à firstOrCreate() (la
     * contrainte UNIQUE(user_id, school_id, role_id) n'inclut pas deleted_at →
     * 500 sur INSERT), et une ligne status='R' (rejetée) qu'il faut réactiver
     * plutôt que laisser bloquée pour toujours. Un statut déjà 'A' ou 'P'
     * existant est préservé tel quel (idempotent) ; $defaultStatus ne
     * s'applique qu'aux lignes neuves ou précédemment rejetées.
     */
    protected function grantOrRestoreSchoolRole(
        int $userId,
        int $schoolId,
        int $roleId,
        string $defaultStatus,
        int $actorId
    ): UserSchoolRole {
        $userSchoolRole = UserSchoolRole::withTrashed()->firstOrNew(
            ['user_id' => $userId, 'school_id' => $schoolId, 'role_id' => $roleId]
        );

        if ($userSchoolRole->trashed()) {
            $userSchoolRole->restore();
        }

        $userSchoolRole->fill([
            'status' => $userSchoolRole->exists && $userSchoolRole->status !== 'R' ? $userSchoolRole->status : $defaultStatus,
            'is_active' => true,
            'created_by' => $userSchoolRole->created_by ?? $actorId,
            'updated_by' => $actorId,
        ])->save();

        return $userSchoolRole;
    }
}

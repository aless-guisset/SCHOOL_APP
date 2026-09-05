<?php

namespace App\Concerns;

use App\Models\Role;
use App\Models\School;
use App\Models\SchoolInvitation;
use App\Notifications\SchoolInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

trait CreatesSchoolInvitations
{
    /**
     * Crée une invitation active pour cette adresse dans cette école et
     * envoie l'email — désactive d'abord toute invitation encore active pour
     * la même adresse (évite d'avoir plusieurs liens valides en même temps
     * pour la même personne). Logique partagée entre l'invitation manuelle
     * post-approbation (SchoolInvitationsController::store()) et la
     * conversion des brouillons d'invitation à l'approbation d'une école
     * (SchoolsController::approve()).
     */
    protected function createSchoolInvitation(School $school, string $email, string $roleReference, int $actorId): SchoolInvitation
    {
        $role = Role::where('reference', $roleReference)->firstOrFail();

        SchoolInvitation::where('school_id', $school->id)
            ->where('email', $email)
            ->where('is_active', true)
            ->whereNull('accepted_at')
            ->get()
            ->each(function (SchoolInvitation $old) use ($actorId) {
                $old->update(['is_active' => false, 'updated_by' => $actorId]);
                $old->delete();
            });

        $invitation = SchoolInvitation::create([
            'school_id' => $school->id,
            'email' => $email,
            'role_id' => $role->id,
            'token' => Str::random(48),
            'expires_at' => now()->addDays(7),
            'is_active' => true,
            'created_by' => $actorId,
        ]);

        Notification::route('mail', $email)->notify(new SchoolInvitationNotification($invitation));

        return $invitation;
    }
}

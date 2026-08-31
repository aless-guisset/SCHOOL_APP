<?php

namespace App\Observers;

use App\Models\Grade;
use App\Notifications\GradeAddedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class GradeObserver
{
    public function created(Grade $grade): void
    {
        $studentUsr = $grade->sectionUser?->userschoolrole;

        if (! $studentUsr) {
            return;
        }

        $parents = $studentUsr->activeParentUsers();

        if ($parents->isEmpty()) {
            return;
        }

        // L'événement `created` est émis DANS la transaction de
        // GradesController::store() : un envoi d'email qui échoue ferait
        // remonter l'exception jusqu'au closure de transaction et annulerait
        // la note. On diffère donc l'envoi après le commit (exécution
        // immédiate s'il n'y a aucune transaction ouverte), et on encapsule
        // l'envoi lui-même — un échec de livraison ne doit jamais transformer
        // un enregistrement réussi en erreur 500.
        DB::afterCommit(function () use ($parents, $grade) {
            try {
                Notification::send($parents, new GradeAddedNotification($grade));
            } catch (\Throwable $e) {
                Log::warning('Notification parent (nouvelle note) échouée', [
                    'grade_id' => $grade->id,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}

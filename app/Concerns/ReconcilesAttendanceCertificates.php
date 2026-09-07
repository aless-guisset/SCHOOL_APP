<?php

namespace App\Concerns;

use App\Models\Attendance;
use App\Models\MedicalCertificate;

trait ReconcilesAttendanceCertificates
{
    /**
     * Répercussion rétroactive : passe en Justifié toutes les présences déjà
     * prises pour l'élève de ce certificat, dont la date du Timesheet tombe
     * dans sa plage — idempotent, ne touche jamais un Présent.
     */
    protected function reconcileCertificate(MedicalCertificate $certificate): void
    {
        Attendance::where('section_user_id', $certificate->section_user_id)
            ->whereIn('presence_status', ['A', 'R'])
            ->whereHas('timesheet', fn ($q) => $q
                ->whereBetween('date', [$certificate->starts_at->toDateString(), $certificate->ends_at->toDateString()]))
            ->update([
                'justification_status' => 'J',
                'medical_certificate_id' => $certificate->id,
            ]);
    }

    /**
     * Répercussion prospective : le certificat actif (le plus récent en cas
     * de chevauchement) qui couvre cette date pour cet élève, ou null.
     * Utilisé à la prise de présence pour forcer la justification.
     */
    protected function activeCertificateCovering(int $sectionUserId, string $date): ?MedicalCertificate
    {
        return MedicalCertificate::where('section_user_id', $sectionUserId)
            ->where('status', 'A')
            ->where('starts_at', '<=', $date)
            ->where('ends_at', '>=', $date)
            ->latest('id')
            ->first();
    }
}

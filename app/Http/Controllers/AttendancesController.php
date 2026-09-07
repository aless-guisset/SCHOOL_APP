<?php

namespace App\Http\Controllers;

use App\Concerns\ReconcilesAttendanceCertificates;
use App\Concerns\ResolvesAttendanceRoster;
use App\Models\Attendance;
use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendancesController extends Controller
{
    use ReconcilesAttendanceCertificates, ResolvesAttendanceRoster;

    public function store(Request $request, Timesheet $timesheet): RedirectResponse
    {
        abort_unless($timesheet->userSchoolRole?->school_id == session('active_school_id'), 404);

        // Verrou définitif : une fois les présences soumises pour ce cours,
        // plus personne ne peut les modifier — décision explicite (voir spec),
        // aucun mécanisme de déverrouillage prévu, même pour le staff.
        if ($timesheet->attendance_submitted_at !== null) {
            return back()->withErrors(['attendances' => 'Les présences de ce cours ont déjà été soumises et ne peuvent plus être modifiées.']);
        }

        // On ne peut pas prendre les présences d'un cours qui n'a pas encore eu lieu.
        if (Carbon::parse($timesheet->date)->gt(Carbon::today())) {
            return back()->withErrors(['attendances' => 'Ce cours n\'a pas encore eu lieu, impossible de prendre les présences à l\'avance.']);
        }

        $validSectionUserIds = $this->eligibleAttendanceStudents($timesheet)?->pluck('id')->all() ?? [];

        $data = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.section_user_id' => ['required', 'integer', Rule::in($validSectionUserIds)],
            'attendances.*.presence_status' => ['required', Rule::in(['P', 'A', 'R'])],
            'attendances.*.justification_status' => ['nullable', Rule::in(['J', 'I'])],
            'attendances.*.note' => 'nullable|string|max:1000',
        ]);

        foreach ($data['attendances'] as $row) {
            $attendance = Attendance::firstOrNew([
                'timesheet_id' => $timesheet->id,
                'section_user_id' => $row['section_user_id'],
            ]);
            $attendance->presence_status = $row['presence_status'];
            $attendance->justification_status = $row['presence_status'] === 'P'
                ? null
                : ($row['justification_status'] ?? 'I');
            $attendance->medical_certificate_id = null;

            // Un certificat actif couvrant cette date fait foi, quelle que
            // soit la valeur envoyée par le client — aucune exception
            // possible depuis cet écran (voir spec, section "Erreurs et cas
            // limites").
            if ($attendance->presence_status !== 'P') {
                $certificate = $this->activeCertificateCovering($row['section_user_id'], $timesheet->date);
                if ($certificate) {
                    $attendance->justification_status = 'J';
                    $attendance->medical_certificate_id = $certificate->id;
                }
            }

            $attendance->note = $row['note'] ?? null;
            $attendance->status = 'A';
            $attendance->updated_by = $request->user()->id;
            if (! $attendance->exists) {
                $attendance->created_by = $request->user()->id;
            }
            $attendance->save();
        }

        $timesheet->update(['attendance_submitted_at' => now()]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Présences enregistrées.']);
    }
}

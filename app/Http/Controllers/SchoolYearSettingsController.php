<?php

namespace App\Http\Controllers;

use App\Models\School;
use App\Services\ScheduleTimesheetSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SchoolYearSettingsController extends Controller
{
    public function update(Request $request, ScheduleTimesheetSync $sync): RedirectResponse
    {
        $data = $request->validate([
            'year_end_date' => 'required|date|after:today',
        ]);

        $school = School::findOrFail(session('active_school_id'));
        $school->update([
            'year_end_date' => $data['year_end_date'],
            'updated_by' => $request->user()->id,
        ]);

        $sync->syncSchool($school->fresh());

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'Fin d\'année scolaire mise à jour, planning resynchronisé.',
        ]);
    }
}

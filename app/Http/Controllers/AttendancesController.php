<?php

namespace App\Http\Controllers;

use App\Concerns\ResolvesAttendanceRoster;
use App\Models\Attendance;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendancesController extends Controller
{
    use ResolvesAttendanceRoster;

    public function store(Request $request, Timesheet $timesheet): RedirectResponse
    {
        abort_unless($timesheet->userSchoolRole?->school_id == session('active_school_id'), 404);

        $validSectionUserIds = $this->eligibleAttendanceStudents($timesheet)?->pluck('id')->all() ?? [];

        $data = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.section_user_id' => ['required', 'integer', Rule::in($validSectionUserIds)],
            'attendances.*.is_present' => 'required|boolean',
            'attendances.*.note' => 'nullable|string|max:1000',
        ]);

        foreach ($data['attendances'] as $row) {
            $attendance = Attendance::firstOrNew([
                'timesheet_id' => $timesheet->id,
                'section_user_id' => $row['section_user_id'],
            ]);
            $attendance->is_present = $row['is_present'];
            $attendance->note = $row['note'] ?? null;
            $attendance->status = 'A';
            $attendance->updated_by = $request->user()->id;
            if (! $attendance->exists) {
                $attendance->created_by = $request->user()->id;
            }
            $attendance->save();
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Présences enregistrées.']);
    }
}

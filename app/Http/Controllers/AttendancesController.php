<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\SectionUserSchoolRole;
use App\Models\Timesheet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AttendancesController extends Controller
{
    public function store(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $sectionId = $timesheet->schedule?->sectionCourse?->sectionUser?->section_id;

        $validSectionUserIds = $sectionId
            ? SectionUserSchoolRole::where('section_id', $sectionId)->pluck('id')->all()
            : [];

        $data = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.section_user_id' => ['required', 'integer', Rule::in($validSectionUserIds)],
            'attendances.*.is_present' => 'required|boolean',
            'attendances.*.note' => 'nullable|string',
        ]);

        foreach ($data['attendances'] as $row) {
            Attendance::updateOrCreate(
                ['timesheet_id' => $timesheet->id, 'section_user_id' => $row['section_user_id']],
                [
                    'is_present' => $row['is_present'],
                    'note' => $row['note'] ?? null,
                    'updated_by' => $request->user()->id,
                    'created_by' => $request->user()->id,
                ]
            );
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Présences enregistrées.']);
    }
}

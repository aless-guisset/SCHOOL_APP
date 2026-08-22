<?php

namespace App\Http\Controllers;

use App\Models\Timesheet;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Duplique une semaine de référence (tous ses Timesheets) vers
 * toutes les semaines jusqu'à une date de fin.
 * Source : lundi de la semaine de référence.
 * Skip : si un Timesheet existe déjà pour ce schedule + date.
 */
class DuplicatePlanningController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'source_week_start' => 'required|date',
            'year_end'          => 'required|date|after:source_week_start',
        ]);

        $sourceMonday = Carbon::parse($data['source_week_start'])->startOfWeek(Carbon::MONDAY);
        $yearEnd      = Carbon::parse($data['year_end'])->endOfDay();

        // Timesheets de la semaine source (lun → dim)
        $sourceSunday = $sourceMonday->copy()->endOfWeek(Carbon::SUNDAY);

        $schoolId = session('active_school_id');

        $sourceTimesheets = Timesheet::whereBetween('date', [$sourceMonday->toDateString(), $sourceSunday->toDateString()])
            ->whereHas('userSchoolRole', fn ($q) => $q->where('school_id', $schoolId))
            ->whereNull('deleted_at')
            ->get();

        if ($sourceTimesheets->isEmpty()) {
            return response()->json(['message' => 'Aucun timesheet trouvé dans la semaine source.', 'created' => 0], 422);
        }

        $created = 0;
        $week = 1;

        while (true) {
            $targetMonday = $sourceMonday->copy()->addWeeks($week);
            if ($targetMonday->gt($yearEnd)) break;

            foreach ($sourceTimesheets as $ts) {
                $dayOffset = Carbon::parse($ts->date)->dayOfWeekIso - 1; // 0=lun
                $targetDate = $targetMonday->copy()->addDays($dayOffset);

                if ($targetDate->gt($yearEnd)) continue;

                $alreadyExists = Timesheet::where('schedule_id', $ts->schedule_id)
                    ->where('date', $targetDate->toDateString())
                    ->whereNull('deleted_at')
                    ->exists();

                if ($alreadyExists) continue;

                Timesheet::create([
                    'user_school_role_id' => $ts->user_school_role_id,
                    'schedule_id'         => $ts->schedule_id,
                    'subject_id'          => $ts->subject_id,
                    'classroom_id'        => $ts->classroom_id,
                    'date'                => $targetDate->toDateString(),
                    'hours_done'          => $ts->hours_done,
                    'is_active'           => true,
                    'created_by'          => $request->user()->id,
                ]);

                $created++;
            }

            $week++;
        }

        return response()->json([
            'message' => "$created créneau(x) générés.",
            'created' => $created,
        ]);
    }
}

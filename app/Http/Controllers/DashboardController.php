<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Schedule;
use App\Models\SectionUserSchoolRole;
use App\Models\Timesheet;
use App\Models\UserSchoolRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Rôles ayant une vue d'ensemble (horaire complet + activité récente) sur
     * l'école active. Administrateur en est exclu : rôle de gestion
     * plateforme sans autorité sur le contenu académique d'une école en
     * particulier (CLAUDE.md), même s'il possède une ligne UserSchoolRole
     * (role Administrateur) rattachée à une school_id précise.
     */
    private const MANAGE_ROLES = ['Power User', 'Directeur'];

    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $user = $request->user();

        $usr = UserSchoolRole::with('role')
            ->where('user_id', $user->id)
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->where('status', 'A')
            ->first();

        $currentRole = $usr?->role?->name;

        return Inertia::render('Dashboard', [
            'week_schedule' => $this->weekSchedule($schoolId, $usr, $currentRole),
            'recent_activity' => $this->recentActivity($schoolId, $currentRole),
        ]);
    }

    private function weekSchedule(?int $schoolId, ?UserSchoolRole $usr, ?string $currentRole): array
    {
        $weekStart = now()->startOfWeek(Carbon::MONDAY)->toDateString();
        $weekEnd = Carbon::parse($weekStart)->endOfWeek(Carbon::SUNDAY)->toDateString();

        $query = Schedule::with('sectionCourse.course')->where('is_active', true);

        if (in_array($currentRole, self::MANAGE_ROLES, true)) {
            $query->whereHas('sectionCourse.course', fn ($q) => $q->where('school_id', $schoolId));
        } elseif ($currentRole === 'Professeur' && $usr) {
            $sectionUserIds = SectionUserSchoolRole::where('user_school_role_id', $usr->id)->pluck('id');
            $query->whereHas('sectionCourse', fn ($q) => $q->whereIn('section_user_id', $sectionUserIds));
        } elseif ($usr) {
            // Élève (et tout rôle sans portée de gestion connue) : créneaux de sa/ses section(s),
            // retrouvés via les section_users des professeurs qui partagent le même section_id.
            $sectionIds = SectionUserSchoolRole::where('user_school_role_id', $usr->id)->pluck('section_id');
            $sectionUserIds = SectionUserSchoolRole::whereIn('section_id', $sectionIds)->pluck('id');
            $query->whereHas('sectionCourse', fn ($q) => $q->whereIn('section_user_id', $sectionUserIds));
        } else {
            $query->whereRaw('1 = 0');
        }

        $schedules = $query->get();

        $timesheetsBySchedule = Timesheet::whereIn('schedule_id', $schedules->pluck('id'))
            ->whereBetween('date', [$weekStart, $weekEnd])
            ->with(['userSchoolRole.user', 'classroom', 'subject'])
            ->get()
            ->keyBy('schedule_id');

        $slots = $schedules->map(function (Schedule $schedule) use ($timesheetsBySchedule) {
            $timesheet = $timesheetsBySchedule->get($schedule->id);

            return [
                'schedule_id' => $schedule->id,
                'day_of_week' => $schedule->day_of_week,
                'start_time' => $schedule->start_time,
                'end_time' => $schedule->end_time,
                'course_label' => $schedule->sectionCourse?->name ?? $schedule->name,
                'teacher' => $timesheet?->userSchoolRole?->user
                    ? "{$timesheet->userSchoolRole->user->firstname} {$timesheet->userSchoolRole->user->lastname}"
                    : null,
                'classroom' => $timesheet?->classroom?->name,
                'subject' => $timesheet?->subject?->name,
            ];
        })->values()->all();

        return [
            'week_start' => $weekStart,
            'slots' => $slots,
        ];
    }

    private function recentActivity(?int $schoolId, ?string $currentRole): ?array
    {
        if (! in_array($currentRole, self::MANAGE_ROLES, true)) {
            return null;
        }

        return ActivityLog::where('school_id', $schoolId)
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (ActivityLog $log) => [
                'id' => $log->id,
                'event' => $log->event,
                'model_label' => $log->model_label,
                'model_type' => class_basename($log->model_type),
                'user_name' => $log->user ? "{$log->user->firstname} {$log->user->lastname}" : $log->user_email,
                'created_at' => $log->created_at->toIso8601String(),
            ])
            ->all();
    }
}

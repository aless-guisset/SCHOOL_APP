<?php

namespace App\Observers;

use App\Models\Schedule;
use App\Services\ScheduleTimesheetSync;

class ScheduleObserver
{
    public function __construct(private ScheduleTimesheetSync $sync) {}

    public function created(Schedule $schedule): void
    {
        $this->sync->sync($schedule);
    }

    public function updated(Schedule $schedule): void
    {
        $this->sync->sync($schedule);
    }

    public function restored(Schedule $schedule): void
    {
        $this->sync->sync($schedule);
    }

    public function deleted(Schedule $schedule): void
    {
        $this->sync->deleteFutureStandard($schedule);
    }
}

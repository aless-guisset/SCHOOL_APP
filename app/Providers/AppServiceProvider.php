<?php

namespace App\Providers;

use App\Models\Classroom;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Resource;
use App\Models\Role;
use App\Models\Schedule;
use App\Models\School;
use App\Models\Section;
use App\Models\Subject;
use App\Models\Timesheet;
use App\Models\Translation;
use App\Models\User;
use App\Models\UserSchoolRole;
use App\Listeners\LogSuccessfulLogin;
use App\Observers\ActivityObserver;
use App\Observers\GradeObserver;
use App\Observers\ScheduleObserver;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->registerActivityObservers();
        Schedule::observe(ScheduleObserver::class);
        Grade::observe(GradeObserver::class);
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function registerActivityObservers(): void
    {
        $models = [
            Classroom::class,
            Course::class,
            Lesson::class,
            Resource::class,
            Role::class,
            Schedule::class,
            School::class,
            Section::class,
            Subject::class,
            Timesheet::class,
            Translation::class,
            User::class,
            UserSchoolRole::class,
        ];

        foreach ($models as $model) {
            $model::observe(ActivityObserver::class);
        }

        Event::listen(Login::class, LogSuccessfulLogin::class);
    }

    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}

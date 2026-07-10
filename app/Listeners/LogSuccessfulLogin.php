<?php

namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Request;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        ActivityLog::create([
            'event'      => 'login',
            'model_type' => get_class($event->user),
            'model_id'   => $event->user->getKey(),
            'model_label'=> $event->user->email,
            'user_id'    => $event->user->getKey(),
            'user_email' => $event->user->email,
            'ip_address' => Request::ip(),
        ]);
    }
}

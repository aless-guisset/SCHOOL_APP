<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ActivityLogsController extends Controller
{
    public function index(Request $request): Response
    {
        $query = ActivityLog::with('user')
            ->latest()
            ->when($request->event, fn ($q) => $q->where('event', $request->event))
            ->when($request->model, fn ($q) => $q->where('model_type', 'like', '%'.$request->model.'%'))
            ->when($request->user_id, fn ($q) => $q->where('user_id', $request->user_id))
            ->when($request->date_from, fn ($q) => $q->whereDate('created_at', '>=', $request->date_from))
            ->when($request->date_to, fn ($q) => $q->whereDate('created_at', '<=', $request->date_to));

        return Inertia::render('admin/web/ActivityLogs/Index', [
            'logs'    => $query->paginate(50)->withQueryString(),
            'filters' => $request->only(['event', 'model', 'user_id', 'date_from', 'date_to']),
        ]);
    }
}

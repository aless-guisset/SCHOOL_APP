<?php

namespace App\Http\Controllers;

use App\Models\SchoolInvitation;
use App\Models\UserSchoolRole;
use App\Notifications\AccessRequestDecidedNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class AccessRequestsController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        $requests = UserSchoolRole::with(['user', 'role'])
            ->where('school_id', $schoolId)
            ->where('status', 'P')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (UserSchoolRole $r) => [
                'id'    => $r->id,
                'name'  => "{$r->user->firstname} {$r->user->lastname}",
                'email' => $r->user->email,
                'role'  => $r->role->name,
                'requested_at' => $r->created_at->diffForHumans(),
            ]);

        $invitations = SchoolInvitation::with('role')
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->whereNull('accepted_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (SchoolInvitation $i) => [
                'id'    => $i->id,
                'email' => $i->email,
                'role'  => $i->role->name,
                'expired' => $i->expires_at->isPast(),
                'sent_at' => $i->created_at->diffForHumans(),
            ]);

        return Inertia::render('director/web/AccessRequests/Index', [
            'requests' => $requests,
            'invitations' => $invitations,
        ]);
    }

    public function approve(UserSchoolRole $userSchoolRole): RedirectResponse
    {
        $this->authorizeSameSchool($userSchoolRole);

        $userSchoolRole->update(['status' => 'A', 'updated_by' => request()->user()->id]);
        $userSchoolRole->user->notify(new AccessRequestDecidedNotification($userSchoolRole->school, approved: true));

        return back()->with('flash', ['type' => 'success', 'message' => 'Demande acceptée.']);
    }

    public function reject(UserSchoolRole $userSchoolRole): RedirectResponse
    {
        $this->authorizeSameSchool($userSchoolRole);

        $userSchoolRole->update(['status' => 'R', 'updated_by' => request()->user()->id]);
        $userSchoolRole->user->notify(new AccessRequestDecidedNotification($userSchoolRole->school, approved: false));

        return back()->with('flash', ['type' => 'warning', 'message' => 'Demande refusée.']);
    }

    private function authorizeSameSchool(UserSchoolRole $userSchoolRole): void
    {
        abort_unless($userSchoolRole->school_id == session('active_school_id'), 404);
        abort_unless($userSchoolRole->status === 'P', 422, 'Cette demande a déjà été traitée.');
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ParentStudentLink;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ParentLinksController extends Controller
{
    public function index(): Response
    {
        $schoolId = session('active_school_id');

        $links = ParentStudentLink::with(['parentUserSchoolRole.user', 'studentUserSchoolRole.user'])
            ->whereHas('parentUserSchoolRole', fn ($q) => $q->where('school_id', $schoolId))
            // Écarte les liens dont la ligne élève a été soft-deletée (retrait
            // de rôle côté admin) : la relation vaudrait null au mapping.
            ->whereHas('studentUserSchoolRole')
            ->where('status', 'A')
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ParentStudentLink $link) => [
                'id' => $link->id,
                'parent_name' => "{$link->parentUserSchoolRole->user->firstname} {$link->parentUserSchoolRole->user->lastname}",
                'parent_email' => $link->parentUserSchoolRole->user->email,
                'student_name' => "{$link->studentUserSchoolRole->user->firstname} {$link->studentUserSchoolRole->user->lastname}",
                'linked_at' => $link->created_at->diffForHumans(),
            ]);

        return Inertia::render('director/web/ParentLinks/Index', [
            'links' => $links,
        ]);
    }

    public function revoke(ParentStudentLink $parentStudentLink): RedirectResponse
    {
        abort_unless($parentStudentLink->parentUserSchoolRole?->school_id == session('active_school_id'), 404);

        $parentStudentLink->update(['status' => 'R', 'is_active' => false, 'updated_by' => request()->user()->id]);
        $parentStudentLink->delete();

        $parentUsr = $parentStudentLink->parentUserSchoolRole;
        $remainingLinks = ParentStudentLink::where('parent_user_school_role_id', $parentUsr->id)
            ->where('status', 'A')->where('is_active', true)->count();

        if ($remainingLinks === 0) {
            $parentUsr->update(['status' => 'R', 'is_active' => false, 'updated_by' => request()->user()->id]);
        }

        return back()->with('flash', ['type' => 'success', 'message' => 'Lien révoqué.']);
    }
}

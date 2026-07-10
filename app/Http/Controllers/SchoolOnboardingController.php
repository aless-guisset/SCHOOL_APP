<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolOnboardingController extends Controller
{
    /**
     * Page de sélection d'école (quand l'utilisateur en a plusieurs).
     */
    public function select(Request $request): Response
    {
        $user = $request->user();

        $schools = School::whereIn(
            'id',
            $user->schoolRoles()->where('is_active', true)->pluck('school_id')
        )
            ->where('is_active', true)
            ->get()
            ->map(fn ($school) => [
                'id' => $school->id,
                'name' => $school->name,
                'email' => $school->email,
                'is_default' => $school->id === $user->default_school_id,
                'role' => $user->schoolRoles()
                    ->where('school_id', $school->id)
                    ->with('role')
                    ->first()
                    ?->role
                    ?->name,
            ]);

        return Inertia::render('school/Select', [
            'schools' => $schools,
        ]);
    }

    /**
     * Active une école en session.
     */
    public function activate(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
        ]);

        $user = $request->user();
        $schoolId = $request->school_id;

        // Vérifier que l'utilisateur appartient bien à cette école
        $belongs = $user->schoolRoles()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->exists();

        abort_unless($belongs, 403, 'Accès non autorisé à cet établissement.');

        session(['active_school_id' => $schoolId]);

        return redirect()->route('dashboard');
    }

    /**
     * Formulaire de demande de création d'école.
     */
    public function create(): Response
    {
        return Inertia::render('school/Create');
    }

    /**
     * Soumettre la demande de création d'école (status = pending).
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|string|max:100',
            'email'        => 'nullable|email|max:191',
            'phone_number' => 'nullable|string|max:20',
            'address'      => 'nullable|string|max:255',
            'description'  => 'nullable|string',
        ]);

        $data['status'] = 'P'; // P = Pending (en attente d'approbation admin)
        $data['is_active'] = false;
        $data['created_by'] = $request->user()->id;

        School::create($data);

        return redirect()->route('school.create')->with('flash', [
            'type' => 'success',
            'message' => 'Votre demande a été soumise. Un administrateur la traitera prochainement.',
        ]);
    }

    /**
     * Définit l'école par défaut de l'utilisateur.
     */
    public function setDefault(Request $request)
    {
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id',
        ]);

        $user = $request->user();
        $schoolId = $request->school_id;

        $belongs = $user->schoolRoles()
            ->where('school_id', $schoolId)
            ->where('is_active', true)
            ->exists();

        abort_unless($belongs, 403, 'Accès non autorisé à cet établissement.');

        $user->update(['default_school_id' => $schoolId]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'École par défaut mise à jour.',
        ]);
    }
}

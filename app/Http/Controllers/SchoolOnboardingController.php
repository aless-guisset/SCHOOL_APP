<?php

namespace App\Http\Controllers;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\School;
use App\Models\User;
use App\Notifications\SchoolPendingNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;

class SchoolOnboardingController extends Controller
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Formulaire de création de compte fondateur d'établissement.
     */
    public function createFounderAccount(): Response
    {
        return Inertia::render('auth/Register');
    }

    /**
     * Crée le compte fondateur (profile=school_owner) et le connecte.
     * `profile` n'est jamais lu depuis la requête : ce parcours ne crée que des
     * fondateurs, donc la valeur est forcée côté serveur (même convention que
     * SchoolAccessController::joinRequest() pour le rôle ELEVE).
     */
    public function registerFounder(Request $request): RedirectResponse
    {
        $data = $request->validate([
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ]);

        $user = User::create([
            'firstname' => $data['firstname'],
            'lastname'  => $data['lastname'],
            'email'     => $data['email'],
            'password'  => $data['password'],
            'profile'   => 'school_owner',
        ]);

        $request->session()->regenerate();
        Auth::login($user);

        return redirect()->route('school.create');
    }

    /**
     * Page de sélection d'école (quand l'utilisateur en a plusieurs).
     */
    public function select(Request $request): Response
    {
        $user = $request->user();

        $schools = School::whereIn(
            'id',
            $user->schoolRoles()->where('is_active', true)->where('status', 'A')->pluck('school_id')
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
                    ->where('status', 'A')
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
            ->where('status', 'A')
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

        $school = School::create($data);

        // Notifier tous les administrateurs de plateforme
        $admins = User::whereHas('schoolRoles', function ($q) {
            $q->where('is_active', true)
                ->whereHas('role', fn ($q) => $q->where('name', 'Administrateur'));
        })->get();

        Notification::send($admins, new SchoolPendingNotification($school, $request->user()));

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
            ->where('status', 'A')
            ->exists();

        abort_unless($belongs, 403, 'Accès non autorisé à cet établissement.');

        $user->update(['default_school_id' => $schoolId]);

        return back()->with('flash', [
            'type' => 'success',
            'message' => 'École par défaut mise à jour.',
        ]);
    }
}

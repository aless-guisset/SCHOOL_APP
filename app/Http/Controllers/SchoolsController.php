<?php

namespace App\Http\Controllers;

use App\Concerns\GrantsSchoolRoles;
use App\Models\Role;
use App\Models\School;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SchoolsController extends Controller
{
    use GrantsSchoolRoles;

    public function index(): Response
    {
        return Inertia::render('admin/web/Schools/Index', [
            'schools'       => School::where('is_active', true)->orderBy('name')->paginate(20),
            'pendingCount'  => School::where('status', 'P')->count(),
        ]);
    }

    // ── Demandes en attente ───────────────────────────────────────────────────
    public function pending(): Response
    {
        $pending = School::where('status', 'P')
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        // Noms des écoles déjà actives (insensible à la casse) pour détecter les doublons
        $activeNamesLower = School::where('status', 'A')
            ->where('is_active', true)
            ->whereNull('deleted_at')
            ->pluck('name')
            ->map(fn ($n) => mb_strtolower(trim($n)))
            ->all();

        $pending->getCollection()->transform(function (School $school) use ($activeNamesLower) {
            $school->setAttribute(
                'has_conflict',
                in_array(mb_strtolower(trim($school->name)), $activeNamesLower, strict: true)
            );
            return $school;
        });

        return Inertia::render('admin/web/Schools/Pending', [
            'schools' => $pending,
        ]);
    }

    public function approve(Request $request, School $school)
    {
        abort_if($school->status !== 'P', 422, 'Cette école n\'est plus en attente d\'approbation.');

        $school->update([
            'status'      => 'A',
            'is_active'   => true,
            'access_code' => $school->access_code ?? $this->generateAccessCode(),
            'updated_by'  => $request->user()->id,
        ]);

        $directeurRole = Role::where('reference', 'DIR')->first();
        if ($directeurRole && $school->created_by) {
            $this->grantOrRestoreSchoolRole(
                $school->created_by, $school->id, $directeurRole->id, 'A', $request->user()->id
            );
        }

        return redirect()->route('schools.pending')
            ->with('flash', ['type' => 'success', 'message' => "L'école \"{$school->name}\" a été approuvée."]);
    }

    /**
     * Code alphanumérique court (8 caractères), pensé pour être dicté/partagé
     * à l'oral — pas de 0/O/1/I pour éviter les confusions. Régénéré en cas de
     * collision improbable (unique() sur `access_code`).
     */
    private function generateAccessCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = collect(range(1, 8))->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])->implode('');
        } while (School::where('access_code', $code)->exists());

        return $code;
    }

    public function reject(Request $request, School $school)
    {
        abort_if($school->status !== 'P', 422, 'Cette école n\'est plus en attente d\'approbation.');

        $data = $request->validate([
            'reason' => 'nullable|string|max:255',
        ]);

        $school->update([
            'status'      => 'R',
            'is_active'   => false,
            'description' => $school->description
                ? $school->description."\n[Refus] ".($data['reason'] ?? '')
                : '[Refus] '.($data['reason'] ?? ''),
            'updated_by'  => $request->user()->id,
        ]);

        $school->delete(); // soft delete

        return redirect()->route('schools.pending')
            ->with('flash', ['type' => 'warning', 'message' => "La demande \"{$school->name}\" a été refusée."]);
    }

    // ── CRUD standard ─────────────────────────────────────────────────────────
    public function create(): Response
    {
        return Inertia::render('admin/web/Schools/Create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'         => 'required|max:80',
            'email'        => 'nullable|email',
            'phone_number' => 'nullable|max:20',
            'address'      => 'nullable|string',
            'description'  => 'nullable|string',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['status']     = 'A';
        $data['is_active']  = true;

        $school = School::create($data);

        return redirect()->route('schools.show', $school)
            ->with('flash', ['type' => 'success', 'message' => 'École créée avec succès.']);
    }

    public function show(School $school): Response
    {
        return Inertia::render('admin/web/Schools/Show', [
            'school' => $school,
        ]);
    }

    public function edit(School $school): Response
    {
        return Inertia::render('admin/web/Schools/Edit', [
            'school' => $school,
        ]);
    }

    public function update(Request $request, School $school)
    {
        $data = $request->validate([
            'name'         => 'sometimes|required|max:80',
            'email'        => 'sometimes|nullable|email',
            'phone_number' => 'sometimes|nullable|max:20',
            'address'      => 'sometimes|nullable|string',
            'description'  => 'sometimes|nullable|string',
            'is_active'    => 'sometimes|boolean',
            'cantine_enabled' => 'sometimes|boolean',
        ]);

        $data['updated_by'] = $request->user()->id;
        $school->update($data);

        return redirect()->route('schools.show', $school)
            ->with('flash', ['type' => 'success', 'message' => 'École mise à jour.']);
    }

    public function destroy(School $school)
    {
        $school->update(['is_active' => false, 'updated_by' => request()->user()->id]);
        $school->delete();

        return redirect()->route('schools.index')
            ->with('flash', ['type' => 'success', 'message' => 'École supprimée.']);
    }
}

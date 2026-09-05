<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureCanManage;
use App\Models\CantineTransaction;
use App\Models\School;
use App\Models\SectionUserSchoolRole;
use App\Services\CantineStripeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class CantineWalletController extends Controller
{
    public function __construct(private CantineStripeService $stripe) {}

    /**
     * Recharge Stripe — Parent uniquement, pour l'enfant qu'il consulte.
     * Jamais l'élève lui-même : cf. spec, "Qui recharge".
     */
    public function topUp(Request $request): HttpResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);
        abort_unless(config('services.stripe.secret'), 503, 'Le paiement en ligne n\'est pas encore configuré pour cette école.');

        $child = $request->user()->parentLinkedStudent($schoolId);
        $sectionUser = $child ? $child->sectionUserRoles()->first() : null;
        abort_unless($sectionUser, 403);

        $data = $request->validate(['amount' => 'required|numeric|min:5|max:500']);

        $url = $this->stripe->createTopUpSession($sectionUser, (float) $data['amount']);

        return Inertia::location($url);
    }

    public function topUpSuccess(): RedirectResponse
    {
        return redirect()->route('cantine.index')->with('flash', [
            'type' => 'success',
            'message' => 'Paiement reçu — le solde sera mis à jour dans quelques instants.',
        ]);
    }

    public function topUpCancel(): Response
    {
        return Inertia::render('power-user/web/Cantine/TopUpCancel');
    }

    /**
     * Liste tous les élèves de l'école active avec leur solde — personnel
     * (écriture) et Directeur (lecture seule, cf. ensureCanViewWallets()).
     */
    public function index(Request $request): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);
        $this->ensureCanViewWallets($schoolId);

        $students = SectionUserSchoolRole::whereHas(
            'userschoolrole',
            fn ($q) => $q->where('school_id', $schoolId)->whereHas('role', fn ($q2) => $q2->where('reference', 'ELEVE'))
        )->with('userschoolrole.user')->get();

        $roster = $students->map(fn (SectionUserSchoolRole $s) => [
            'id' => $s->id,
            'name' => $s->userschoolrole?->user
                ? "{$s->userschoolrole->user->lastname} {$s->userschoolrole->user->firstname}"
                : '—',
            'balance' => $s->cantineBalance(),
        ])->sortBy('name')->values();

        return Inertia::render('power-user/web/Cantine/Wallets/Index', [
            'students' => $roster,
            'can_write' => $this->userCanManageWallets($schoolId),
        ]);
    }

    /**
     * Historique des transactions d'un élève.
     */
    public function show(SectionUserSchoolRole $sectionUser): Response
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);
        $this->ensureCanViewWallets($schoolId);

        abort_unless($sectionUser->userschoolrole?->school_id === $schoolId, 404);

        $transactions = $sectionUser->cantineTransactions()
            ->where('is_active', true)
            ->orderByDesc('id')
            ->get(['id', 'type', 'amount', 'note', 'created_at']);

        return Inertia::render('power-user/web/Cantine/Wallets/Show', [
            'student_name' => $sectionUser->userschoolrole?->user
                ? "{$sectionUser->userschoolrole->user->firstname} {$sectionUser->userschoolrole->user->lastname}"
                : '—',
            'section_user_id' => $sectionUser->id,
            'balance' => $sectionUser->cantineBalance(),
            'transactions' => $transactions,
            'can_write' => $this->userCanManageWallets($schoolId),
        ]);
    }

    /**
     * Crédit manuel — paiement reçu hors ligne (espèces, chèque). Réservé au
     * personnel (can-manage), jamais au Directeur (lecture seule).
     */
    public function manualCredit(Request $request, SectionUserSchoolRole $sectionUser): RedirectResponse
    {
        $schoolId = session('active_school_id');
        $this->abortUnlessCantineEnabled($schoolId);
        abort_unless($this->userCanManageWallets($schoolId), 403);
        abort_unless($sectionUser->userschoolrole?->school_id === $schoolId, 404);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01|max:9999.99',
            'note' => 'nullable|string|max:1000',
        ]);

        CantineTransaction::create([
            'section_user_id' => $sectionUser->id,
            'type' => 'manual_credit',
            'amount' => $data['amount'],
            'note' => $data['note'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return back()->with('flash', ['type' => 'success', 'message' => 'Solde crédité.']);
    }

    /**
     * Annule un crédit manuel saisi par erreur. Restreint aux transactions
     * de type manual_credit — jamais une recharge Stripe ou un débit/
     * remboursement de commande, qui suivent leurs propres règles.
     */
    public function voidManualCredit(CantineTransaction $cantineTransaction): RedirectResponse
    {
        $schoolId = session('active_school_id');
        abort_unless($this->userCanManageWallets($schoolId), 403);
        abort_unless($cantineTransaction->sectionUser?->userschoolrole?->school_id === $schoolId, 404);
        abort_unless($cantineTransaction->type === 'manual_credit', 422);

        $cantineTransaction->update(['is_active' => false, 'updated_by' => auth()->id()]);
        $cantineTransaction->delete();

        return back()->with('flash', ['type' => 'success', 'message' => 'Crédit annulé.']);
    }

    private function ensureCanViewWallets(?int $schoolId): void
    {
        if (! $schoolId) {
            abort(403);
        }

        $role = auth()->user()->activeRoleAt($schoolId);

        abort_unless(in_array($role, [...EnsureCanManage::MANAGE_ROLES, 'Directeur'], true), 403);
    }

    private function userCanManageWallets(?int $schoolId): bool
    {
        if (! $schoolId) {
            return false;
        }

        $role = auth()->user()->activeRoleAt($schoolId);

        return in_array($role, EnsureCanManage::MANAGE_ROLES, true);
    }

    private function abortUnlessCantineEnabled(?int $schoolId): void
    {
        abort_unless(
            $schoolId && School::where('id', $schoolId)->where('cantine_enabled', true)->exists(),
            404
        );
    }
}

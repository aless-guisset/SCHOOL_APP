<?php

namespace App\Http\Controllers;

use App\Models\School;
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

        $child = $request->user()->parentLinkedStudent($schoolId);
        $sectionUser = $child ? $child->sectionUserRoles()->first() : null;
        abort_unless($sectionUser, 403);

        $data = $request->validate(['amount' => 'required|numeric|min:5|max:500']);

        $url = $this->stripe->createTopUpSession($sectionUser, (float) $data['amount']);

        return Inertia::location($url);
    }

    public function topUpSuccess(): RedirectResponse
    {
        // N'écrit rien en base — la confirmation réelle passe uniquement par
        // le webhook (StripeWebhookController). Cette page peut s'afficher
        // avant que le webhook n'arrive.
        return redirect()->route('cantine.index')->with('flash', [
            'type' => 'success',
            'message' => 'Paiement reçu — le solde sera mis à jour dans quelques instants.',
        ]);
    }

    public function topUpCancel(): Response
    {
        return Inertia::render('power-user/web/Cantine/TopUpCancel');
    }

    private function abortUnlessCantineEnabled(?int $schoolId): void
    {
        abort_unless(
            $schoolId && School::where('id', $schoolId)->where('cantine_enabled', true)->exists(),
            404
        );
    }
}

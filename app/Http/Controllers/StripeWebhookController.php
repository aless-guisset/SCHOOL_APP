<?php

namespace App\Http\Controllers;

use App\Models\CantineTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends Controller
{
    public function handle(Request $request): Response
    {
        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) config('services.stripe.webhook_secret'),
            );
        } catch (SignatureVerificationException) {
            return response('Signature invalide.', 400);
        }

        if ($event->type === 'checkout.session.completed') {
            $session = $event->data->object;
            $sectionUserId = (int) ($session->metadata->section_user_id ?? 0);
            $paymentIntentId = $session->payment_intent;

            if ($sectionUserId && $paymentIntentId) {
                // firstOrCreate() couvre le cas courant ; l'index unique sur
                // stripe_payment_intent_id (Task 1) est le vrai filet de
                // sécurité si Stripe livre le même événement deux fois en
                // concurrence (garanti "at least once" par leur système).
                try {
                    CantineTransaction::firstOrCreate(
                        ['stripe_payment_intent_id' => $paymentIntentId],
                        [
                            'section_user_id' => $sectionUserId,
                            'type' => 'stripe_topup',
                            'amount' => $session->amount_total / 100,
                            'is_active' => true,
                        ],
                    );
                } catch (QueryException) {
                    // Contrainte unique déclenchée par une livraison concurrente
                    // du même événement — rien à faire, déjà enregistré.
                }
            }
        }

        return response()->noContent();
    }
}

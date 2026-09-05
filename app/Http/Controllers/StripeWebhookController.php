<?php

namespace App\Http\Controllers;

use App\Models\CantineTransaction;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
                } catch (QueryException $e) {
                    // SQLSTATE 23000 couvre à la fois les violations de contrainte
                    // unique ET les violations de clé étrangère (même classe ANSI
                    // "integrity constraint violation") — on ne peut pas distinguer
                    // les deux à ce niveau. On accepte ce cas ambigu en silence
                    // (choix conservateur), mais toute AUTRE erreur (deadlock,
                    // connexion, schéma/syntaxe — codes SQLSTATE différents) est
                    // loguée puis relancée pour remonter en 500 et déclencher un
                    // retry Stripe, plutôt que de perdre le paiement sans trace.
                    if ($e->getCode() !== '23000') {
                        Log::error('Webhook Stripe : échec inattendu de l\'enregistrement de la transaction.', [
                            'stripe_payment_intent_id' => $paymentIntentId,
                            'section_user_id' => $sectionUserId,
                            'exception' => $e->getMessage(),
                        ]);

                        throw $e;
                    }

                    // Contrainte unique déclenchée par une livraison concurrente
                    // du même événement — rien à faire, déjà enregistré.
                }
            }
        }

        return response()->noContent();
    }
}

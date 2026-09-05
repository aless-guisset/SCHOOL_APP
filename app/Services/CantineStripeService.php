<?php

namespace App\Services;

use App\Models\SectionUserSchoolRole;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\Stripe;

class CantineStripeService
{
    /**
     * Crée une Stripe Checkout Session pour recharger le solde de $sectionUser
     * et retourne son URL — la redirection externe est à la charge de
     * l'appelant (Inertia::location(), pas un redirect() classique : Stripe
     * est un domaine externe, une redirection Inertia XHR ne peut pas le suivre).
     */
    public function createTopUpSession(SectionUserSchoolRole $sectionUser, float $amountEuros): string
    {
        Stripe::setApiKey(config('services.stripe.secret'));

        $session = StripeCheckoutSession::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'currency' => 'eur',
                    'product_data' => ['name' => 'Recharge cantine'],
                    'unit_amount' => (int) round($amountEuros * 100),
                ],
                'quantity' => 1,
            ]],
            'metadata' => ['section_user_id' => (string) $sectionUser->id],
            'success_url' => route('cantine.wallet.top-up.success'),
            'cancel_url' => route('cantine.wallet.top-up.cancel'),
        ]);

        return $session->url;
    }
}

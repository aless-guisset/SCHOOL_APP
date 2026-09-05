<?php

use App\Models\CantineTransaction;
use App\Models\Role;
use App\Models\School;
use App\Models\Section;
use App\Models\SectionUserSchoolRole;
use App\Models\User;
use App\Models\UserSchoolRole;

function makeWebhookSignature(string $payload, string $secret): string
{
    $timestamp = time();
    $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $secret);

    return "t={$timestamp},v1={$signature}";
}

function makeWebhookStudent(): SectionUserSchoolRole
{
    $school = School::create(['name' => 'École Webhook', 'status' => 'A', 'is_active' => true, 'cantine_enabled' => true, 'created_by' => 1]);
    $section = Section::create(['school_id' => $school->id, 'name' => 'Classe', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $role = Role::firstOrCreate(['reference' => 'ELEVE'], ['name' => 'Élève', 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
    $usr = UserSchoolRole::create(['user_id' => User::factory()->create()->id, 'school_id' => $school->id, 'role_id' => $role->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);

    return SectionUserSchoolRole::create(['section_id' => $section->id, 'user_school_role_id' => $usr->id, 'status' => 'A', 'is_active' => true, 'created_by' => 1]);
}

function checkoutCompletedPayload(int $sectionUserId, string $paymentIntentId, int $amountCents = 1000): string
{
    return json_encode([
        'id' => 'evt_test_'.uniqid(),
        'object' => 'event',
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.uniqid(),
                'object' => 'checkout.session',
                'amount_total' => $amountCents,
                'payment_intent' => $paymentIntentId,
                'metadata' => ['section_user_id' => (string) $sectionUserId],
            ],
        ],
    ]);
}

beforeEach(function () {
    config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
});

test('a valid webhook signature creates a stripe_topup transaction', function () {
    $student = makeWebhookStudent();
    $payload = checkoutCompletedPayload($student->id, 'pi_test_1', 1500);
    $signature = makeWebhookSignature($payload, 'whsec_test_secret');

    $this->postJson('/api/webhooks/stripe', [], ['Stripe-Signature' => $signature, 'CONTENT_TYPE' => 'application/json'])
        ->assertStatus(204);

    // withBody direct, postJson n'utilise pas le payload brut requis par
    // Stripe\Webhook::constructEvent() — étape suivante avec un vrai body brut.
})->skip('remplacé par le test suivant, qui envoie le payload brut correctement');

test('a valid webhook signature creates a stripe_topup transaction (raw body)', function () {
    $student = makeWebhookStudent();
    $payload = checkoutCompletedPayload($student->id, 'pi_test_2', 1500);
    $signature = makeWebhookSignature($payload, 'whsec_test_secret');

    $this->call('POST', '/api/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => $signature,
    ], $payload)->assertStatus(204);

    $transaction = CantineTransaction::where('stripe_payment_intent_id', 'pi_test_2')->first();
    expect($transaction)->not->toBeNull();
    expect($transaction->section_user_id)->toBe($student->id);
    expect($transaction->type)->toBe('stripe_topup');
    expect($transaction->amount)->toBe(15.0);
});

test('an invalid webhook signature is rejected', function () {
    $student = makeWebhookStudent();
    $payload = checkoutCompletedPayload($student->id, 'pi_test_3');

    $this->call('POST', '/api/webhooks/stripe', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_STRIPE_SIGNATURE' => 't=1234,v1=garbage',
    ], $payload)->assertStatus(400);

    expect(CantineTransaction::where('stripe_payment_intent_id', 'pi_test_3')->exists())->toBeFalse();
});

test('the same payment_intent delivered twice creates only one transaction (idempotence)', function () {
    $student = makeWebhookStudent();
    $payload = checkoutCompletedPayload($student->id, 'pi_test_4', 1000);
    $signature = makeWebhookSignature($payload, 'whsec_test_secret');

    $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $signature];

    $this->call('POST', '/api/webhooks/stripe', [], [], [], $headers, $payload)->assertStatus(204);
    $this->call('POST', '/api/webhooks/stripe', [], [], [], $headers, $payload)->assertStatus(204);

    expect(CantineTransaction::where('stripe_payment_intent_id', 'pi_test_4')->count())->toBe(1);
});

<?php

use App\Models\User;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Support\Facades\Crypt;

function makeLocaleUser(?string $locale = null): User
{
    return User::factory()->create(['locale' => $locale]);
}

test('a logged-in user posting to /locale updates their account locale, not a cookie', function () {
    $user = makeLocaleUser();

    $response = $this->actingAs($user)
        ->post('/locale', ['locale' => 'en'])
        ->assertRedirect();

    expect($user->refresh()->locale)->toBe('en');
    expect(collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'locale'))->toBeNull();
});

test('an anonymous visitor posting to /locale gets a locale cookie, no account created', function () {
    $countBefore = User::count();

    $response = $this->post('/locale', ['locale' => 'nl'])
        ->assertRedirect();

    $cookie = collect($response->headers->getCookies())->first(fn ($c) => $c->getName() === 'locale');
    expect($cookie)->not->toBeNull();

    // Le cookie sort chiffré du navigateur (EncryptCookies, comportement par
    // défaut de Laravel pour tout cookie non explicitement excepté — cf.
    // bootstrap/app.php) : on le déchiffre ici pour vérifier sa valeur réelle,
    // exactement comme le ferait EncryptCookies côté requête entrante.
    $decrypted = CookieValuePrefix::remove(Crypt::decrypt($cookie->getValue(), false));
    expect($decrypted)->toBe('nl');
    expect(User::count())->toBe($countBefore);
});

test('an invalid locale is rejected and changes nothing', function () {
    $user = makeLocaleUser('fr');

    $this->actingAs($user)
        ->post('/locale', ['locale' => 'de'])
        ->assertSessionHasErrors('locale');

    expect($user->refresh()->locale)->toBe('fr');
});

test('SetLocale syncs a cookie to the account only once, then ignores it', function () {
    $user = makeLocaleUser(); // locale encore NULL

    $this->actingAs($user)
        ->withCookie('locale', 'es')
        ->get('/dashboard');

    expect($user->refresh()->locale)->toBe('es');

    // Le compte a maintenant une locale ; un cookie différent ne doit plus
    // jamais l'écraser.
    $this->actingAs($user)
        ->withCookie('locale', 'nl')
        ->get('/dashboard');

    expect($user->refresh()->locale)->toBe('es');
});

test('showLanguagePicker is true for an anonymous visitor without a locale cookie', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('showLanguagePicker', true));
});

test('showLanguagePicker is false for an anonymous visitor with a locale cookie', function () {
    $this->withCookie('locale', 'fr')
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('showLanguagePicker', false));
});

test('showLanguagePicker is true for a logged-in user whose account has no locale yet', function () {
    $user = makeLocaleUser();

    // '/' (Welcome) plutôt que '/dashboard' : cette dernière est sous
    // school.context, qui redirige vers /school/create pour un compte sans
    // UserSchoolRole (le cas ici) avant que la page Inertia ne soit rendue.
    // showLanguagePicker est un prop partagé globalement, disponible sur
    // n'importe quelle page rendue — '/' fonctionne pour un visiteur
    // connecté comme anonyme, sans dépendre du contexte école.
    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('showLanguagePicker', true));
});

test('showLanguagePicker is false for a logged-in user whose account already has a locale', function () {
    $user = makeLocaleUser('en');

    $this->actingAs($user)
        ->get('/')
        ->assertInertia(fn ($page) => $page->where('showLanguagePicker', false));
});

test('availableLocales shared prop contains the four fixed languages', function () {
    $this->get('/')
        ->assertInertia(fn ($page) => $page->where('availableLocales', ['fr', 'en', 'nl', 'es']));
});

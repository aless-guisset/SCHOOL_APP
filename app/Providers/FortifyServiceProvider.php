<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            // Toujours vrai : /register n'est plus une feature Fortify togglable.
            'canRegister' => true,
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        // Pas de Fortify::registerView() : /register est géré par
        // SchoolOnboardingController (cf. config/fortify.php), pas par Fortify.

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$this->clientIp($request));

            return Limit::perMinute(5)->by($throttleKey);
        });

        // Parcours capables de créer un compte sans authentification préalable
        // (SchoolAccessController::joinWithCode()/joinRequest(),
        // SchoolInvitationsController::accept()) : accessibles publiquement
        // (recherche d'école par nom, ou lien d'invitation), à limiter contre
        // le spam/l'énumération de comptes.
        RateLimiter::for('account-creation', function (Request $request) {
            return Limit::perMinute(5)->by($this->clientIp($request));
        });
    }

    /**
     * IP client réelle. Railway (`trustProxies(at: '*')`) fait tourner l'IP
     * de son propre nœud d'edge entre deux requêtes d'un même visiteur — donc
     * $request->ip() (dernier maillon de X-Forwarded-For une fois tous les
     * proxies approuvés) change à chaque requête et neutralise silencieusement
     * tout rate-limiting basé dessus. X-Real-IP, que Railway pose lui-même,
     * reste stable. Découvert en vérifiant que le rate-limiting déployé
     * fonctionnait réellement : 6 tentatives successives ne déclenchaient
     * jamais de 429, y compris sur le limiter 'login' pourtant préexistant.
     */
    private function clientIp(Request $request): string
    {
        return $request->header('X-Real-IP') ?: $request->ip();
    }
}

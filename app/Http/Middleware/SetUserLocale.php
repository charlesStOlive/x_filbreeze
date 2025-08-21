<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SetUserLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Configurer la locale de l'application
            if ($user->locale) {
                // Extraire juste la langue (ex: 'fr' de 'fr_FR')
                $language = substr($user->locale, 0, 2);
                App::setLocale($language);
            }

            // Configurer le fuseau horaire pour l'utilisateur (optionnel)
            if ($user->timezone) {
                // Vous pouvez utiliser ceci dans vos vues pour afficher les dates
                // dans le bon fuseau horaire
                config(['app.user_timezone' => $user->timezone]);
            }
        }

        return $next($request);
    }
}

<?php

namespace App\Http\Middleware;

use App\Models\Contact;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs contacts out whose portal access was revoked or whose customer was archived.
 */
class EnsureContactCanUsePortal
{
    public function handle(Request $request, Closure $next): Response
    {
        $contact = Auth::guard('contact')->user();

        if ($contact instanceof Contact && ! $contact->canUsePortal()) {
            Auth::guard('contact')->logout();
            $request->session()->regenerateToken();

            return redirect()->route('portal.login')
                ->withErrors(['email' => 'Ihr Zugang zum Kundenportal ist nicht mehr aktiv. Bitte wenden Sie sich an uns.']);
        }

        return $next($request);
    }
}

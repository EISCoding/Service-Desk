<?php

namespace App\Http\Middleware;

use App\Support\Installation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Not installed yet: every page leads to the installer. Afterwards the installer is gone.
 */
class RedirectToInstaller
{
    public function handle(Request $request, Closure $next): Response
    {
        $installer = $request->is('install', 'install/*');

        if (Installation::installed()) {
            abort_if($installer, 404);

            return $next($request);
        }

        if ($installer || $request->is('livewire*', 'up', 'manifest.json')) {
            return $next($request);
        }

        return redirect('/install');
    }
}

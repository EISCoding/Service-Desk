<?php

namespace App\Http\Controllers\Intern;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UpdateSidebarController extends Controller
{
    /**
     * Remember whether the sidebar is collapsed to icons.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate(['collapsed' => ['required', 'boolean']]);

        $request->user()->update(['sidebar_collapsed' => $validated['collapsed']]);

        return response()->noContent();
    }
}

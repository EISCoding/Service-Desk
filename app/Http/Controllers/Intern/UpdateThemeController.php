<?php

namespace App\Http\Controllers\Intern;

use App\Enums\Theme;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class UpdateThemeController extends Controller
{
    /**
     * Store the appearance (System/Hell/Dunkel) chosen in the theme switcher.
     */
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'theme' => ['required', Rule::enum(Theme::class)],
        ]);

        $request->user()->update(['theme' => $validated['theme']]);

        return response()->noContent();
    }
}

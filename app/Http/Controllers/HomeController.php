<?php

namespace App\Http\Controllers;

use App\Support\HomePage;
use Illuminate\View\View;

/**
 * Public home page: ways to reach the support and the sign-ins for customers and staff.
 */
class HomeController extends Controller
{
    public function __invoke(HomePage $page): View
    {
        return view('home', [
            'page' => $page,
            'staff' => auth('web')->user(),
            'contact' => auth('contact')->user(),
        ]);
    }
}

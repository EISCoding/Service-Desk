<?php

namespace App\Http\Controllers\Intern\Admin;

use App\Http\Controllers\Controller;
use App\Support\LogReader;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Download of a Laravel log file (admin "Protokolle").
 */
class LogDownloadController extends Controller
{
    public function __invoke(string $file): BinaryFileResponse
    {
        $path = LogReader::path($file) ?? abort(404);

        return response()->download($path);
    }
}

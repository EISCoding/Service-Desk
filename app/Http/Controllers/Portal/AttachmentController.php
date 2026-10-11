<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Intern\AttachmentController as InternAttachmentController;
use App\Models\Attachment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Attachments of public messages for contacts (never of internal notes).
 */
class AttachmentController extends InternAttachmentController
{
    public function show(Request $request, Attachment $attachment): StreamedResponse
    {
        Gate::forUser(auth('contact')->user())->authorize('viewInPortal', $attachment);

        return $this->stream($request, $attachment);
    }
}

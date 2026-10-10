<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\TicketMessage;
use App\Models\User;

class AttachmentPolicy
{
    /**
     * Staff may download an attachment when they may see the ticket of its message.
     */
    public function view(User $user, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return $attachable instanceof TicketMessage && $user->can('view', $attachable->ticket);
    }
}

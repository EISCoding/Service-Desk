<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\Contact;
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

    /**
     * Contacts only get attachments of public messages of tickets they see in the portal.
     */
    public function viewInPortal(Contact $contact, Attachment $attachment): bool
    {
        $attachable = $attachment->attachable;

        return $attachable instanceof TicketMessage
            && ! $attachable->isInternal()
            && $contact->can('viewInPortal', $attachable->ticket);
    }
}

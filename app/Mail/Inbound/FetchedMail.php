<?php

namespace App\Mail\Inbound;

/**
 * A mail fetched from a mailbox that still has to be marked as processed.
 */
interface FetchedMail
{
    public function mail(): InboundMail;

    /**
     * Mark as read or move to the processed folder (per mailbox setting).
     */
    public function markProcessed(): void;
}

<?php

namespace App\Http\Responses;

use Illuminate\Support\Facades\Password;
use Laravel\Fortify\Http\Responses\FailedPasswordResetLinkRequestResponse as FortifyResponse;
use Laravel\Fortify\Http\Responses\SuccessfulPasswordResetLinkRequestResponse;

/**
 * Answers an unknown email address exactly like a successful request, so the
 * form does not reveal which addresses have an account.
 */
class FailedPasswordResetLinkRequestResponse extends FortifyResponse
{
    public function toResponse($request)
    {
        if ($this->status === Password::INVALID_USER) {
            return (new SuccessfulPasswordResetLinkRequestResponse(Password::RESET_LINK_SENT))->toResponse($request);
        }

        return parent::toResponse($request);
    }
}

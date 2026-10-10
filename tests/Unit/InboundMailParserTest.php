<?php

use App\Mail\Inbound\InboundMailParser;

it('parses sender, recipients, subject and threading headers', function () {
    $mail = (new InboundMailParser)->fromFile(base_path('tests/Fixtures/mails/new-from-contact.eml'));

    expect($mail->messageId)->toBe('CAF1234567890@mail.kunde.test')
        ->and($mail->inReplyTo)->toBeNull()
        ->and($mail->subject)->toBe('Drucker im 2. OG druckt nicht')
        ->and($mail->fromEmail)->toBe('anna.becker@kunde.test')
        ->and($mail->fromName)->toBe('Anna Bäcker')
        ->and($mail->to)->toBe([['email' => 'support@musterfirma.test', 'name' => 'Support Musterfirma']])
        ->and(array_column($mail->cc, 'email'))->toBe(['chef@kunde.test', 'support@musterfirma.test'])
        ->and($mail->date->format('Y-m-d H:i'))->toBe('2026-10-14 09:15');
});

it('extracts HTML, text and attachments with content ids', function () {
    $mail = (new InboundMailParser)->fromFile(base_path('tests/Fixtures/mails/new-from-contact.eml'));

    expect($mail->html)->toContain('<b>nicht mehr</b>')
        ->and($mail->text)->toContain('seit heute früh')
        ->and($mail->attachments)->toHaveCount(2);

    [$logo, $pdf] = $mail->attachments;
    expect($logo)->name->toBe('logo.png')->mimeType->toBe('image/png')->contentId->toBe('logo@kunde.test')->inline->toBeTrue()
        ->and($pdf)->name->toBe('fehlerbericht.pdf')->inline->toBeFalse()
        ->and(str_starts_with($pdf->content, '%PDF'))->toBeTrue();
});

it('normalizes message ids and decodes encoded words', function () {
    expect(InboundMailParser::normalizeId(' <abc@host> '))->toBe('abc@host')
        ->and(InboundMailParser::normalizeId(''))->toBeNull()
        ->and(InboundMailParser::decode('=?UTF-8?Q?Gr=C3=BC=C3=9Fe?='))->toBe('Grüße')
        ->and(InboundMailParser::decode('Plain'))->toBe('Plain');
});

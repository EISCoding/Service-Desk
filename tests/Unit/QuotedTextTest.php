<?php

use App\Support\Html;
use App\Support\QuotedText;

it('splits Gmail/Apple style quotes at the attribution line', function () {
    [$main, $quoted] = QuotedText::split('<div><p>Danke, klappt wieder!</p><div>Am Mi., 14. Okt. 2026 um 16:00 schrieb Support &lt;support@firma.test&gt;:</div><blockquote><p>Bitte neu starten.</p></blockquote></div>');

    expect($main)->toContain('Danke, klappt wieder!')->not->toContain('schrieb')->not->toContain('neu starten')
        ->and($quoted)->toContain('schrieb')->toContain('Bitte neu starten.');
});

it('splits Outlook header blocks (Von/Gesendet)', function () {
    [$main, $quoted] = QuotedText::split('<p>Anbei die Infos.</p><hr><p><b>Von:</b> Support<br><b>Gesendet:</b> Mittwoch, 14. Oktober 2026<br><b>Betreff:</b> Drucker</p><p>Alter Text</p>');

    expect($main)->toContain('Anbei die Infos.')->not->toContain('Alter Text')
        ->and($quoted)->toContain('Gesendet:')->toContain('Alter Text');
});

it('splits text mails at "Am … schrieb" and "Ursprüngliche Nachricht"', function () {
    [$main, $quoted] = QuotedText::split(Html::fromText("Neue Antwort\n\nAm 14.10.2026 um 16:00 schrieb Support:\n> Alte Nachricht"));
    expect($main)->toContain('Neue Antwort')->and($quoted)->toContain('Alte Nachricht');

    [$main, $quoted] = QuotedText::split(Html::fromText("Hallo\n\n-----Ursprüngliche Nachricht-----\nVon: x"));
    expect($main)->toContain('Hallo')->and($quoted)->toContain('Ursprüngliche Nachricht');
});

it('leaves mails without quotes alone', function () {
    expect(QuotedText::split('<p>Nur neuer Text, von: niemandem zitiert.</p>'))->toBe(['<p>Nur neuer Text, von: niemandem zitiert.</p>', null])
        ->and(QuotedText::split(''))->toBe(['', null]);
});

it('does not hide everything when the mail starts with the quote', function () {
    $html = '<blockquote><p>Nur Zitat</p></blockquote>';

    expect(QuotedText::split($html))->toBe([$html, null]);
});

it('keeps both parts valid HTML', function () {
    [$main, $quoted] = QuotedText::split('<div class="wrapper"><p>Oben</p><blockquote><p>Zitat</p></blockquote><p>Nach dem Zitat</p></div>');

    expect(substr_count($main, '<div'))->toBe(substr_count($main, '</div>'))
        ->and($quoted)->toContain('Zitat')->toContain('Nach dem Zitat');
});

it('keeps inline cid images but drops external images', function () {
    expect(Html::sanitize('<p><img src="cid:logo@kunde.test" alt="Logo"><img src="https://tracker.example/p.gif"></p>'))
        ->toBe('<p><img src="cid:logo@kunde.test" alt="Logo" /></p>');
});

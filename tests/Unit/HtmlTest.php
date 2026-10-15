<?php

use App\Support\Html;

it('keeps formatting from the editor', function () {
    $html = '<div>Hallo <strong>Welt</strong>, <em>kursiv</em></div><ul><li>eins</li></ul><blockquote>Zitat</blockquote>';

    expect(Html::sanitize($html))->toBe($html);
});

it('removes scripts, event handlers and dangerous links', function () {
    $clean = Html::sanitize('<p onclick="alert(1)">Text</p><script>alert(1)</script><a href="javascript:alert(1)">x</a><iframe src="https://evil"></iframe>');

    expect($clean)->not->toContain('script')
        ->not->toContain('onclick')
        ->not->toContain('javascript')
        ->not->toContain('iframe')
        ->toContain('<p>Text</p>');
});

it('opens links safely in a new tab', function () {
    expect(Html::sanitize('<a href="https://example.com">Link</a>'))
        ->toContain('href="https://example.com"')
        ->toContain('rel="noopener noreferrer"')
        ->toContain('target="_blank"');
});

it('drops styles, classes and images', function () {
    expect(Html::sanitize('<p style="color:red" class="x">a</p><img src="https://x/y.png">'))->toBe('<p>a</p>');
});

it('handles empty input and long mails', function () {
    expect(Html::sanitize(null))->toBe('')
        ->and(Html::sanitize('   '))->toBe('')
        ->and(strlen(Html::sanitize('<p>'.str_repeat('a', 100_000).'</p>')))->toBeGreaterThan(100_000);
});

it('converts between HTML and plain text', function () {
    expect(Html::toText('<p>Zeile 1</p><p>Zeile 2 &amp; mehr</p>'))->toBe("Zeile 1\nZeile 2 & mehr")
        ->and(Html::fromText("Hallo <b>\nWelt\n\nNeuer Absatz"))->toBe("<p>Hallo &lt;b&gt;<br>\nWelt</p><p>Neuer Absatz</p>");
});

it('keeps the editor formatting: alignment, palette colors, buttons and placeholders', function () {
    $html = '<p style="text-align: center; position: fixed">a <span style="color: rgb(209, 36, 47)">rot</span></p>'
        .'<h2 style="text-align:right;margin-top:900px">Titel</h2>'
        .'<p><a href="https://firma.de" data-type="button">Zum Termin</a> <span data-type="placeholder">{ticket.nummer}</span></p><hr>';

    expect(Html::sanitize($html))->toBe(
        '<p style="text-align: center">a <span style="color: #d1242f">rot</span></p>'
        .'<h2 style="text-align: right">Titel</h2>'
        .'<p><a href="https://firma.de" data-type="button" rel="noopener noreferrer" target="_blank">Zum Termin</a> <span data-type="placeholder">{ticket.nummer}</span></p><hr />'
    );
});

it('drops colors outside the palette and unknown data types', function () {
    // Incoming mails often force black text, which would be unreadable in dark mode.
    expect(Html::sanitize('<p style="color: #000000">a</p><span style="color: #d1242f; background: url(https://track.er/x)">b</span>'))
        ->toBe('<p>a</p><span style="color: #d1242f">b</span>')
        ->and(Html::sanitize('<a href="https://x.de" data-type="script">x</a><span data-type="button">y</span><p data-type="placeholder">z</p>'))
        ->toBe('<a href="https://x.de" rel="noopener noreferrer" target="_blank">x</a><span>y</span><p>z</p>');
});

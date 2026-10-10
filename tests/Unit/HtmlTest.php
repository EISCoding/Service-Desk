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

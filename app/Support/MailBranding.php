<?php

namespace App\Support;

use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Storage;

/**
 * Look of the HTML mail layout (Admin → Mail-Design): company name, logo,
 * colors, font, header and footer. withDraft() renders unsaved changes for the
 * live preview without storing them.
 */
class MailBranding
{
    public const DEFAULT_REPLY_HINT = 'Bitte antworten Sie direkt auf diese E-Mail, damit Ihre Nachricht dem Ticket zugeordnet wird.';

    /** key => [label, CSS font stack]; only fonts every mail client has. */
    public const FONTS = [
        'system' => ['Systemschrift (wie auf Apple- und Windows-Geräten)', "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif"],
        'helvetica' => ['Helvetica / Arial', 'Helvetica,Arial,sans-serif'],
        'verdana' => ['Verdana', 'Verdana,Geneva,sans-serif'],
        'trebuchet' => ['Trebuchet', "'Trebuchet MS',Helvetica,Arial,sans-serif"],
        'georgia' => ['Georgia (mit Serifen)', "Georgia,'Times New Roman',serif"],
    ];

    public const LOGO_SIZES = [32 => 'Klein', 40 => 'Mittel', 56 => 'Groß'];

    public const DEFAULT_DESIGN = [
        'accent' => null,          // null: the accent color from "Erscheinungsbild"
        'background' => '#f2f2f7',
        'font' => 'system',
        'logo' => 'left',          // left | center | none
        'logo_size' => 40,
        'show_name' => true,
        'header' => null,          // HTML next to (or below) the logo, e.g. a hotline
        'accent_bar' => true,
        'rounded' => true,
    ];

    /** Larger logos are linked instead of embedded, so every mail stays small. */
    private const MAX_EMBED_BYTES = 200 * 1024;

    /** @var array{design?: array<string, mixed>, footer?: ?string, reply_hint?: ?string} */
    private array $draft = [];

    public function __construct(private AppSettings $settings) {}

    /**
     * Copy with unsaved values (live preview, test mail).
     *
     * @param  array<string, mixed>  $design
     */
    public function withDraft(array $design, ?string $footer, ?string $replyHint): static
    {
        $clone = clone $this;
        $clone->draft = ['design' => $design, 'footer' => $footer, 'reply_hint' => $replyHint];

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function design(): array
    {
        $design = [...self::DEFAULT_DESIGN, ...(array) ($this->settings->get('mail_design') ?? []), ...($this->draft['design'] ?? [])];

        return array_intersect_key($design, self::DEFAULT_DESIGN);
    }

    public function name(): string
    {
        return (string) config('app.name');
    }

    /**
     * Color of buttons and the bar above the card; by default the darker variant
     * of the accent color (readable on white and with white text on buttons).
     */
    public function accent(): string
    {
        return self::color($this->design()['accent'])
            ?? AccentColor::PALETTE[$this->settings->get('accent')][1] ?? AccentColor::PALETTE['blue'][1];
    }

    public function background(): string
    {
        return self::color($this->design()['background']) ?? self::DEFAULT_DESIGN['background'];
    }

    public function font(): string
    {
        return (self::FONTS[$this->design()['font']] ?? self::FONTS['system'])[1];
    }

    /**
     * left | center | none
     */
    public function logoPlacement(): string
    {
        $placement = $this->design()['logo'];

        return in_array($placement, ['left', 'center', 'none'], true) ? $placement : 'left';
    }

    public function logoSize(): int
    {
        $size = (int) $this->design()['logo_size'];

        return array_key_exists($size, self::LOGO_SIZES) ? $size : 40;
    }

    public function showName(): bool
    {
        return (bool) $this->design()['show_name'];
    }

    public function accentBar(): bool
    {
        return (bool) $this->design()['accent_bar'];
    }

    public function radius(): int
    {
        return $this->design()['rounded'] ? 16 : 0;
    }

    /**
     * Short HTML in the header (sanitized), null when empty.
     */
    public function header(): ?string
    {
        $html = Html::sanitize($this->design()['header']);

        return Html::toText($html) === '' ? null : $html;
    }

    public function replyHint(): string
    {
        $hint = array_key_exists('reply_hint', $this->draft) ? $this->draft['reply_hint'] : $this->settings->get('mail_reply_hint');

        return trim((string) $hint) ?: self::DEFAULT_REPLY_HINT;
    }

    /**
     * Footer as HTML (sanitized), e.g. address, phone and legal details.
     * Older installations stored plain text; it is shown with line breaks.
     */
    public function footer(): ?string
    {
        if (array_key_exists('footer', $this->draft)) {
            $html = Html::sanitize($this->draft['footer']);
        } elseif ($stored = $this->settings->get('mail_footer_html')) {
            $html = Html::sanitize($stored);
        } else {
            $text = trim((string) $this->settings->get('mail_footer'));
            $html = $text === '' ? '' : preg_replace('/\R/', '', nl2br(e($text)));
        }

        return Html::toText($html) === '' ? null : $html;
    }

    /**
     * Footer for the plain text part of mails.
     */
    public function footerText(): ?string
    {
        $footer = $this->footer();

        return $footer === null ? null : Html::toText($footer);
    }

    /**
     * Image source of the logo: embedded (cid) when sending, otherwise the public URL.
     */
    public function logo(?Message $message = null): ?string
    {
        $path = $this->settings->get('logo');
        $disk = Storage::disk('local');

        if ($this->logoPlacement() === 'none' || ! $path || ! $disk->exists($path)) {
            return null;
        }

        if ($message && $disk->size($path) <= self::MAX_EMBED_BYTES) {
            return $message->embedData($disk->get($path), basename($path), $disk->mimeType($path) ?: 'image/png');
        }

        return $this->settings->logoUrl();
    }

    private static function color(mixed $value): ?string
    {
        return is_string($value) && preg_match('/^#[0-9a-f]{6}$/i', $value) ? strtolower($value) : null;
    }
}

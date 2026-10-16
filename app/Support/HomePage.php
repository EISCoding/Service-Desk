<?php

namespace App\Support;

use App\Models\Mailbox;

/**
 * Content of the public home page ("/"), maintained under Admin → Erscheinungsbild.
 * Every value has a sensible default, so the page works right after the installation.
 */
class HomePage
{
    public const DEFAULT_HEADING = 'Wie können wir helfen?';

    public function __construct(private AppSettings $settings) {}

    public function heading(): string
    {
        return trim((string) $this->settings->get('home_heading')) ?: self::DEFAULT_HEADING;
    }

    /**
     * Introduction as sanitized HTML; the default text when nothing is stored.
     */
    public function intro(): string
    {
        $html = Html::sanitize($this->settings->get('home_intro'));

        return Html::toText($html) !== '' ? $html : self::defaultIntro();
    }

    public static function defaultIntro(): string
    {
        return '<p>Hier erreichen Sie den Support von '.e(config('app.name')).'. Im Kundenportal stellen Sie Anfragen, '
            .'beantworten Rückfragen und sehen jederzeit, wie weit Ihr Anliegen ist.</p>';
    }

    /**
     * Address of the default mailbox: mails to it become tickets.
     */
    public function supportEmail(): ?string
    {
        return Mailbox::default()?->email;
    }

    public function phone(): ?string
    {
        return trim((string) $this->settings->get('home_phone')) ?: null;
    }

    /**
     * tel: link of the phone number (digits and a leading +).
     */
    public function phoneHref(): ?string
    {
        $phone = $this->phone();

        return $phone ? 'tel:'.preg_replace('/(?!^\+)[^\d]/', '', $phone) : null;
    }

    public function hours(): ?string
    {
        return trim((string) $this->settings->get('home_hours')) ?: null;
    }

    public function imprintUrl(): ?string
    {
        return $this->settings->get('home_imprint_url') ?: null;
    }

    public function privacyUrl(): ?string
    {
        return $this->settings->get('home_privacy_url') ?: null;
    }
}

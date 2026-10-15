<?php

namespace App\Support;

use GdImage;
use InvalidArgumentException;
use RuntimeException;

/**
 * Builds the app icons (home screen, manifest, push) and the favicon from one
 * uploaded image, with the GD extension. Logos with transparent areas are placed
 * on the chosen background with some padding, so the round and rounded-square
 * masks of Android and iOS do not cut them off; opaque images fill the icon.
 */
class IconGenerator
{
    /**
     * Generated files: name => [size, content scale for transparent images, maskable].
     */
    public const ICONS = [
        'icon-any-192.png' => [192, 1.0, false],
        'icon-any-512.png' => [512, 1.0, false],
        'icon-192.png' => [192, 0.62, true],
        'icon-512.png' => [512, 0.62, true],
        'apple-touch-icon.png' => [180, 0.72, true],
    ];

    public const FAVICON_SIZE = 32;

    public const BADGE_SIZE = 96;

    public static function available(): bool
    {
        return extension_loaded('gd') && function_exists('imagecreatefromstring');
    }

    /**
     * @return array<string, string> file name => PNG data (the badge only for images with transparency)
     */
    public function appIcons(string $image, string $background): array
    {
        $source = $this->load($image);
        $transparent = $this->hasTransparency($source);
        $color = $this->parseColor($background);

        $files = [];
        foreach (self::ICONS as $name => [$size, $scale, $filled]) {
            $files[$name] = $filled
                ? $this->render($source, $size, $transparent ? $scale : 1.0, $color)
                : $this->render($source, $size, 1.0, null);
        }

        if ($transparent) {
            $files['badge-96.png'] = $this->silhouette($source, self::BADGE_SIZE);
        }

        return $files;
    }

    public function favicon(string $image): string
    {
        return $this->render($this->load($image), self::FAVICON_SIZE, 1.0, null);
    }

    /**
     * Square PNG with the image fitted (contain) into $scale of the canvas.
     *
     * @param  array{int, int, int}|null  $background  null keeps the canvas transparent
     */
    private function render(GdImage $source, int $size, float $scale, ?array $background): string
    {
        $canvas = $this->canvas($size, $background);
        [$x, $y, $width, $height] = $this->fit($source, $size, $scale);

        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));

        return $this->png($canvas);
    }

    /**
     * White shape of the opaque parts, for the Android notification badge.
     */
    private function silhouette(GdImage $source, int $size): string
    {
        $scaled = $this->canvas($size, null);
        [$x, $y, $width, $height] = $this->fit($source, $size, 0.84);
        imagecopyresampled($scaled, $source, $x, $y, 0, 0, $width, $height, imagesx($source), imagesy($source));

        $badge = $this->canvas($size, null);
        for ($py = 0; $py < $size; $py++) {
            for ($px = 0; $px < $size; $px++) {
                $alpha = (imagecolorat($scaled, $px, $py) >> 24) & 0x7F;
                if ($alpha < 127) {
                    imagesetpixel($badge, $px, $py, imagecolorallocatealpha($badge, 255, 255, 255, $alpha));
                }
            }
        }

        return $this->png($badge);
    }

    private function load(string $image): GdImage
    {
        if (! self::available()) {
            throw new RuntimeException('The GD extension is required to generate icons.');
        }

        $source = @imagecreatefromstring($image);
        if (! $source instanceof GdImage) {
            throw new InvalidArgumentException('The file is not a readable image.');
        }

        imagepalettetotruecolor($source);
        imagealphablending($source, false);
        imagesavealpha($source, true);

        return $source;
    }

    /**
     * Whether a noticeable part of the image is (semi-)transparent, sampled on a grid.
     */
    private function hasTransparency(GdImage $image): bool
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $steps = 40;
        $transparent = 0;

        for ($i = 0; $i < $steps; $i++) {
            for ($j = 0; $j < $steps; $j++) {
                $x = (int) min($width - 1, round(($i + 0.5) * $width / $steps));
                $y = (int) min($height - 1, round(($j + 0.5) * $height / $steps));
                if (((imagecolorat($image, $x, $y) >> 24) & 0x7F) > 32) {
                    $transparent++;
                }
            }
        }

        return $transparent > $steps * $steps * 0.03;
    }

    /**
     * @param  array{int, int, int}|null  $background
     */
    private function canvas(int $size, ?array $background): GdImage
    {
        $canvas = imagecreatetruecolor($size, $size);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        $fill = $background
            ? imagecolorallocate($canvas, ...$background)
            : imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefilledrectangle($canvas, 0, 0, $size, $size, $fill);
        // On a background, transparent pixels must blend with it; without one they are copied as they are.
        imagealphablending($canvas, $background !== null);

        return $canvas;
    }

    /**
     * @return array{int, int, int, int} x, y, width and height of the fitted image
     */
    private function fit(GdImage $source, int $size, float $scale): array
    {
        $box = $size * $scale;
        $ratio = min($box / imagesx($source), $box / imagesy($source));
        $width = max(1, (int) round(imagesx($source) * $ratio));
        $height = max(1, (int) round(imagesy($source) * $ratio));

        return [intdiv($size - $width, 2), intdiv($size - $height, 2), $width, $height];
    }

    /**
     * @return array{int, int, int}
     */
    private function parseColor(string $hex): array
    {
        if (! preg_match('/^#([0-9a-f]{6})$/i', $hex, $match)) {
            throw new InvalidArgumentException('Expected a color like #ffffff.');
        }

        return array_map('hexdec', str_split($match[1], 2));
    }

    private function png(GdImage $image): string
    {
        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 9);

        return (string) ob_get_clean();
    }
}

<?php

namespace App\Services\Stories;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Format;
use Intervention\Image\Geometry\Factories\RectangleFactory;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;
use Intervention\Image\Typography\FontFactory;

/**
 * Draws 9:16 story cards (the daily career news), and saves them as story
 * images on the public disk.
 */
class StoryCardRenderer
{
    public const WIDTH = 1080;

    public const HEIGHT = 1920;

    protected const PADDING = 96;

    protected const TEXT_WIDTH = self::WIDTH - 2 * self::PADDING;

    // The app's dark theme.
    protected const BACKGROUND = '#141414';

    // The app's primary colour.
    protected const ACCENT = '#8ef0d4';

    protected const WHITE = '#fafafa';

    protected const MUTED = '#a3a3a3';

    protected Driver $driver;

    public function __construct()
    {
        $this->driver = new Driver;
    }

    /**
     * A headline: what the story is, the headline, and a line under it.
     */
    public function headline(string $kicker, string $title, ?string $meta = null): string
    {
        $image = $this->canvas();

        $y = $this->kicker($image, $kicker, 520);
        $y = $this->write($image, $title, $y, 84, self::WHITE, 1.15);

        if ($meta) {
            $this->write($image, $meta, $y + 24, 40, self::MUTED);
        }

        return $this->save($image);
    }

    /**
     * A song or video: its cover art (when it's a local file), name and
     * artist.
     */
    public function song(string $kicker, string $title, string $artist, ?string $thumbnail, string $callToAction): string
    {
        $image = $this->canvas();

        $y = $this->kicker($image, $kicker, 300);

        $image->insert($this->cover($thumbnail), self::PADDING, $y);
        $y += self::TEXT_WIDTH + 64;

        $y = $this->write($image, $title, $y, 72, self::WHITE, 1.15);
        $y = $this->write($image, $artist, $y + 16, 48, self::MUTED);
        $this->write($image, $callToAction, $y + 40, 40, self::ACCENT);

        return $this->save($image);
    }

    /**
     * A titled list, e.g. gigs or music history: each item is a gold label
     * (a date or year) over a line of text. Items that don't fit are left
     * out.
     *
     * @param  iterable<int, array{label: string, text: string}>  $items
     */
    public function list(string $kicker, string $title, iterable $items): string
    {
        $image = $this->canvas();

        $y = $this->kicker($image, $kicker, 300);
        $y = $this->write($image, $title, $y, 72, self::WHITE, 1.15) + 40;

        collect($items)
            ->each(function (array $item) use ($image, &$y) {
                $height = $this->measure($item['label'], 40) + 12 + $this->measure($item['text'], 46, 1.25);

                if ($y + $height > self::HEIGHT - 220) {
                    return false;
                }

                $y = $this->write($image, $item['label'], $y, 40, self::ACCENT);
                $y = $this->write($image, $item['text'], $y + 12, 46, self::WHITE, 1.25) + 48;
            });

        return $this->save($image);
    }

    /**
     * The square cover art, or the logo on a dark square when the song has
     * no local thumbnail.
     */
    protected function cover(?string $thumbnail): ImageInterface
    {
        if ($thumbnail && Storage::disk('public')->exists($thumbnail)) {
            return $this->manager()
                ->decodePath(Storage::disk('public')->path($thumbnail))
                ->cover(self::TEXT_WIDTH, self::TEXT_WIDTH);
        }

        $logo = $this->manager()
            ->decodePath(public_path('android-chrome-512x512.png'))
            ->scale(height: 320);

        return $this->manager()
            ->createImage(self::TEXT_WIDTH, self::TEXT_WIDTH)
            ->fill('#1f1f1f')
            ->insert($logo, 0, 0, 'center');
    }

    /**
     * A blank card with the logo at the top and the web address at the
     * bottom.
     */
    protected function canvas(): ImageInterface
    {
        $image = $this->manager()
            ->createImage(self::WIDTH, self::HEIGHT)
            ->fill(self::BACKGROUND);

        $logo = $this->manager()
            ->decodePath(public_path('android-chrome-512x512.png'))
            ->scale(height: 88);

        $image->insert($logo, self::PADDING, 120);

        $this->write($image, 'Black Mail', 136, 48, self::WHITE, x: self::PADDING + $logo->width() + 28);
        $this->write($image, 'mail.black.co.ke', self::HEIGHT - 150, 36, self::MUTED);

        return $image;
    }

    /**
     * The small gold heading with a bar under it. Returns where the next
     * block starts.
     */
    protected function kicker(ImageInterface $image, string $text, int $y): int
    {
        $y = $this->write($image, Str::upper($text), $y, 40, self::ACCENT);

        $image->drawRectangle(fn (RectangleFactory $rectangle) => $rectangle
            ->at(self::PADDING, $y + 20)
            ->size(120, 8)
            ->background(self::ACCENT));

        return $y + 84;
    }

    /**
     * Draw text wrapped to the card's width, top at $y, one line at a time.
     * Returns the y just below it.
     */
    protected function write(ImageInterface $image, string $text, int $y, int $size, string $color, float $lineHeight = 1.2, int $x = self::PADDING): int
    {
        return $this->lines($text, $size, self::WIDTH - self::PADDING - $x)
            ->reduce(function (int $y, string $line) use ($image, $x, $size, $color, $lineHeight) {
                $image->text($line, $x, $y, $this->font($size, $color));

                return $y + $this->lineAdvance($size, $lineHeight);
            }, $y);
    }

    /**
     * How tall write() draws this text.
     */
    protected function measure(string $text, int $size, float $lineHeight = 1.2): int
    {
        return $this->lines($text, $size, self::TEXT_WIDTH)->count() * $this->lineAdvance($size, $lineHeight);
    }

    protected function lineAdvance(int $size, float $lineHeight): int
    {
        return (int) round($size * $lineHeight);
    }

    /**
     * Word wrap by measured width (the driver's own wrapping can't be
     * measured, so cards would overlap).
     *
     * @return Collection<int, string>
     */
    protected function lines(string $text, int $size, int $width): Collection
    {
        return collect(preg_split('/\s+/u', trim($text)) ?: [])
            ->reduce(function (Collection $lines, string $word) use ($size, $width) {
                $candidate = trim(($lines->last() ?? '').' '.$word);

                return $lines->isNotEmpty() && $this->width($candidate, $size) <= $width
                    ? $lines->put($lines->keys()->last(), $candidate)
                    : $lines->push($word);
            }, collect());
    }

    protected function width(string $text, int $size): int
    {
        return $this->driver
            ->fontProcessor()
            ->boxSize($text, FontFactory::build($this->font($size, self::WHITE)))
            ->width();
    }

    protected function font(int $size, string $color): callable
    {
        return fn (FontFactory $font) => $font
            ->filename(resource_path('fonts/InstrumentSans.ttf'))
            ->size($size)
            ->color($color)
            ->align('left', 'top');
    }

    protected function save(ImageInterface $image): string
    {
        $path = 'stories/'.Str::uuid7().'.jpg';

        Storage::disk('public')->put($path, (string) $image->encodeUsingFormat(Format::JPEG, quality: 85));

        return $path;
    }

    protected function manager(): ImageManager
    {
        return ImageManager::usingDriver($this->driver);
    }
}

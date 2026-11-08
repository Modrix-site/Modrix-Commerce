<?php

namespace EcoModular\ImageStudio\Removers;

use EcoModular\ImageStudio\Contracts\BackgroundRemover;
use EcoModular\ImageStudio\Exceptions\SkipImage;
use GdImage;

/**
 * Removes a flat studio background by flood-filling from the image border.
 * Only background connected to the edge becomes transparent.
 */
class GdEdgeRemover implements BackgroundRemover
{
    public const KEY = 'gd-edge';

    private const UNSEEN = "\0";

    private const BACKGROUND = "\1";

    private const FOREGROUND = "\2";

    private int $tolerance;

    private int $featherPx;

    public function __construct(int $tolerance = 18, int $featherPx = 1)
    {
        $this->tolerance = max(1, $tolerance);
        $this->featherPx = max(0, $featherPx);
    }

    public function remove(GdImage $image, string $originalBytes): GdImage
    {
        $w = imagesx($image);
        $h = imagesy($image);

        // Work on a true-colour copy so the input is never mutated.
        $out = imagecreatetruecolor($w, $h);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, (int) imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopy($out, $image, 0, 0, 0, 0, $w, $h);

        $threshold = $this->tolerance * 1.1;
        $limit = $threshold * $threshold;

        [$bgR, $bgG, $bgB] = $this->borderMedian($out, $w, $h, $limit);

        // Scanline flood fill (4-connected) from every matching border pixel. The stack
        // holds horizontal spans packed as (start index * width + length). Tight loops use
        // imagecolorat and a byte-per-pixel state string; nothing nested is allocated per pixel.
        $visited = str_repeat(self::UNSEEN, $w * $h);
        $stack = [];
        $removed = 0;
        $boundary = []; // background pixels 4-adjacent to foreground, keyed by index

        // Marks the pixel (x, y) (already tested as background, colour $c) and the maximal
        // matching run around it, then queues the run. Returns the run's last x.
        $grow = function (int $x, int $y, int $c) use (&$visited, &$stack, &$removed, &$boundary, $out, $w, $bgR, $bgG, $bgB, $limit): int {
            $row = $y * $w;
            $visited[$row + $x] = self::BACKGROUND;
            imagesetpixel($out, $x, $y, ($c & 0xFFFFFF) | 0x7F000000);
            $count = 1;

            $a = $x;
            while ($a > 0) {
                $state = $visited[$row + $a - 1];
                if ($state === self::UNSEEN) {
                    $p = imagecolorat($out, $a - 1, $y);
                    $dr = (($p >> 16) & 0xFF) - $bgR;
                    $dg = (($p >> 8) & 0xFF) - $bgG;
                    $db = ($p & 0xFF) - $bgB;
                    if ($dr * $dr + $dg * $dg + $db * $db <= $limit) {
                        $a--;
                        $visited[$row + $a] = self::BACKGROUND;
                        imagesetpixel($out, $a, $y, ($p & 0xFFFFFF) | 0x7F000000);
                        $count++;

                        continue;
                    }
                    $visited[$row + $a - 1] = self::FOREGROUND;
                    $boundary[$row + $a] = true;
                } elseif ($state === self::FOREGROUND) {
                    $boundary[$row + $a] = true;
                }
                break;
            }

            $b = $x;
            $last = $w - 1;
            while ($b < $last) {
                $state = $visited[$row + $b + 1];
                if ($state === self::UNSEEN) {
                    $p = imagecolorat($out, $b + 1, $y);
                    $dr = (($p >> 16) & 0xFF) - $bgR;
                    $dg = (($p >> 8) & 0xFF) - $bgG;
                    $db = ($p & 0xFF) - $bgB;
                    if ($dr * $dr + $dg * $dg + $db * $db <= $limit) {
                        $b++;
                        $visited[$row + $b] = self::BACKGROUND;
                        imagesetpixel($out, $b, $y, ($p & 0xFFFFFF) | 0x7F000000);
                        $count++;

                        continue;
                    }
                    $visited[$row + $b + 1] = self::FOREGROUND;
                    $boundary[$row + $b] = true;
                } elseif ($state === self::FOREGROUND) {
                    $boundary[$row + $b] = true;
                }
                break;
            }

            $removed += $count;
            $stack[] = ($row + $a) * $w + ($b - $a);

            return $b;
        };

        // Seeds: every border pixel within the threshold.
        $seed = function (int $x, int $y) use (&$visited, $grow, $out, $w, $bgR, $bgG, $bgB, $limit): void {
            if ($visited[$y * $w + $x] !== self::UNSEEN) {
                return;
            }
            $c = imagecolorat($out, $x, $y);
            $dr = (($c >> 16) & 0xFF) - $bgR;
            $dg = (($c >> 8) & 0xFF) - $bgG;
            $db = ($c & 0xFF) - $bgB;
            if ($dr * $dr + $dg * $dg + $db * $db <= $limit) {
                $grow($x, $y, $c);
            } else {
                $visited[$y * $w + $x] = self::FOREGROUND;
            }
        };
        for ($x = 0; $x < $w; $x++) {
            $seed($x, 0);
            $seed($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y++) {
            $seed(0, $y);
            $seed($w - 1, $y);
        }

        while ($stack !== []) {
            $code = array_pop($stack);
            $len = $code % $w;
            $start = intdiv($code, $w);
            $y = intdiv($start, $w);
            $a = $start - $y * $w;
            $b = $a + $len;
            $row = $y * $w;

            foreach ([$y - 1, $y + 1] as $ny) {
                if ($ny < 0 || $ny >= $h) {
                    continue;
                }
                $nrow = $ny * $w;
                $x = $a;
                while ($x <= $b) {
                    $state = $visited[$nrow + $x];
                    if ($state === self::UNSEEN) {
                        $c = imagecolorat($out, $x, $ny);
                        $dr = (($c >> 16) & 0xFF) - $bgR;
                        $dg = (($c >> 8) & 0xFF) - $bgG;
                        $db = ($c & 0xFF) - $bgB;
                        if ($dr * $dr + $dg * $dg + $db * $db <= $limit) {
                            $x = $grow($x, $ny, $c) + 1;

                            continue;
                        }
                        $visited[$nrow + $x] = self::FOREGROUND;
                        $boundary[$row + $x] = true;
                    } elseif ($state === self::FOREGROUND) {
                        $boundary[$row + $x] = true;
                    }
                    $x++;
                }
            }
        }

        $share = $removed / ($w * $h);
        if ($share < 0.03 || $share > 0.97) {
            throw new SkipImage('Could not separate the product from the background');
        }

        if ($this->featherPx > 0) {
            $this->feather($out, $visited, $boundary, $w, $h);
        }

        return $out;
    }

    /**
     * @return array{int, int, int} median border colour
     *
     * @throws SkipImage when the border is not uniform
     */
    private function borderMedian(GdImage $img, int $w, int $h, float $limit): array
    {
        $stride = max(1, (int) ceil(max($w, $h) / 1000));
        $r = $g = $b = [];
        $add = function (int $x, int $y) use ($img, &$r, &$g, &$b): void {
            $c = imagecolorat($img, $x, $y);
            $r[] = ($c >> 16) & 0xFF;
            $g[] = ($c >> 8) & 0xFF;
            $b[] = $c & 0xFF;
        };
        for ($x = 0; $x < $w; $x += $stride) {
            $add($x, 0);
            $add($x, $h - 1);
        }
        for ($y = 0; $y < $h; $y += $stride) {
            $add(0, $y);
            $add($w - 1, $y);
        }

        $median = static function (array $v): int {
            sort($v);

            return $v[intdiv(count($v), 2)];
        };
        $mr = $median($r);
        $mg = $median($g);
        $mb = $median($b);

        $close = 0;
        $total = count($r);
        for ($i = 0; $i < $total; $i++) {
            $dr = $r[$i] - $mr;
            $dg = $g[$i] - $mg;
            $db = $b[$i] - $mb;
            if ($dr * $dr + $dg * $dg + $db * $db <= $limit) {
                $close++;
            }
        }
        if ($close / $total < 0.85) {
            throw new SkipImage('Background is not uniform');
        }

        return [$mr, $mg, $mb];
    }

    /**
     * Foreground pixels within featherPx (Chebyshev) of the background get a
     * partial alpha. The nearest background pixel of any foreground pixel is
     * always a boundary pixel, so scanning around boundary pixels is enough.
     *
     * @param  array<int, true>  $boundary  background pixel indexes touching foreground
     */
    private function feather(GdImage $out, string $visited, array $boundary, int $w, int $h): void
    {
        $f = $this->featherPx;
        $nearest = [];

        foreach (array_keys($boundary) as $idx) {
            $y = intdiv($idx, $w);
            $x = $idx - $y * $w;
            $y0 = max(0, $y - $f);
            $y1 = min($h - 1, $y + $f);
            $x0 = max(0, $x - $f);
            $x1 = min($w - 1, $x + $f);
            for ($ny = $y0; $ny <= $y1; $ny++) {
                $dy = abs($ny - $y);
                $row = $ny * $w;
                for ($nx = $x0; $nx <= $x1; $nx++) {
                    $n = $row + $nx;
                    if ($visited[$n] === self::BACKGROUND) {
                        continue;
                    }
                    $d = max($dy, abs($nx - $x));
                    if (! isset($nearest[$n]) || $d < $nearest[$n]) {
                        $nearest[$n] = $d;
                    }
                }
            }
        }

        foreach ($nearest as $n => $d) {
            $y = intdiv($n, $w);
            $x = $n - $y * $w;
            $c = imagecolorat($out, $x, $y);
            $alpha = max(($c >> 24) & 0x7F, (int) round(127 * ($f + 1 - $d) / ($f + 2)));
            imagesetpixel($out, $x, $y, ($c & 0xFFFFFF) | ($alpha << 24));
        }
    }
}

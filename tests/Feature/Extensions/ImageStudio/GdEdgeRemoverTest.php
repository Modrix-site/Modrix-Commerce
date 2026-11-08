<?php

namespace Tests\Feature\Extensions\ImageStudio;

use EcoModular\ImageStudio\Contracts\BackgroundRemover;
use EcoModular\ImageStudio\Exceptions\SkipImage;
use EcoModular\ImageStudio\Removers\GdEdgeRemover;
use GdImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;
use Tests\Support\ImageStudioFixtures;
use Tests\Support\ModuleFixtures;
use Tests\TestCase;

class GdEdgeRemoverTest extends TestCase
{
    use CommerceFixtures, ImageStudioFixtures, ModuleFixtures, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootImageStudio();
    }

    private function canvas(int $w = 200, int $h = 200): GdImage
    {
        $img = imagecreatetruecolor($w, $h);
        imagefill($img, 0, 0, imagecolorallocate($img, 0xF4, 0xF2, 0xEF));

        return $img;
    }

    private function dark(GdImage $img): int
    {
        return (int) imagecolorallocate($img, 0x2B, 0x4C, 0x7E);
    }

    private function alphaAt(GdImage $img, int $x, int $y): int
    {
        return (imagecolorat($img, $x, $y) >> 24) & 0x7F;
    }

    private function squareImage(): GdImage
    {
        $img = $this->canvas();
        imagefilledrectangle($img, 50, 50, 149, 149, $this->dark($img));

        return $img;
    }

    public function test_implements_contract_with_key(): void
    {
        $this->assertInstanceOf(BackgroundRemover::class, new GdEdgeRemover);
        $this->assertSame('gd-edge', GdEdgeRemover::KEY);
    }

    public function test_corners_become_transparent_and_centre_stays_opaque(): void
    {
        $src = $this->squareImage();
        $out = (new GdEdgeRemover)->remove($src, '');

        $this->assertSame(127, $this->alphaAt($out, 2, 2));
        $this->assertSame(127, $this->alphaAt($out, 197, 197));
        $this->assertSame(0, $this->alphaAt($out, 100, 100));
        // pixel colour of foreground is preserved
        $this->assertSame(0x2B4C7E, imagecolorat($out, 100, 100) & 0xFFFFFF);
    }

    public function test_default_feather_softens_outer_ring(): void
    {
        $out = (new GdEdgeRemover)->remove($this->squareImage(), '');

        $this->assertSame(42, $this->alphaAt($out, 50, 100)); // round(127*1/3)
        $this->assertSame(0, $this->alphaAt($out, 51, 100));
    }

    public function test_input_image_is_not_mutated(): void
    {
        $src = $this->squareImage();
        $before = imagecolorat($src, 2, 2);
        (new GdEdgeRemover)->remove($src, '');

        $this->assertSame($before, imagecolorat($src, 2, 2));
    }

    public function test_light_interior_not_connected_to_edge_is_kept(): void
    {
        $img = $this->canvas();
        imagefilledrectangle($img, 40, 40, 159, 159, $this->dark($img));
        imagefilledrectangle($img, 50, 50, 149, 149, imagecolorallocate($img, 0xF4, 0xF2, 0xEF));

        $out = (new GdEdgeRemover)->remove($img, '');

        $this->assertSame(0, $this->alphaAt($out, 100, 100));
        $this->assertSame(127, $this->alphaAt($out, 5, 5));
    }

    public function test_product_touching_edge_keeps_its_pixels(): void
    {
        $img = $this->canvas();
        imagefilledrectangle($img, 80, 0, 120, 199, $this->dark($img));

        $out = (new GdEdgeRemover)->remove($img, '');

        $this->assertSame(0, $this->alphaAt($out, 100, 0));
        $this->assertSame(0, $this->alphaAt($out, 100, 199));
        $this->assertSame(127, $this->alphaAt($out, 5, 5));
    }

    public function test_noisy_background_is_skipped(): void
    {
        mt_srand(7);
        $img = imagecreatetruecolor(100, 100);
        for ($y = 0; $y < 100; $y++) {
            for ($x = 0; $x < 100; $x++) {
                imagesetpixel($img, $x, $y, imagecolorallocate($img, mt_rand(0, 255), mt_rand(0, 255), mt_rand(0, 255)));
            }
        }

        $this->expectException(SkipImage::class);
        (new GdEdgeRemover)->remove($img, '');
    }

    public function test_blank_image_is_skipped(): void
    {
        $this->expectException(SkipImage::class);
        (new GdEdgeRemover)->remove($this->canvas(), '');
    }

    public function test_feather_zero_gives_hard_edge(): void
    {
        $out = (new GdEdgeRemover(18, 0))->remove($this->squareImage(), '');

        $this->assertSame(0, $this->alphaAt($out, 50, 100));
        $this->assertSame(127, $this->alphaAt($out, 49, 100));
    }

    public function test_source_alpha_is_preserved(): void
    {
        $img = $this->squareImage();
        imagealphablending($img, false);
        imagesavealpha($img, true);
        imagesetpixel($img, 100, 100, imagecolorallocatealpha($img, 0x2B, 0x4C, 0x7E, 60));

        $out = (new GdEdgeRemover)->remove($img, '');

        $this->assertSame(60, $this->alphaAt($out, 100, 100));
    }
}

<?php

namespace EcoModular\ImageStudio\Contracts;

use EcoModular\ImageStudio\Exceptions\SkipImage;
use GdImage;

interface BackgroundRemover
{
    /**
     * Returns a true-colour GdImage with alpha (imagesavealpha on) and leaves
     * the input untouched.
     *
     * @param  string  $originalBytes  the encoded original, for drivers that upload it
     *
     * @throws SkipImage when the image is not suitable; the message is merchant-readable
     */
    public function remove(GdImage $image, string $originalBytes): GdImage;
}

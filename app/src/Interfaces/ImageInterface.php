<?php
namespace TriTan\Interfaces;

interface ImageInterface
{
    /**
    * Resize image function.
    *
    * @since 1.0.0
    * @param int $width Width of the image.
    * @param int $height Height of the image.
    * @param string $target Size of image.
    */
    public function resize($width, $height, $target);
}

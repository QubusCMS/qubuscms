<?php
namespace TriTan\Interfaces;

interface HtmlPurifierInterface
{
    /**
     * Escaping for rich text.
     *
     * @since 1.0.0
     * @param string $string
     * @return string Escaped rich text.
     */
    public function purify($string, $is_image = false);
}

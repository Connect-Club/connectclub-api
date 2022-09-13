<?php

namespace App\Util;

use App\Entity\Photo\AbstractPhoto;

class ConnectClub
{
    public static function getResizerUrl(?AbstractPhoto $photo, $width = ':WIDTH', $height = ':HEIGHT'): string
    {
        if (!$photo) {
            return '';
        }

        return sprintf('%s/'.$width.'x'.$height.'/%s', $_ENV['IMAGE_RESIZER_BASE_URL'], $photo->processedName);
    }

    public static function getResizerUrlWithName(string $name, $width = ':WIDTH', $height = ':HEIGHT'): string
    {
        return sprintf('%s/'.$width.'x'.$height.'/%s', $_ENV['IMAGE_RESIZER_BASE_URL'], $name);
    }

    public static function getResizerCropUrl(?AbstractPhoto $photo, $width = ':WIDTH', $height = ':HEIGHT'): string
    {
        if (!$photo) {
            return '';
        }

        return sprintf('%s/crop/'.$width.'x'.$height.'/%s', $_ENV['IMAGE_RESIZER_BASE_URL'], $photo->processedName);
    }
}

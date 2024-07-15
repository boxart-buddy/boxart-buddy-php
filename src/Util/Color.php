<?php

namespace App\Util;

class Color
{
    public static function inverse(int $r, int $g, int $b): array
    {
        $maxContrast = 192;

        $minContrast = 128;
        $y = round(0.299 * $r + 0.587 * $g + 0.114 * $b); // luma
        $oy = (255 - $y); // opposite
        $dy = $oy - $y; // delta
        if (abs($dy) > $maxContrast) {
            $dy = ($dy <=> 0) * $maxContrast;
            $oy = $y + $dy;
        } elseif (abs($dy) < $minContrast) {
            $dy = ($dy <=> 0) * $minContrast;
            $oy = $y + $dy;
        }

        return [
            'r' => $oy,
            'g' => $oy,
            'b' => $oy,
        ];
    }
}

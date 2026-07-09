<?php

namespace App\Domain\Publishing\Support;

/**
 * Numeric bounds for the canvas editor's freeform geometry, shared between the
 * publish renderer (BuildPageService) and the magazine→canvas converter so the
 * design-width clamp and per-element layout clamps cannot drift apart. Mirrors
 * the front-end constants in resources/admin/src/types/canvas.ts.
 */
final class CanvasBounds
{
    // Design (desktop) canvas width
    public const CANVAS_W_MIN = 320;
    public const CANVAS_W_MAX = 3000;
    public const CANVAS_W_DEFAULT = 1200;

    // Phone canvas width (publish-side mobile-override container)
    public const MOBILE_W_MIN = 240;
    public const MOBILE_W_MAX = 767;
    public const MOBILE_W_DEFAULT = 390;

    // Per-element layout box clamps (px / deg / z-index)
    public const X_MIN = -5000;
    public const X_MAX = 20000;
    public const Y_MIN = -5000;
    public const Y_MAX = 50000;
    public const W_MIN = 1;
    public const W_MAX = 6000;
    public const H_MIN = 1;
    public const H_MAX = 20000;
    public const ROT_MIN = -360.0;
    public const ROT_MAX = 360.0;
    public const Z_MIN = 0;
    public const Z_MAX = 9999;

    /** A design width, replaced by the default when it falls outside [MIN,MAX]. */
    public static function canvasWidth(int $w): int
    {
        return ($w < self::CANVAS_W_MIN || $w > self::CANVAS_W_MAX) ? self::CANVAS_W_DEFAULT : $w;
    }

    /** A phone canvas width, replaced by the default when outside [MIN,MAX]. */
    public static function mobileWidth(int $w): int
    {
        return ($w < self::MOBILE_W_MIN || $w > self::MOBILE_W_MAX) ? self::MOBILE_W_DEFAULT : $w;
    }

    /**
     * Clamp already-numeric geometry into the layout box bounds. Shared by
     * childLayout and childMobile so the two sanitizers can't diverge.
     *
     * @return array{x:int,y:int,w:int,h:int,rotation:float,zIndex:int}
     */
    public static function clampBox(int $x, int $y, int $w, int $h, float $rot, int $z): array
    {
        return [
            'x' => max(self::X_MIN, min(self::X_MAX, $x)),
            'y' => max(self::Y_MIN, min(self::Y_MAX, $y)),
            'w' => max(self::W_MIN, min(self::W_MAX, $w)),
            'h' => max(self::H_MIN, min(self::H_MAX, $h)),
            'rotation' => max(self::ROT_MIN, min(self::ROT_MAX, $rot)),
            'zIndex' => max(self::Z_MIN, min(self::Z_MAX, $z)),
        ];
    }
}

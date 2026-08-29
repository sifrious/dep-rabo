<?php

declare(strict_types=1);

namespace Sifrious\Rabo\Motion;

use InvalidArgumentException;

/**
 * Evaluates a CSS timing function at a point in time.
 *
 * Needed because a frame-sampled render and a CSS-animated render must agree: if the encoder
 * sampled a linear ramp while the browser drew the brand's eased one, the MP4 and the SVG
 * would be two different pieces of motion wearing the same name.
 *
 * Newton-Raphson with a bisection fallback, which is what browsers do.
 */
final readonly class CubicBezier
{
    public function __construct(
        private float $x1,
        private float $y1,
        private float $x2,
        private float $y2,
    ) {
        if ($x1 < 0.0 || $x1 > 1.0 || $x2 < 0.0 || $x2 > 1.0) {
            throw new InvalidArgumentException('Cubic bezier control points must have x in [0, 1].');
        }
    }

    /** Parses `linear` or `cubic-bezier(a, b, c, d)`. Anything else is a brand error. */
    public static function parse(string $curve): self
    {
        if (trim($curve) === 'linear') {
            return new self(0.0, 0.0, 1.0, 1.0);
        }
        if (preg_match('/^cubic-bezier\(\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*,\s*(-?[\d.]+)\s*\)$/', trim($curve), $m) !== 1) {
            throw new InvalidArgumentException("Cannot evaluate timing function '{$curve}'.");
        }

        return new self((float) $m[1], (float) $m[2], (float) $m[3], (float) $m[4]);
    }

    /** Progress at fraction $t of the duration, both in [0, 1]. */
    public function at(float $t): float
    {
        if ($t <= 0.0) {
            return 0.0;
        }
        if ($t >= 1.0) {
            return 1.0;
        }

        return $this->sampleY($this->solveX($t));
    }

    private function solveX(float $x): float
    {
        $guess = $x;
        for ($i = 0; $i < 8; $i++) {
            $error = $this->sampleX($guess) - $x;
            if (abs($error) < 1e-7) {
                return $guess;
            }
            $slope = $this->slopeX($guess);
            if (abs($slope) < 1e-7) {
                break;
            }
            $guess -= $error / $slope;
        }

        $low = 0.0;
        $high = 1.0;
        $guess = $x;
        for ($i = 0; $i < 32; $i++) {
            $sampled = $this->sampleX($guess);
            if (abs($sampled - $x) < 1e-7) {
                return $guess;
            }
            if ($sampled > $x) {
                $high = $guess;
            } else {
                $low = $guess;
            }
            $guess = ($low + $high) / 2;
        }

        return $guess;
    }

    private function sampleX(float $t): float
    {
        return $this->bezier($t, $this->x1, $this->x2);
    }

    private function sampleY(float $t): float
    {
        return $this->bezier($t, $this->y1, $this->y2);
    }

    private function bezier(float $t, float $p1, float $p2): float
    {
        $inverse = 1 - $t;

        return 3 * $inverse * $inverse * $t * $p1 + 3 * $inverse * $t * $t * $p2 + $t * $t * $t;
    }

    private function slopeX(float $t): float
    {
        $inverse = 1 - $t;

        return 3 * $inverse * $inverse * $this->x1
            + 6 * $inverse * $t * ($this->x2 - $this->x1)
            + 3 * $t * $t * (1 - $this->x2);
    }
}

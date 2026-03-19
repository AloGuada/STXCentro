<?php

namespace App\Helpers;

use setasign\Fpdi\Fpdi;

class PdfWithShapes extends Fpdi
{
    /**
     * Dibujar una elipse (círculo si rx == ry)
     */
    public function Ellipse($x, $y, $rx, $ry, $style = 'D')
    {
        if ($style == 'F') {
            $op = 'f';
        } elseif ($style == 'FD' || $style == 'DF') {
            $op = 'B';
        } else {
            $op = 'S';
        }

        $lx = 4 / 3 * (M_SQRT2 - 1) * $rx;
        $ly = 4 / 3 * (M_SQRT2 - 1) * $ry;

        $this->_out(sprintf(
            '%.2F %.2F m %.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x + $rx) * $this->k,
            ($this->h - $y) * $this->k,
            ($x + $rx) * $this->k,
            ($this->h - ($y - $ly)) * $this->k,
            ($x + $lx) * $this->k,
            ($this->h - ($y - $ry)) * $this->k,
            $x * $this->k,
            ($this->h - ($y - $ry)) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $lx) * $this->k,
            ($this->h - ($y - $ry)) * $this->k,
            ($x - $rx) * $this->k,
            ($this->h - ($y - $ly)) * $this->k,
            ($x - $rx) * $this->k,
            ($this->h - $y) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c',
            ($x - $rx) * $this->k,
            ($this->h - ($y + $ly)) * $this->k,
            ($x - $lx) * $this->k,
            ($this->h - ($y + $ry)) * $this->k,
            $x * $this->k,
            ($this->h - ($y + $ry)) * $this->k
        ));
        $this->_out(sprintf(
            '%.2F %.2F %.2F %.2F %.2F %.2F c %s',
            ($x + $lx) * $this->k,
            ($this->h - ($y + $ry)) * $this->k,
            ($x + $rx) * $this->k,
            ($this->h - ($y + $ly)) * $this->k,
            ($x + $rx) * $this->k,
            ($this->h - $y) * $this->k,
            $op
        ));
    }

    /**
     * Dibujar un círculo (alias de Ellipse con radio igual)
     */
    public function Circle($x, $y, $r, $style = 'D')
    {
        $this->Ellipse($x, $y, $r, $r, $style);
    }

    protected $angle = 0;

    public function Rotate($angle, $x = -1, $y = -1)
    {
        if ($x == -1) {
            $x = $this->x;
        }
        if ($y == -1) {
            $y = $this->y;
        }

        if ($this->angle != 0) {
            $this->_out('Q');
        }

        $this->angle = $angle;

        if ($angle != 0) {
            $angle *= M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    public function _endpage()
    {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }
}

<?php
/*
 * PHP QR Code encoder
 *
 * This file contains MERGED version of PHP QR Code library.
 * It was auto-generated from full version for your convenience.
 *
 * This merged version was configured to not requre any external files,
 * with disabled cache, error loging and weker but faster mask matching.
 * If you need tune it up please use non-merged version.
 *
 * For full version, documentation, examples of use please visit:
 *
 *    http://phpqrcode.sourceforge.net/
 *    https://sourceforge.net/projects/phpqrcode/
 *
 * Based on libqrencode C library distributed under LGPL 2.1
 * Copyright (C) 2006, 2007, 2008, 2009 Kentaro Fukuchi <fukuchi@megaui.net>
 *
 * PHP QR Code is distributed under LGPL 3
 * Copyright (C) 2010 Dominik Dzienia <deltalab at poczta dot fm>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA
 *
 * Version: 1.1.4
 * Build: 2010100721
 */



namespace vertwo\plite\phpqrcode;



// Encoding modes

define('QR_MODE_NUL', -1);
define('QR_MODE_NUM', 0);
define('QR_MODE_AN', 1);
define('QR_MODE_8', 2);
define('QR_MODE_KANJI', 3);
define('QR_MODE_STRUCTURE', 4);

// Levels of error correction.

define('QR_ECLEVEL_L', 0);
define('QR_ECLEVEL_M', 1);
define('QR_ECLEVEL_Q', 2);
define('QR_ECLEVEL_H', 3);

// Supported output formats

define('QR_FORMAT_TEXT', 0);
define('QR_FORMAT_PNG', 1);



class FrameFiller
{

    public $width;
    public $frame;
    public $x;
    public $y;
    public $dir;
    public $bit;

    //----------------------------------------------------------------------
    public function __construct ( $width, &$frame )
    {
        $this->width = $width;
        $this->frame = $frame;
        $this->x     = $width - 1;
        $this->y     = $width - 1;
        $this->dir   = -1;
        $this->bit   = -1;
    }

    //----------------------------------------------------------------------
    public function setFrameAt ( $at, $val )
    {
        $this->frame[$at['y']][$at['x']] = chr($val);
    }

    //----------------------------------------------------------------------
    public function getFrameAt ( $at )
    {
        return ord($this->frame[$at['y']][$at['x']]);
    }

    //----------------------------------------------------------------------
    public function next ()
    {
        do
        {

            if ( $this->bit == -1 )
            {
                $this->bit = 0;
                return [ 'x' => $this->x, 'y' => $this->y ];
            }

            $x = $this->x;
            $y = $this->y;
            $w = $this->width;

            if ( $this->bit == 0 )
            {
                $x--;
                $this->bit++;
            }
            else
            {
                $x++;
                $y += $this->dir;
                $this->bit--;
            }

            if ( $this->dir < 0 )
            {
                if ( $y < 0 )
                {
                    $y         = 0;
                    $x         -= 2;
                    $this->dir = 1;
                    if ( $x == 6 )
                    {
                        $x--;
                        $y = 9;
                    }
                }
            }
            else
            {
                if ( $y == $w )
                {
                    $y         = $w - 1;
                    $x         -= 2;
                    $this->dir = -1;
                    if ( $x == 6 )
                    {
                        $x--;
                        $y -= 8;
                    }
                }
            }
            if ( $x < 0 || $y < 0 ) return null;

            $this->x = $x;
            $this->y = $y;

        } while ( ord($this->frame[$y][$x]) & 0x80 );

        return [ 'x' => $x, 'y' => $y ];
    }
}

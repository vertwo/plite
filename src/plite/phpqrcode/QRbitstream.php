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



define('QR_CACHEABLE', false);       // use cache - more disk reads but less CPU power, masks and format templates are stored there
define('QR_CACHE_DIR', false);       // used when QR_CACHEABLE === true
define('QR_LOG_DIR', false);         // default error logs dir

define('QR_FIND_BEST_MASK', true);                                                          // if true, estimates best mask (spec. default, but extremally slow; set to false to significant performance boost but (propably) worst quality code
define('QR_FIND_FROM_RANDOM', 2);                                                       // if false, checks all masks available, otherwise value tells count of masks need to be checked, mask id are got randomly
define('QR_DEFAULT_MASK', 2);                                                               // when QR_FIND_BEST_MASK === false

define('QR_PNG_MAXIMUM_SIZE', 1024);                                                       // maximum allowed png image width (in pixels), tune to make sure GD and PHP can handle such big images



/*
 * PHP QR Code encoder
 *
 * Bitstream class
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
 */



class QRbitstream
{

    public $data = [];

    //----------------------------------------------------------------------
    public function size ()
    {
        return count($this->data);
    }

    //----------------------------------------------------------------------
    public function allocate ( $setLength )
    {
        $this->data = array_fill(0, $setLength, 0);
        return 0;
    }

    //----------------------------------------------------------------------
    public static function newFromNum ( $bits, $num )
    {
        $bstream = new QRbitstream();
        $bstream->allocate($bits);

        $mask = 1 << ($bits - 1);
        for ( $i = 0; $i < $bits; $i++ )
        {
            if ( $num & $mask )
            {
                $bstream->data[$i] = 1;
            }
            else
            {
                $bstream->data[$i] = 0;
            }
            $mask = $mask >> 1;
        }

        return $bstream;
    }

    //----------------------------------------------------------------------
    public static function newFromBytes ( $size, $data )
    {
        $bstream = new QRbitstream();
        $bstream->allocate($size * 8);
        $p = 0;

        for ( $i = 0; $i < $size; $i++ )
        {
            $mask = 0x80;
            for ( $j = 0; $j < 8; $j++ )
            {
                if ( $data[$i] & $mask )
                {
                    $bstream->data[$p] = 1;
                }
                else
                {
                    $bstream->data[$p] = 0;
                }
                $p++;
                $mask = $mask >> 1;
            }
        }

        return $bstream;
    }

    //----------------------------------------------------------------------
    public function append ( QRbitstream $arg )
    {
        if ( is_null($arg) )
        {
            return -1;
        }

        if ( $arg->size() == 0 )
        {
            return 0;
        }

        if ( $this->size() == 0 )
        {
            $this->data = $arg->data;
            return 0;
        }

        $this->data = array_values(array_merge($this->data, $arg->data));

        return 0;
    }

    //----------------------------------------------------------------------
    public function appendNum ( $bits, $num )
    {
        if ( $bits == 0 )
            return 0;

        $b = QRbitstream::newFromNum($bits, $num);

        if ( is_null($b) )
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    //----------------------------------------------------------------------
    public function appendBytes ( $size, $data )
    {
        if ( $size == 0 )
            return 0;

        $b = QRbitstream::newFromBytes($size, $data);

        if ( is_null($b) )
            return -1;

        $ret = $this->append($b);
        unset($b);

        return $ret;
    }

    //----------------------------------------------------------------------
    public function toByte ()
    {

        $size = $this->size();

        if ( $size == 0 )
        {
            return [];
        }

        $data  = array_fill(0, (int)(($size + 7) / 8), 0);
        $bytes = (int)($size / 8);

        $p = 0;

        for ( $i = 0; $i < $bytes; $i++ )
        {
            $v = 0;
            for ( $j = 0; $j < 8; $j++ )
            {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$i] = $v;
        }

        if ( $size & 7 )
        {
            $v = 0;
            for ( $j = 0; $j < ($size & 7); $j++ )
            {
                $v = $v << 1;
                $v |= $this->data[$p];
                $p++;
            }
            $data[$bytes] = $v;
        }

        return $data;
    }

}

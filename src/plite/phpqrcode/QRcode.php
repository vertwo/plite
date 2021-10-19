<?php



namespace vertwo\plite\phpqrcode;



use Exception;



class QRcode
{

    public $version;
    public $width;
    public $data;

    //----------------------------------------------------------------------
    /**
     * @param QRinput $input
     * @param         $mask
     *
     * @return $this|null
     * @throws Exception
     */
    public function encodeMask ( QRinput $input, $mask )
    {
        if ( $input->getVersion() < 0 || $input->getVersion() > QRSPEC_VERSION_MAX )
        {
            throw new Exception('wrong version');
        }
        if ( $input->getErrorCorrectionLevel() > QR_ECLEVEL_H )
        {
            throw new Exception('wrong level');
        }

        $raw = new QRrawcode($input);

        QRtools::markTime('after_raw');

        $version = $raw->version;
        $width   = QRspec::getWidth($version);
        $frame   = QRspec::newFrame($version);

        $filler = new FrameFiller($width, $frame);
        if ( is_null($filler) )
        {
            return null;
        }

        // inteleaved data and ecc codes
        for ( $i = 0; $i < $raw->dataLength + $raw->eccLength; $i++ )
        {
            $code = $raw->getCode();
            $bit  = 0x80;
            for ( $j = 0; $j < 8; $j++ )
            {
                $addr = $filler->next();
                $filler->setFrameAt($addr, 0x02 | (($bit & $code) != 0));
                $bit = $bit >> 1;
            }
        }

        QRtools::markTime('after_filler');

        unset($raw);

        // remainder bits
        $j = QRspec::getRemainder($version);
        for ( $i = 0; $i < $j; $i++ )
        {
            $addr = $filler->next();
            $filler->setFrameAt($addr, 0x02);
        }

        $frame = $filler->frame;
        unset($filler);


        // masking
        $maskObj = new QRmask();
        if ( $mask < 0 )
        {

            if ( QR_FIND_BEST_MASK )
            {
                $masked = $maskObj->mask($width, $frame, $input->getErrorCorrectionLevel());
            }
            else
            {
                $masked = $maskObj->makeMask($width, $frame, (intval(QR_DEFAULT_MASK) % 8), $input->getErrorCorrectionLevel());
            }
        }
        else
        {
            $masked = $maskObj->makeMask($width, $frame, $mask, $input->getErrorCorrectionLevel());
        }

        if ( $masked == null )
        {
            return null;
        }

        QRtools::markTime('after_mask');

        $this->version = $version;
        $this->width   = $width;
        $this->data    = $masked;

        return $this;
    }

    //----------------------------------------------------------------------
    /**
     * @param QRinput $input
     *
     * @return $this|null
     * @throws Exception
     */
    public function encodeInput ( QRinput $input )
    {
        return $this->encodeMask($input, -1);
    }

    //----------------------------------------------------------------------
    /**
     * @param $string
     * @param $version
     * @param $level
     *
     * @return $this|null
     * @throws Exception
     */
    public function encodeString8bit ( $string, $version, $level )
    {
        if ( $string == null )
        {
            throw new Exception('empty string!');
        }

        $input = new QRinput($version, $level);
        if ( $input == null ) return null;

        $ret = $input->append($input, QR_MODE_8, strlen($string), str_split($string));
        if ( $ret < 0 )
        {
            unset($input);
            return null;
        }
        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    /**
     * @param $string
     * @param $version
     * @param $level
     * @param $hint
     * @param $casesensitive
     *
     * @return $this|null
     * @throws Exception
     */
    public function encodeString ( $string, $version, $level, $hint, $casesensitive )
    {

        if ( $hint != QR_MODE_8 && $hint != QR_MODE_KANJI )
        {
            throw new Exception('bad hint');
        }

        $input = new QRinput($version, $level);
        if ( $input == null ) return null;

        $ret = QRsplit::splitStringToQRinput($string, $input, $hint, $casesensitive);
        if ( $ret < 0 )
        {
            return null;
        }

        return $this->encodeInput($input);
    }

    //----------------------------------------------------------------------
    /**
     * @param      $text
     * @param bool $outfile
     * @param int  $level
     * @param int  $size
     * @param int  $margin
     * @param bool $saveandprint
     *
     * @throws Exception
     */
    public static function png ( $text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4, $saveandprint = false )
    {
        $enc = QRencode::factory($level, $size, $margin);
        $enc->encodePNG($text, $outfile, $saveandprint = false);
    }

    //----------------------------------------------------------------------
    /**
     * @param      $text
     * @param bool $outfile
     * @param int  $level
     * @param int  $size
     * @param int  $margin
     *
     * @return mixed
     * @throws Exception
     */
    public static function text ( $text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4 )
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encode($text, $outfile);
    }

    //----------------------------------------------------------------------
    /**
     * @param      $text
     * @param bool $outfile
     * @param int  $level
     * @param int  $size
     * @param int  $margin
     *
     * @return mixed
     * @throws Exception
     */
    public static function raw ( $text, $outfile = false, $level = QR_ECLEVEL_L, $size = 3, $margin = 4 )
    {
        $enc = QRencode::factory($level, $size, $margin);
        return $enc->encodeRAW($text, $outfile);
    }
}

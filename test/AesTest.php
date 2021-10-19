<?php declare(strict_types = 1);



/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice,
 * this list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE
 * ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE
 * LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR
 * CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF
 * SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS
 * INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN
 * CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
 * ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 */



use phpseclib3\Crypt\AES;
use PHPUnit\Framework\TestCase;
use vertwo\plite\FJ;
use function vertwo\plite\clog;



final class AesTest extends TestCase
{
    const DEBUG = false;

    const PLAIN_HEX_1 = "6bc1bee22e409f96e93d7e117393172a";
    const PLAIN_HEX_2 = "ae2d8a571e03ac9c9eb76fac45af8e51";
    const PLAIN_HEX_3 = "30c81c46a35ce411e5fbc1191a0a52ef";
    const PLAIN_HEX_4 = "f69f2445df4f9b17ad2b417be66c3710";
    const PLAIN_HEX   = self::PLAIN_HEX_1 .
                        self::PLAIN_HEX_2 .
                        self::PLAIN_HEX_3 .
                        self::PLAIN_HEX_4;

    const CIPHER_HEX_1 = "601ec313775789a5b7a7f504bbf3d228";
    const CIPHER_HEX_2 = "f443e3ca4d62b59aca84e990cacaf5c5";
    const CIPHER_HEX_3 = "2b0930daa23de94ce87017ba2d84988d";
    const CIPHER_HEX_4 = "dfc9c58db67aada613c2dd08457941a6";
    const CIPHER_HEX   = self::CIPHER_HEX_1 .
                         self::CIPHER_HEX_2 .
                         self::CIPHER_HEX_3 .
                         self::CIPHER_HEX_4;

    const KEY_HEX = "603deb1015ca71be2b73aef0857d77811f352c073b6108d72d9810a30914dff4";
    const CTR_HEX = "f0f1f2f3f4f5f6f7f8f9fafbfcfdfeff";



    private $plain;
    private $cipher;
    private $key;
    private $ctr;



    /**
     * AesTest constructor.
     *
     * @param string|null $name
     * @param array       $data
     * @param string      $dataName
     *
     * @throws Exception
     */
    function __construct ( string $name = null, array $data = [], $dataName = '' )
    {
        parent::__construct($name, $data, $dataName);

        $this->plain  = hex2bin(self::PLAIN_HEX);
        $this->cipher = hex2bin(self::CIPHER_HEX);
        $this->key    = hex2bin(self::KEY_HEX);
        $this->ctr    = hex2bin(self::CTR_HEX);

        if ( self::DEBUG )
        {
            clog("       key (hex)", self::KEY_HEX);
            clog("       ctr (hex)", self::CTR_HEX);
            clog(" plaintext (hex)", self::PLAIN_HEX);
            clog("    cipher (hex)", self::CIPHER_HEX);
        }
    }



    public function testEncrypt ()
    {
        $cipher = $this->encrypt($this->key, $this->ctr, $this->plain);

        $this->assertEquals($cipher, $this->cipher);
    }


    public function testEmbeddedEncrypt ()
    {
        $cipher = FJ::encrypt($this->key, $this->ctr, $this->plain);

        $this->assertEquals($cipher, $this->cipher);
    }



    public function testDecrypt ()
    {
        $plain = $this->decrypt($this->key, $this->ctr, $this->cipher);

        $this->assertEquals($plain, $this->plain);
    }


    public function testEmbeddedDecrypt ()
    {
        $plain = FJ::decrypt($this->key, $this->ctr, $this->cipher);

        $this->assertEquals($plain, $this->plain);
    }



    public function testEmbeddedEncryptionMatches ()
    {
        $lib = $this->encrypt($this->key, $this->ctr, $this->plain);
        $fj  = FJ::encrypt($this->key, $this->ctr, $this->plain);

        $this->assertEquals($lib, $fj);
    }



    public function testEmbeddedDecryptionMatches ()
    {
        $lib = $this->decrypt($this->key, $this->ctr, $this->cipher);
        $fj  = FJ::decrypt($this->key, $this->ctr, $this->cipher);

        $this->assertEquals($lib, $fj);
    }



    private function encrypt ( $key, $iv, $plaintext )
    {
        $aes = new AES("ctr");
        $aes->setKey($key);
        $aes->setIV($iv);

        // the following does the same thing:
        //$cipher->setPassword('whatever', 'pbkdf2', 'sha1', 'phpseclib/salt', 1000, 256 / 8);
        //$cipher->setIV('...'); // defaults to all-NULLs if not explicitely defined

        //$size      = 10 * 1024;
        //$plaintext = str_repeat('a', $size);

        $ciphertext = $aes->encrypt($plaintext);

        return $ciphertext;
    }



    private function decrypt ( $key, $iv, $ciphertext )
    {
        $aes = new AES("ctr");
        $aes->setKey($key);
        $aes->setIV($iv);

        $plaintext = $aes->decrypt($ciphertext);

        return $plaintext;

    }



//    function test_mcrypt ()
//    {
//        clog("ciphertext (hex)", $cipherHex);
//
//        $ivSize128  = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, 'ctr');
//        $ivSize192  = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_192, 'ctr');
//        $ivSize256  = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, 'ctr');
//        $isCTRBlock = mcrypt_module_is_block_algorithm_mode('ctr');
//
//        clog("AES-128 - IV (CTR) length", $ivSize128);
//        clog("AES-192 - IV (CTR) length", $ivSize192);
//        clog("AES-256 - IV (CTR) length", $ivSize256);
//        clog("CTR (per NIST)     length", strlen($ctr));
//        clog("CTR is block mode in PHP?", $isCTRBlock);
//
//        $td = mcrypt_module_open('rijndael-256', '', 'ecb', '');
//        mcrypt_generic_init($td, $key, $ctr);
//        $out    = mcrypt_generic($td, $plain);
//        $outHex = bin2hex($out);
//
//        clog("       key (hex)", $keyHex);
//        clog("       ctr (hex)", $ctrHex);
//        clog(" plaintext (hex)", $plainHex);
//        clog("ciphertext (hex)", $cipherHex);
//        clog("    output (hex)", $outHex);
//
//        if ( $cipher == $out )
//        {
//            clog("mcrypt (Rijndal-256 used as AES-256) encryption worked?", true);
//        }
//        else
//        {
//            clog("mcrypt (Rijndal-256 used as AES-256) encryption worked?", false);
//        }
//
//        return false;
//    }
}

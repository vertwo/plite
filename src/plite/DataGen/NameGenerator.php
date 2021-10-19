<?php
/**
 * Copyright (c) 2012-2021 Troy Wu
 * Copyright (c) 2021      Version2 OÜ
 * All rights reserved.
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



namespace vertwo\plite\DataGen;



use \Exception;
use vertwo\plite\FJ;
use vertwo\plite\Log;
use vertwo\plite\Util\CSVisitorBasic;
use function vertwo\plite\clog;



class NameGenerator extends CSVisitorBasic
{
    private $genderMap = [];
    private $firsts    = [];
    private $lasts     = [];
    private $girls     = [];
    private $boys      = [];



    /**
     * @param $maxFamilySize
     *
     * @return mixed
     * @throws Exception
     */
    public function getFamily ( $maxFamilySize )
    {
        $last = $this->getRandomLast();
        $dad  = $this->getRandomFirstByGender("m");
        $mom  = $this->getRandomFirstByGender("f");

        $dadFull = ucwords("$dad $last");
        $momFull = ucwords("$mom $last");

        $family           = [];
        $family[$dadFull] = "m";
        $family[$momFull] = "f";

        $kidMax   = $maxFamilySize - 2;
        $kidCount = random_int(0, $kidMax);

        $firsts = [ $dad, $mom ];

        clog("kid count", $kidCount);

        for ( $k = 0; $k < $kidCount; ++$k )
        {
            do
            {
                list($g, $kid) = $this->getRandomFirst();
            } while ( in_array($kid, $firsts) );

//            list($g, $kid) = $this->getRandomFirst();

            if ( in_array($kid, $firsts) )
            {
                Log::error("[ $kid ] already exists!");
                clog("family", $family);
            }
            else $firsts[] = $kid;

            $kidFull          = ucwords("$kid $last");
            $family[$kidFull] = $g;
        }

        return $family;
    }



    /**
     * @param $gender
     *
     * @return string
     * @throws Exception
     */
    public function getRandomName ( $gender )
    {
        $first = $this->getRandomFirstByGender($gender);
        $last  = $this->getRandomLast();

        return ucwords("$first $last");
    }



    /**
     * @return array
     * @throws Exception
     */
    private function getRandomFirst ()
    {
        $first  = $this->getRandomFromArray($this->firsts);
        $gender = $this->genderMap[$first];

        return [ $gender, $first ];
    }



    /**
     * @return int
     * @throws Exception
     */
    private function getRandomFirstByGender ( $gender )
    {
        if ( FJ::startsWith("m", $gender) )
            $ar = $this->boys;
        elseif ( FJ::startsWith("f", $gender) )
            $ar = $this->girls;

        return $this->getRandomFromArray($ar);
    }



    /**
     * @param $ar
     *
     * @return mixed
     * @throws Exception
     */
    private function getRandomFromArray ( $ar ) { return $ar[random_int(0, count($ar) - 1)]; }



    /**
     * @return mixed
     * @throws Exception
     */
    private function getRandomLast () { return $this->getRandomFromArray($this->lasts); }



    /**
     * NOTE - "Main" method, called for each CSV line tokenized.
     *
     * @param int        $lineIndex - Index of line, inclusive of all lines.
     * @param string[]   $tokens    - Array of strings.
     * @param array|bool $columns   - (false) if no column names; otherwise, array of columns names
     *
     * @return mixed
     */
    function parse ( $lineIndex, $tokens, $columns = false )
    {
        if ( count($tokens) < 2 ) return false;

        list($name, $gender) = $tokens;

        $gender = strtolower($gender);

        $nameTokens = explode(" ", $name);

        if ( count($nameTokens) < 2 ) return false;

        list ($first, $last) = $nameTokens;

        if ( !in_array($first, $this->firsts) )
        {
            $this->firsts[]          = $first;
            $this->genderMap[$first] = $gender;

            if ( FJ::startsWith("m", $gender) )
                $this->boys[] = $first;
            elseif ( FJ::startsWith("f", $gender) )
                $this->girls[] = $first;
        }
        if ( !in_array($last, $this->lasts) ) $this->lasts[] = $last;

        return false;
    }
    /**
     * Handle columns headers (column names).  This is called BEFORE parse().
     *
     * @param array $columns - Column headers.
     */
    function parseHeaders ( $columns )
    {
        // TODO: Implement parseHeaders() method.
    }
}

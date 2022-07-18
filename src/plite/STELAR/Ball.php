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



namespace vertwo\plite\STELAR;



use vertwo\plite\FJ;
use vertwo\plite\Log;
use function vertwo\plite\clog;
use function vertwo\plite\redulog;



class Ball
{
    const DEBUG_MERGE         = false;
    const DEBUG_MERGE_VERBOSE = false;

    const ARRAY_SYNTAX_REGEX = '/^([A-z_][A-z\-_]*)\[(\d+)\]$/';
    const DEFAULT_DELIM      = ".";



    protected $data;
    protected $delim;
    protected $delimLen;
    protected $history = [];



    public static function fromJSON ( $jsonString, $delim = self::DEFAULT_DELIM )
    {
        $obj = FJ::js($jsonString);
        return new Ball($obj, $delim);
    }



    public static function fromFlat ( $flat, $delim = self::DEFAULT_DELIM )
    {
        $data = [];
        $ball = new Ball($data, $delim);
        foreach ( $flat as $path => $v )
            $ball->set($path, $v);

        return $ball;
    }



    public function __construct ( $obj, $delim = self::DEFAULT_DELIM )
    {
        $this->data     = $obj;
        $this->delim    = $delim;
        $this->delimLen = strlen($delim);
    }



    public function disableHistory () { $this->history = false; }



    public function data () { return $this->data; }



    /**
     * @param $path
     *
     * @return mixed
     */
    public function get ( $path )
    {
        $keys = self::getKeyList($path);

        $scope = $this->data;

        foreach ( $keys as $k )
            $scope = $scope[$k];

        return $scope;
    }



    /**
     * @return BallIterator
     */
    public function iterator () { return new BallIterator($this); }
    //public function conformingIterator () { return new BallIterator($this); }



    public function has ( $path )
    {
        $keys = self::getKeyList($path);

        $scope = $this->data;

        foreach ( $keys as $k )
        {
            if ( !array_key_exists($k, $scope) ) return false;
            $scope = $scope[$k];
        }

        return true;
    }
    public function no ( $path ) { return !$this->has($path); }



    public function hasPrefix ( $pathPrefix )
    {
        if ( !FJ::startsWith($this->delim, $pathPrefix) ) $pathPrefix = $this->delim . $pathPrefix;

        $flat = $this->flatten();
        $keys = array_keys($flat);

        foreach ( $keys as $k )
        {
            if ( FJ::startsWith($pathPrefix, $k) ) return true;
        }

        return false;
    }
    public function noPrefix ( $pathPrefix ) { return !$this->hasPrefix($pathPrefix); }



    public function getPrefixes ( $pathPrefix )
    {
        if ( !FJ::startsWith($this->delim, $pathPrefix) ) $pathPrefix = $this->delim . $pathPrefix;

        $flat = $this->flatten();
        $keys = array_keys($flat);

        $prefixes = [];

        foreach ( $keys as $k )
        {
            if ( FJ::startsWith($pathPrefix, $k) ) $prefixes[] = $k;
        }

        return $prefixes;
    }



    /**
     * $ball->set("a.b.c", 5);
     *
     * $this->data[a][b][c] = 5;
     *
     * @param $path
     * @param $value
     *
     * @return Ball
     */
    public function set ( $path, $value )
    {
        $keys         = self::getKeyList($path);
        $keyCount     = count($keys);
        $lastKeyIndex = $keyCount - 1;

        $scope = &$this->data;

        for ( $i = 0; $i < $lastKeyIndex; ++$i )
        {
            $key   = $keys[$i];
            $scope = &$scope[$key];
        }

        $key         = $keys[$lastKeyIndex];
        $scope[$key] = $value;

        return $this;
    }



    /**
     * @param $path
     * @param $value
     *
     * @return Ball
     */
    public function merge ( $path, $value )
    {
        $flat = $this->flatten();

        if ( self::DEBUG_MERGE ) clog("flat", $flat);

        $vb = new Ball([], $this->delim);
        $vb->set($path, $value);

        if ( self::DEBUG_MERGE_VERBOSE ) $vb->dump("setting 1 value");

        $fv = $vb->flatten();

        if ( self::DEBUG_MERGE ) clog("flattened value", $fv);

        $merged     = array_merge($flat, $fv);
        $mergedBall = Ball::fromFlat($merged, $this->delim);
        $this->data = $mergedBall->data;

        return $this;
    }



    /**
     * @param Ball $nb - New Ball object to merge into existing.
     *
     * Like merge(), but with a whole ball (not a single param).
     */
    public function mergeBall ( $nb )
    {
        $flat = $this->flatten();

        clog("flat", $flat);

        $fv = $nb->flatten();

        clog("flattened value", $fv);

        $merged     = array_merge($flat, $fv);
        $mergedBall = Ball::fromFlat($merged, $this->delim);
        $this->data = $mergedBall->data;

        return $this;
    }



    public function copy ( $pathSrc, $pathDest ) { return $this->has($pathSrc) ? $this->set($pathDest, $this->get($pathSrc)) : $this; }



    /**
     *
     * @param $path
     * @param $value
     *
     * @return Ball
     */
    public function move ( $pathSrc, $pathDest ) { return $this->copy($pathSrc, $pathDest)->delete($pathSrc); }



    /**
     * $ball->set("a.b.c", 5);
     *
     * $this->data[a][b][c] = 5;
     *
     * @param $path
     * @param $value
     *
     * @return Ball
     */
    public function delete ( $path )
    {
        $keys         = self::getKeyList($path);
        $keyCount     = count($keys);
        $lastKeyIndex = $keyCount - 1;

        $scope = &$this->data;

        for ( $i = 0; $i < $lastKeyIndex; ++$i )
        {
            $key   = $keys[$i];
            $scope = &$scope[$key];
        }

        $key = $keys[$lastKeyIndex];
        unset($scope[$key]);

        return $this;
    }



    public function swap ( $pathA, $pathB )
    {
        $valA = $this->get($pathA);
        $valB = $this->get($pathB);
        $this->set($pathA, $valB);
        $this->set($pathB, $valA);

        return $this;
    }



    public function flatten ()
    {
        $flat = self::flattenJSON($this->delim, $this->data);
        return $flat;
    }



    public function flattenNicely ()
    {
        $flat = self::flattenJSON($this->delim, $this->data);

        $nice = [];

        foreach ( $flat as $k => $v )
        {
            if ( FJ::startsWith($this->delim, $k) )
            {
                $niceKey        = substr($k, $this->delimLen);
                $nice[$niceKey] = $v;
            }
            else
            {
                $nice[$k] = $v;
            }
        }

        return $nice;
    }



    /**
     * This method takes a set of keys as input, and outputs a ball which "conforms"
     * to the given keys.  Keys which exist in this ball will be copied; keys which
     * do not exist in this ball will be given the value of empty-string ("").
     *
     * Keys which exist in this ball but which are _NOT_ specified in the given key
     * list will NOT BE INCLUDED in the output.
     *
     * @param $givenKeys
     *
     * @return array - output values, in the order of the given key list.
     */
    public function conform ( $givenKeys )
    {
        $output = [];

        foreach ( $givenKeys as $givenKey )
        {
            $output[$givenKey] = $this->has($givenKey) ? $this->get($givenKey) : "";
        }

        return $output;
    }



    /**
     * Assuming that input array is a list of objects,
     * and that when flattening that object, each item
     * is a __COLUMN__ in a CSV.
     *
     * @param            $ar             - Input array.
     * @param            $emptyCellValue - What to fill for empty fields.
     * @param bool|array $rowHeaderMap   - List of row-headers.
     */
    public static function columnsToCSV ( $ar, $emptyCellValue = "", $rowHeaderMap = false )
    {
        $csv = [];

        if ( false === $rowHeaderMap )
        {
            $rowHeaderMap      = self::keyIndexMap($ar);
            $firstColumnHeader = "Description";
        }
        else
        {
            $firstColumnHeader = array_values($rowHeaderMap)[0];
            array_shift($rowHeaderMap);
        }

        $headers = array_keys($ar);
        array_unshift($headers, $firstColumnHeader);

        $headerCSV = implode(",", $headers);
        $csv[]     = $headerCSV;

        foreach ( $rowHeaderMap as $k => $idx )
        {
            $row = [];

            //
            // Put row header.
            //
            $row[] = (false !== $rowHeaderMap) ? $rowHeaderMap[$k] : $k;

            foreach ( $ar as $col )
            {
                $row[] = array_key_exists($k, $col) ? $col[$k] : $emptyCellValue;
            }

            $rowCSV = implode(",", $row);
            $csv[]  = $rowCSV;
        }

        return $csv;
    }



    public static function mapToCSV ( $map )
    {
        // NOTE - Each outermost [$col => $colDataMap] entry in map is a column in a CSV.
        //  e.g., States of the USA.

        // NOTE - And each column contains many entries [$k => $v].
        //   Assume most $k will overlap beteween columns.
        //   e.g., Info about each state (e.g., name, capitol, date, population, area, roads, etc).

        $cols   = [];
        $cols[] = "metric";

        $union = [];
        foreach ( $map as $col => $colDataMap )
        {
            $cols[] = $col;
            $union  = array_merge($union, $colDataMap);
        }

        $metricNames = array_keys($union);

        $csv    = [];
        $csv[0] = implode(",", $cols);

        $k = 1;
        foreach ( $metricNames as $metricName )
        {
            $row    = [];
            $row[0] = $metricName;

            foreach ( $cols as $col )
            {
                if ( !array_key_exists($col, $map) ) continue;

                $source = $map[$col];
                $cell   = array_key_exists($metricName, $source) ? $source[$metricName] : 0;

                //clog("$col / $metricName", $cell);
                $row[] = $cell;
            }
            $csv[$k++] = implode(",", $row);
        }

        $csv = implode("\n", $csv);

        return $csv;
    }



    public static function dumpCSV ( $csv ) { foreach ( $csv as $row ) printf("%s\n", $row); }



    /**
     * Assuming that input array is a list of objects,
     * and that when flattening that object, each item
     * is a __ROW__ in a CSV.
     *
     * NOTE - This is the more "natural" ordering.
     *
     * @param $ar - Input array.
     */
    public static function rowsToCSV ( $ar )
    {

    }



    public static function keyIndexMap ( $ar ) { return array_flip(self::keyUnion($ar)); }
    public static function keyUnion ( $ar )
    {
        $outerKeys = array_keys($ar);

        $count = count($ar);
        $k     = $outerKeys[0];
        $union = array_keys($ar[$k]);

        for ( $i = 1; $i < $count; ++$i )
        {
            $k    = $outerKeys[$i];
            $obj  = $ar[$k];
            $keys = array_keys($obj);

            $diff = array_diff($keys, $union);

            if ( count($diff) > 0 ) clog("diff", $diff);

            array_merge($union, $diff);
        }

        return $union;
    }



    /**
     * Depth-first flattening, using a delimiter to separate subkeys.
     *
     * @param $delim
     * @param $jobj
     *
     * @return mixed
     */
    private static function flattenJSON ( $delim, $jobj )
    {
        $root = "";
        $flat = self::recursivelyFlattenJSON($delim, $jobj, $root, []);

        return $flat;
    }
    private static function recursivelyFlattenJSON ( $delim, $jobj, $outerKey, $o, $isSeqNumArrayElem = false )
    {
        $innerKeys     = array_keys($jobj);
        $innerKeyCount = count($innerKeys);

        if ( 0 === $innerKeyCount )
        {
            $o[$outerKey] = [];
            return $o;
        }

        foreach ( $jobj as $k => $v )
        {
            // $v is a resource; skip.
            if ( is_resource($v) ) continue;

            $flatKey = $isSeqNumArrayElem ? "{$outerKey}[$k]" : "{$outerKey}{$delim}$k";

            if ( is_scalar($v) || is_null($v) )
            {
                // $v is a scalar.
                $o[$flatKey] = $v;
            }
            elseif ( is_array($v) )
            {
                // $v is an array.
                $o = self::recursivelyFlattenJSON($delim, $v, $flatKey, $o, self::isSeqNumArray($v));
            }
            else
            {
                Log::dump();
                redulog("Can it be other things?? - [$v]");
            }
        }

        return $o;
    }
    private static function isAssocArray ( array $arr )
    {
        if ( [] === $arr ) return false;
        return array_keys($arr) !== range(0, count($arr) - 1);
    }
    private static function isSeqNumArray ( array $arr ) { return !self::isAssocArray($arr); }



    private function getKeyList ( $path )
    {
        $path = trim($path);
        if ( FJ::startsWith($this->delim, $path) )
            $path = substr($path, $this->delimLen);

        $keys       = [];
        $pathTokens = explode($this->delim, $path);
        foreach ( $pathTokens as $t )
        {
            $isok = preg_match(self::ARRAY_SYNTAX_REGEX, $t, $matches);
            if ( 1 === $isok )
            {
                $keys[] = $matches[1];
                $keys[] = $matches[2];
            }
            else
            {
                $keys[] = $t;
            }
        }

        return $keys;
    }



    public function dump ( $mesg = false )
    {
        $mesg = (false === $mesg) ? "ball" : $mesg;
        clog($mesg, $this->data);
    }



    public function string () { return FJ::js($this->data); }
}

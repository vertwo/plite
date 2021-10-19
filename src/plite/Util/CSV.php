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



namespace vertwo\plite\Util;



use Exception;
use vertwo\plite\Log;
use function vertwo\plite\cclog;
use function vertwo\plite\clog;



class CSV
{
    const DEBUG_CSV_VERBOSE = false;
    const DEBUG_CSV_COLUMNS = false;

    const CSV_DELIM_DEFAULT = ",";
    const CSV_DELIM_COMMA   = self::CSV_DELIM_DEFAULT;
    const CSV_DELIM_OUTPUT  = self::CSV_DELIM_COMMA;
    const CSV_DELIM_SEMI    = ";";
    const CSV_COMMENT_CHAR  = "#";


    private $filepath;
    private $delim;
    private $columns;

    private $isFilepathActuallyData = false;

    /**
     * @var bool|CSVResultHandler
     */
    private $resultHandler = false;


    public function __construct ( $filepath, $delim = self::CSV_DELIM_COMMA )
    {
        $this->filepath = $filepath;
        $this->delim    = $delim;
    }



    /**
     * This will cause the read() method to treat the 1st arg to the constructor,
     * intended to be a path to a file, as the raw CSV data as a string.
     *
     * @see CSV::read()
     */
    public function treatInputAsRawData ()
    {
        $this->isFilepathActuallyData = true;
    }



    /**
     * Checks the first character of a string, to compare with test character.
     *
     * @param $string String - Input to check for match.
     * @param $c      String - Test character.
     *
     * @return bool
     */
    public static function isFirstCharacter ( $string, $c )
    {
        $firstChar = substr($string[0], 0, 1);

        if ( self::DEBUG_CSV_VERBOSE ) clog("first char", $firstChar);

        return $c == $firstChar;
    }


    /**
     * @param $tokens array - CSV tokens.
     *
     * @return bool - (true) if line is empty (may return 1 token of zero-length; (false) otherwise.
     */
    public static function isEmptyLine ( $tokens )
    {
        return (
            (0 == count($tokens))
            ||
            (1 == count($tokens) && 0 == strlen(trim($tokens[0])))
        );
    }


    /**
     * @param $tokens array - CSV tokens.
     *
     * @return bool - (true) if trimmed line is either empty or starts with '#'; (false) otherwise.
     */
    public static function isCommentLine ( $tokens )
    {
        if ( null == $tokens || self::isEmptyLine($tokens) ) return true;

        $token = $tokens[0];

        if ( self::isFirstCharacter($token, self::CSV_COMMENT_CHAR) ) return true;

        return false;
    }


    public static function stripBOMHeader ( $tokens )
    {
        if ( self::DEBUG_CSV_VERBOSE ) clog("stripBOMHeader tokens", $tokens);

        $first = $tokens[0];

        $f0 = substr($first, 0, 1);
        $f1 = substr($first, 1, 1);
        $f2 = substr($first, 2, 1);

        // The UTF-8 representation of the BOM is the (hexadecimal) byte sequence 0xEF,0xBB,0xBF.

        $is0 = 0xEF === ord($f0);
        $is1 = 0xBB === ord($f1);
        $is2 = 0xBF === ord($f2);

        $hasBOM = $is0 && $is1 && $is2;

        if ( self::DEBUG_CSV_VERBOSE ) clog("has BOM", $hasBOM);
        if ( self::DEBUG_CSV_VERBOSE ) clog("    is0", $is0);
        if ( self::DEBUG_CSV_VERBOSE ) clog("    is1", $is1);
        if ( self::DEBUG_CSV_VERBOSE ) clog("    is2", $is2);

        if ( $hasBOM )
        {
            $output    = [];
            $output[0] = substr($first, 3);
            $len       = count($tokens);
            for ( $i = 1; $i < $len; ++$i )
            {
                $output[$i] = $tokens[$i];
            }

            if ( self::DEBUG_CSV_VERBOSE ) clog("output", $output);

            return $output;
        }

        if ( self::DEBUG_CSV_VERBOSE ) clog("after", $tokens);

        return $tokens;
    }



    /**
     * @param CSVResultHandler $handler
     */
    public function setResultHandler ( $handler ) { if ( $handler ) $this->resultHandler = $handler; }



    /**
     * @param CSVisitor $visitor       - Visits each line in the CSV, tokenized by fgetcsv().
     * @param bool      $hasHeaderLine - Does this file have a header line?
     *
     * @throws Exception
     *
     * @see CSV::treatInputAsRawData()
     */
    public function read ( $visitor, $hasHeaderLine = false )
    {
        if ( $this->isFilepathActuallyData )
        {
            $this->readAsData($visitor, $hasHeaderLine);
            return;
        }

        $hasResultHandler = false !== $this->resultHandler;

        $csv = fopen($this->filepath, "r");

        if ( false === $csv )
        {
            clog("File {$csv} could not be opened for reading; aborting.");
            throw new Exception("CSV could not open {$csv}.");
        }

        if ( self::DEBUG_CSV_VERBOSE ) clog("Reading CSV file", $this->filepath);

        if ( $hasHeaderLine )
        {
            $firstLineTokens = fgetcsv($csv); // Assume first line has column names
            $this->columns   = self::stripBOMHeader($firstLineTokens); // Excel bullshit.
            if ( self::DEBUG_CSV_COLUMNS ) clog("columns", $this->columns);
        }

        $lineIndex = -1;

        while ( false !== ($tokens = fgetcsv($csv, 0, $this->delim)) )
        {
            ++$lineIndex;

            if ( self::DEBUG_CSV_VERBOSE ) clog("start", $tokens);

            $visitor->ante($lineIndex);

            //$tokens = self::stripBOMHeader($tokens); // NOTE - stripping BOM headers on EVERY LINE???
            //if ( self::DEBUG_CSV_VERBOSE ) clog("after BOM-strip", $tokens);

            if ( $this->isCommentLine($tokens) )
            {
                if ( self::DEBUG_CSV_VERBOSE ) cclog(Log::TEXT_COLOR_UL_YELLOW, "Skipping line: " . implode(",", $tokens));
                $visitor->parseComment($lineIndex, $tokens);
                $result = false;
            }
            else
            {
                $result = $visitor->parse($lineIndex, $tokens, $this->columns);
            }

            $visitor->post($lineIndex);

            if ( $hasResultHandler && false !== $result ) $this->resultHandler->handleResult($lineIndex, $result);
        }

        fclose($csv);

        $visitor->finish();
    }



    /**
     * @param CSVisitor $visitor       - Visits each line in the CSV, tokenized by fgetcsv().
     * @param bool      $hasHeaderLine - Does this file have a header line?
     *
     * @throws Exception
     *
     * @see CSV::treatInputAsRawData()
     */
    private function readAsData ( $visitor, $hasHeaderLine )
    {
        $hasResultHandler = false !== $this->resultHandler;

        if ( self::DEBUG_CSV_VERBOSE ) clog("Using input as raw CSV data");

        $data      = $this->filepath;
        $lines     = explode("\n", $data);
        $lineCount = count($lines);
        $lineIndex = 0;

        if ( $hasHeaderLine )
        {
            $firstLineTokens = str_getcsv($lines[0]); // Assume first line has column names
            $this->columns   = self::stripBOMHeader($firstLineTokens); // Excel bullshit.
            if ( self::DEBUG_CSV_COLUMNS ) clog("columns", $this->columns);
            $visitor->parseHeaders($this->columns);
            ++$lineIndex;
        }

        for ( ; $lineIndex < $lineCount; ++$lineIndex )
        {
            $tokens = str_getcsv($lines[$lineIndex]);

            if ( self::DEBUG_CSV_VERBOSE ) clog("start", $tokens);

            $visitor->ante($lineIndex);

            //$tokens = self::stripBOMHeader($tokens); // NOTE - stripping BOM headers on EVERY LINE???
            //if ( self::DEBUG_CSV_VERBOSE ) clog("after BOM-strip", $tokens);

            if ( $this->isCommentLine($tokens) )
            {
                if ( self::DEBUG_CSV_VERBOSE ) cclog(Log::TEXT_COLOR_UL_YELLOW, "Skipping line: " . implode(",", $tokens));
                $visitor->parseComment($lineIndex, $tokens);
                $result = false;
            }
            else
            {
                $result = $visitor->parse($lineIndex, $tokens, $this->columns);
            }

            $visitor->post($lineIndex);

            if ( $hasResultHandler && false !== $result ) $this->resultHandler->handleResult($lineIndex, $result);

        }

        $visitor->finish();
    }



    /**
     * @param CSVisitor $visitor
     *
     * @throws Exception
     *
     * @see CSV::read()
     */
    public function readWithHeaderRow ( $visitor ) { $this->read($visitor, true); }
}

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



namespace vertwo\plite\Provider\Base;



use Exception;
use vertwo\plite\FJ;
use vertwo\plite\Provider\CRUDProvider;
use vertwo\plite\Provider\Exception\KeyNotExistException;
use vertwo\plite\Provider\Exception\KeyAlreadyExistsException;
use vertwo\plite\Provider\ProviderHelperJSON;
use vertwo\plite\STELAR\Ball;
use vertwo\plite\STELAR\TableDataHelper;
use vertwo\plite\Util\CSV;
use vertwo\plite\Util\CSVisitor;
use vertwo\plite\Util\CSVisitorBasic;
use function vertwo\plite\clog;



/**
 * Class CRUDProviderBase
 *
 * Implements CrudProvider for JSON-based files.
 *
 * JSON format is a map: [ id => [obj] ] where 'id' is a string,
 * and 'obj' is itself is an arbitrary JSON object, most likely
 * a map itself, with nested components.
 *
 * The object itself will likely have a field which is also the ID.
 *
 * @package FJ\Provider\Base
 */
abstract class CSVProviderBase extends CRUDProviderBase implements CSVisitor
{
    const DEBUG_VERBOSE = false;

    const ERROR_EEXISTS     = "EEXISTS";
    const ERROR_ENOTFOUND   = "ENOTFOUND";
    const ERROR_ENOFILE     = "ENOFILE";
    const ERROR_ENOFILESPEC = "ENOFILESPEC";



    private $idCols    = [];
    private $idIndexes = [];
    private $headers   = [];
    private $map       = [];



    /**
     * Do environment-specific initialization (test for readability, etc).
     *
     * @param boolean|array $params
     */
    function init ( $params = false )
    {
//        $ar = [
//            "dir"      => $localPath,
//            "bucket"   => $agentsBucket,
//            "file"     => "global/agent-list.json",
//            "id_index" => $agentsIndex,
//            "id_delim" => $agentsDelim,
//        ];

        if ( array_key_exists("id_col", $params) )
        {
            $this->idCols = [];

            $indexesString = $params['id_cols'];
            $indexes       = explode(",", $indexesString);
            foreach ( $indexes as $index )
            {
                $index          = strtolower(trim($index));
                $this->idCols[] = $index;
            }
        }
    }



    protected function mapToCSV ( $map )
    {
        $lines = [];

        $headerRow = implode(",", $this->headers);
        $lines[]   = $headerRow;

        foreach ( $map as $id => $entry )
        {
            $lineTokens = [];
            foreach ( $this->headers as $header )
            {
                $val          = $entry[$header];
                $lineTokens[] = $val;
            }
            $line    = implode(",", $lineTokens);
            $lines[] = $line;
        }

        $csv = implode("\n", $lines) . "\n";

        return $csv;
    }



    /**
     * Gets all records.
     *
     * @return mixed
     */
    function ls ()
    {
        // For CRUD provider, loadData() just returns file/blob contents.
        $raw  = $this->loadData();
        $data = $this->csvToMap($raw);

        return $data;
    }




    private function csvToMap ( $raw )
    {
        $csv = new CSV($raw);
        $csv->treatInputAsRawData();

        try
        {
            $this->map = [];
            $csv->readWithHeaderRow($this);
        }
        catch ( Exception $e )
        {
            // NOTE - This doesn't actually happen when using CSV with raw data (not file).
            clog($e);
        }

        return $this->map;
    }



    /**
     * Handle columns headers (column names).  This is called BEFORE parse().
     *
     * @param array $columns - Column headers.
     */
    function parseHeaders ( $columns )
    {
        $this->headers = $columns;

        $index = 0;
        foreach ( $columns as $col )
        {
            $col = strtolower(trim($col));
            if ( in_array($col, $this->idCols) )
            {
                $this->idIndexes[] = $index;
            }
            ++$index;
        }
    }




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
        $id    = $this->generateID($tokens);
        $entry = array_combine($columns, $tokens);

        $this->map[$id] = $entry;

        return $entry;
    }



    /**
     * @param $lineIndex
     *
     * @return mixed
     */
    function ante ( $lineIndex ) { return false; }
    /**
     * @param $lineIndex
     * @param $tokens
     *
     * @return mixed
     */
    function parseComment ( $lineIndex, $tokens ) { return false; }
    /**
     * @param $lineIndex
     *
     * @return mixed
     */
    function post ( $lineIndex ) { return false; }
    /**
     * @return mixed
     */
    function finish () { return false; }




    private function generateID ( $tokens )
    {
        $idTokens = [];
        foreach ( $this->idIndexes as $idx )
        {
            $idTokens[] = $tokens[$idx];
        }

        $id = implode("|", $idTokens);

        return $id;
    }
}

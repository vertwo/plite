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
abstract class CRUDProviderBase implements CRUDProvider
{
    const DEBUG_VERBOSE = false;

    const ERROR_EEXISTS     = "EEXISTS";
    const ERROR_ENOTFOUND   = "ENOTFOUND";
    const ERROR_ENOFILE     = "ENOFILE";
    const ERROR_ENOFILESPEC = "ENOFILESPEC";

    abstract function loadData ();
    abstract function saveData ( $data );



    /**
     * Do environment-specific initialization (test for readability, etc).
     *
     * @param boolean|array $params
     */
    function init ( $params = false ) { }



    /**
     * Gets all records.
     *
     * @return mixed
     */
    function ls ()
    {
        // For CRUD provider, loadData() just returns file/blob contents.
        $raw  = $this->loadData();
        $data = FJ::jsDecode($raw);

        return $data;
    }




    /**
     * Get single datum from specified ID.
     *
     * @param $id
     *
     * @return mixed
     * @throws KeyNotExistException
     */
    function get ( $id )
    {
        $data = $this->ls();
        if ( array_key_exists($id, $data) ) return $data[$id];

        throw new KeyNotExistException($id);
    }




    /**
     * Insert single datum.
     *
     * @param $id
     * @param $datum
     *
     * @return mixed
     *
     * @throws KeyAlreadyExistsException
     */
    function add ( $id, $datum )
    {
        $data = $this->ls();

        $ids = array_keys($data);
        if ( self::DEBUG_VERBOSE ) clog("Existing IDs", $ids);

        if ( array_key_exists($id, $data) ) throw new KeyAlreadyExistsException($id);

        $data[$id] = $datum;

        if ( self::DEBUG_VERBOSE ) clog("Saving", $data);

        $json = FJ::jsPrettyEncode($data);
        $this->saveData($json);

        if ( self::DEBUG_VERBOSE ) clog("Saved", $json);

        return [ $id, $datum ];
    }




    /**
     * Remove single datum.
     *
     * @param $id
     *
     * @return mixed
     */
    function del ( $id )
    {
        $data = $this->ls();

        $datum = $data[$id];

        unset($data[$id]);

        $json = FJ::jsPrettyEncode($data);
        $this->saveData($json);

        return $datum;
    }



    /**
     * Edit (i.e., replace) single datum.
     *
     * If editing the datum causes the ID to change, caller of edit() must
     * provide new ID.  By default, edit() assumes ID does not change, and
     * simply edits the record.  If 'newID' is provided, then the old record
     * is deleted, and a new record created.
     *
     * @param string      $existingID - Existing ID
     * @param array       $datum
     * @param string|bool $newID      - New ID (if editing changes ID)
     *
     * @return mixed
     *
     * @throws Exception
     */
    function edit ( $existingID, $datum, $newID = false )
    {
        $data = $this->ls();

        if ( !array_key_exists($existingID, $data) ) throw new Exception(self::ERROR_ENOTFOUND);

        //
        // NOTE - On the off chance that editing changes the ID,
        //  1. Remove old ID.
        //  2. Create object with new ID.
        //
        if ( $newID !== false )
        {
            unset($data[$existingID]);
            $data[$newID] = $datum;
        }
        // Otherwise, just replace old ID.
        else
        {
            $data[$existingID] = $datum;
        }

        $json = FJ::jsPrettyEncode($data);
        $this->saveData($json);

        return $datum;
    }



    /**
     * Merge (i.e., update-new-values) single datum.
     *
     * Merge differs from update in that the old record is not simple REPLACED.
     *
     * NOTE - Using Ball::merge, update fields in existing record where specified;
     *        leave existing fields alone (do not delete).
     *
     * If editing the datum causes the ID to change, caller of edit() must
     * provide new ID.  By default, edit() assumes ID does not change, and
     * simply edits the record.  If 'newID' is provided, then the old record
     * is deleted, and a new record created.
     *
     * @param string      $existingID   - Existing ID
     * @param Ball        $newBall      - New data.
     * @param string      $newBallDelim - Delimiter for data.
     * @param string|bool $newID        - New ID (if editing changes ID)
     *
     * @return Ball
     *
     * @throws Exception
     */
    function merge ( $existingID, $newBall, $newBallDelim = TableDataHelper::DELIM, $newID = false )
    {
        $data       = $this->ls();
        $mergedData = ProviderHelperJSON::merge($data, $existingID, $newBall, $newBallDelim, $newID);
        $mergedID   = false !== $newID ? $newID : $existingID;
        $json       = FJ::jsPrettyEncode($mergedData);

        $this->saveData($json);

        $mergedDatum = $mergedData[$mergedID];
        return TableDataHelper::newBall($mergedDatum);
    }
}

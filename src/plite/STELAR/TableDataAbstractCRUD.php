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



use Exception;
use vertwo\plite\Provider\CRUDProvider;
use vertwo\plite\Web\Ajax;
use function vertwo\plite\clog;
use function vertwo\plite\redlog;



/**
 * See Dashboard/Endpoints.
 *
 * Class TD_AgentList
 */
abstract class TableDataAbstractCRUD implements TableDataEditable
{
    /** @var CRUDProvider $crud */
    protected $crud;



    public function ls () { return $this->crud->ls(); }



    function getData ( $params = false )
    {
        $data = $this->ls();
        $meta = $this->getMetadata();

        $data = $this->doBeforeCulling($data);
        return $this->doAfterGet(TableDataHelper::flattenFill($data, $meta));
    }



    function doBeforeCulling ( $flatArray ) { return $flatArray; }



    function doAfterGet ( $flat ) { return $flat; }



    function getMetadata ()
    {
        $hiddenMap = defined("static::IS_HIDDEN") ? static::IS_HIDDEN : false;
        return TableDataHelper::getMetadata(static::MAP, $hiddenMap);
    }



    function isEditable () { return true; }



    function getEditMetadata ()
    {
        $md = [];

        foreach ( static::EDIT_MAP as $colName => $displayName )
        {
            $md[] = [
                "label" => $displayName,
                "name"  => $colName,
            ];
        }

        return $md;
    }
    function getPrefillMap () { return []; }



    /**
     * @param array $records
     *
     * @return array - [id => err]
     */
    function create ( $records )
    {
        $ids = [];

        foreach ( $records as $idx => $info )
        {
            $ball = TableDataHelper::fromFlat($info);

            try
            {
                /** @var Ball $ball */
                list($id, $ball) = $this->doBeforeInsert($ball);

                //$ball->dump("Info (just before CRUD.add())");

                list($storedID, $datum) =
                    $this->crud->add($id, $ball->data());

                clog("Added object - $storedID", $datum);

                $ids[$idx] = $datum;
            }
            catch ( Exception $e )
            {
                clog($e);
                redlog("Could not create() record; aborting.");

                $rawerr = $e->getMessage();
                switch ( $rawerr )
                {
                    case "EEXISTS":
                        $err = "Object [ $id ] already exists.";
                        break;

                    case "EADDFAIL":
                        $err = "Error adding object [ $id ]; please contact support.";
                        break;

                    case "ENOTDONE":
                        $err = "Required fields not filled out.";
                        break;

                    case "ERANDFAIL":
                        $err = "Cannot genereate a valid Request-ID; please contact support.";
                        break;

                    default:
                        $err = $rawerr;
                }
                $ids[$idx] = $err;
            }
        }

        return $ids;
    }



    /**
     * @param array $records
     *
     * @return array - [id => err]
     */
    function update ( $records )
    {
        $ids = [];

        //clog("UPDATE - data", $records);

        foreach ( $records as $existingID => $info )
        {
            $ball = TableDataHelper::fromFlat($info);

            try
            {
                $ball = $this->doBeforeUpdate($ball);

                $ball->dump("Updating [ $existingID ]");

                $updatedBall = $this->crud->merge($existingID, $ball, TableDataHelper::DELIM);

                $updatedID = $updatedBall->get($this->getKeyID());
                $updatedBall->dump("Updated [ $updatedID ]");

                $ids[$existingID] = $updatedBall->data();
            }
            catch ( Exception $e )
            {
                $rawerr = $e->getMessage();
                switch ( $rawerr )
                {
                    case "ENOTFOUND":
                        $err = "Object [ $existingID ] could not be found; please contact support.";
                        break;

                    default:
                        $err = $rawerr;
                }
                $ids[$existingID] = $err;
            }
        }

        return $ids;
    }



    /**
     * @param array $records
     *
     * @return array - [id => err]
     */
    function delete ( $records )
    {
        $failedIDs = [];

        clog("DELETE - data", $records);

        foreach ( $records as $existingID => $info )
        {
            $ball = TableDataHelper::fromFlat($info);

            $ball->dump("Deleting [ $existingID ]");

            $deletedID = $this->crud->del($existingID);

            clog("Deleted [ $existingID ]", $deletedID);

            if ( false === $deletedID )
            {
                $failedIDs[$existingID] = "Error deleting object [ $existingID ]; please contact CLMRS support.";
            }
        }

        return $failedIDs;
    }
}

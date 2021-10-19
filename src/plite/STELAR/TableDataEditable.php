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



interface TableDataEditable extends TableData
{
    /**
     * Gets metadata for UI.
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * Returns a list of column entries:
     *
     *   [ ["label" => displayName, "name" => colName] ]
     *
     * 'displayName' is the name of the column DISPLAYED in the UI.
     * 'colName' is the key of the object in the data.
     *
     * @return mixed
     */
    function getEditMetadata ();



    /**
     * Gets the column name (array key) holding the "ID" of the row.
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * @return mixed
     */
    function getKeyID ();



    /**
     * Returns any additional data that needs to be sent BACK to back-end
     * by front-end, which it does not have access to.  In essence, this
     * data is "pre-filled", before the edited data comes from the UI.
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * Simple map of [ key => value ], where 'key' is meaningful for
     * INSERT / UPDATE / DELETE.
     *
     * @return mixed
     */
    function getPrefillMap ();



    /**
     * Normalize data before INSERT (C).
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * PRE-create, do things like generate hashes, IDs, ctime, etc.
     *
     * @param $ball
     *
     * @return mixed
     */
    function doBeforeInsert ( $ball );



    /**
     * Normalize data before UPDATE (U).
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * PRE-update, do things like modify mtime, etc.
     *
     * @param $ball
     *
     * @return mixed
     */
    function doBeforeUpdate ( $ball );



    /**
     * INSERT.
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * @param $records
     *
     * @return mixed
     */
    function create ( $records );



    /**
     * UPDATE
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * @param $records
     *
     * @return mixed
     */
    function update ( $records );



    /**
     * DELETE
     *
     * NOTE - For data that cannot be modified, a noop impl is fine.
     *
     * @param $records
     *
     * @return mixed
     */
    function delete ( $records );
}

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



namespace vertwo\plite\Provider;



use vertwo\plite\STELAR\Ball;
use vertwo\plite\STELAR\TableDataHelper;



interface CRUDProvider extends DataViewProvider
{
    /**
     * Insert single datum.
     *
     * @param $datum
     *
     * @return array
     */
    function add ( $id, $datum );



    /**
     * Remove single datum.
     *
     * @param $id
     *
     * @return mixed
     */
    function del ( $id );



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
     */
    function edit ( $existingID, $datum, $newID = false );



    /**
     * Merge (i.e., update-new-values) single datum (into existing data set).
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
     * @param Ball        $ball         - New data.
     * @param string      $newBallDelim - Delimiter for data.
     * @param string|bool $newID        - New ID (if editing changes ID)
     *
     * @return Ball
     */
    function merge ( $existingID, $ball, $newBallDelim = TableDataHelper::DELIM, $newID = false );
}

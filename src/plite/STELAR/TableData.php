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



interface TableData
{
    /**
     * Gets the title for the data: probably used as title/header of page in UI.
     *
     * @return mixed
     */
    function getTitle ();



    /**
     * Gets the "caption" for the data: probably a small description under table.
     *
     * @return mixed
     */
    function getCaption ();



    /**
     * CRUD retrieve for API callers.
     *
     * @return mixed
     */
    function ls ();



    /**
     * SELECT (retrieve, R)
     *
     * @params array - Any parameters.  Optional.
     *
     * @return mixed
     */
    function getData ( $params = false );



    /**
     * Normalize data __JUST__ after SELECT, when ALL RAW DATA
     * is available.
     *
     * @param $flat - The flattened data ready to go to client.
     *
     * @return mixed - The flattened data with any changes.
     */
    function doBeforeCulling ( $flat );



    /**
     * Normalize data after SELECT (retrieve, R), before UI.
     *
     * @param $flat - The flattened data ready to go to client.
     *
     * @return mixed - The flattened data with any changes.
     */
    function doAfterGet ( $flat );



    /**
     * Returns a list of column entries:
     *
     *   [ ["title" => displayName, "data" => colName] ]
     *
     * 'displayName' is the name of the column DISPLAYED in the UI.
     * 'colName' is the key of the object in the data.
     *
     * @return mixed
     */
    function getMetadata ();



    /**
     * Tells the caller if this class contains editable data.
     *
     * @return boolean
     */
    function isEditable ();
}

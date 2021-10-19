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



/**
 * See Dashboard/Endpoints.
 *
 * Class TD_AgentList
 */
abstract class TableDataAbstractReadOnly implements TableData
{
    /** @var CRUDProvider $crud */
    protected $crud;



    public function ls () { return $this->crud->ls(); }



    function getData ( $params = false )
    {
        $data = $this->ls();
        $meta = $this->getMetadata();

        return $this->doAfterGet(TableDataHelper::flattenFill($data, $meta));
    }
    function doAfterGet ( $flat ) { return $flat; }



    function getMetadata ()
    {
        $md = [];

        foreach ( static::MAP as $colName => $displayName )
        {
            $md[] = [
                "title" => $displayName,
                "data"  => $colName,
            ];
        }

        return $md;
    }



    function isEditable () { return false; }
}

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



namespace vertwo\plite\integrations;



use Exception;
use vertwo\plite\FJ;
use function vertwo\plite\clog;



class TrelloIntegration
{
    private $params = false;
    private $debug  = false;

    private $boardMap            = [];
    private $boardListMap        = [];
    private $boardLabelMap       = [];
    private $boardCustomFieldMap = [];


    /**
     * Trello constructor.
     *
     * @param array $params - Contains API keys & debug.
     *
     * @throws Exception
     */
    public function __construct ( $params )
    {
        $this->params = $params;

        if ( array_key_exists("debug", $params) )
            $this->debug = $params['debug'];
    }



    /**
     * @param bool|array $params
     */
    public function init ( $params = false )
    {
        if ( false !== $params )
        {
            $this->params = $params;

            if ( array_key_exists("debug", $params) )
                $this->debug = $params['debug'];
        }

        $this->getBoards();
    }



    public function getBoards ()
    {
        $BOARDS_URL = "https://api.trello.com/1/members/me/boards";
        $params     = FJ::deepCopy($this->params);
        $boardsJSON = FJ::getAPI($BOARDS_URL, $params, $this->debug);
        $boards     = FJ::js($boardsJSON);

        //clog("Trello Boards", $boards);

        $this->boardMap = [];

        foreach ( $boards as $b )
        {
            $boardName = $b['name'];
            $boardID   = $b['id'];

            $this->boardMap[$boardName] = $boardID;

            //clog("board", $boardName);
        }

        return $this->boardMap;
    }



    public function getLabels ( $boardName )
    {
        $boardID = $this->boardMap[$boardName];

        $LABELS_URL = "https://api.trello.com/1/boards/$boardID/labels";
        $params     = FJ::deepCopy($this->params);
        $labelsJSON = FJ::getAPI($LABELS_URL, $params, $this->debug);
        $labels     = FJ::js($labelsJSON);
        $labelMap   = [];

        //clog("Labels on [$boardID]", $labels);

        foreach ( $labels as $l )
        {
            $labelName = $l['name'];
            $labelID   = $l['id'];

            $labelMap[$labelName] = $labelID;

            //clog($labelName, $l);
        }

        $this->boardLabelMap[$boardID] = $labelMap;

        return $labelMap;
    }



    public function getCustomFields ( $boardName )
    {
        $boardID = $this->boardMap[$boardName];

        $LABELS_URL = "https://api.trello.com/1/boards/$boardID/customFields";
        $params     = FJ::deepCopy($this->params);
        $labelsJSON = FJ::getAPI($LABELS_URL, $params, $this->debug);
        $labels     = FJ::js($labelsJSON);
        $labelMap   = [];

        //clog("Labels on [$boardID]", $labels);

        foreach ( $labels as $l )
        {
            $labelName = $l['name'];
            $labelID   = $l['id'];

            $labelMap[$labelName] = $labelID;

            //clog($labelName, $l);
        }

        $this->boardCustomFieldMap[$boardID] = $labelMap;

        return $labelMap;
    }



    public function getBoard ( $boardName )
    {
        $boardMap = $this->getBoards();
        $boardID  = $boardMap[$boardName];

        return $boardID;
    }



    public function getLists ( $boardName )
    {
        $boardID = $this->boardMap[$boardName];

        //
        // Get list of lists on the target board.
        //
        $LIST_URL  = "https://api.trello.com/1/boards/$boardID/lists";
        $params    = FJ::deepCopy($this->params);
        $listsJSON = FJ::getAPI($LIST_URL, $params, $this->debug);
        $lists     = FJ::js($listsJSON);
        $listMap   = [];

        clog("Lists on [$boardID]", $lists);

        foreach ( $lists as $l )
        {
            $listName = $l['name'];
            $listID   = $l['id'];

            $listMap[$listName] = $listID;

            clog($listName, $l);
        }

        $this->boardListMap[$boardName] = $listMap;

        return $listMap;
    }



    public function getList ( $boardName, $listName )
    {
        $this->getBoards();
        $listMap = $this->getLists($boardName);

        $listID = $listMap[$listName];

        return $listID;
    }



    public static function createCardParams ( $title, $desc, $lat = false, $lon = false )
    {
        $card = [
            "pos"  => "top",
            "name" => $title,
            "desc" => $desc,
        ];

        // Add GPS coords if present.
        if ( false !== $lat && false !== $lon )
        {
            $coords              = "$lat,$lon";
            $card['coordinates'] = $coords;
        }

        return $card;
    }



    public function createCard ( $boardName, $listName, $card )
    {
        $listID         = $this->getList($boardName, $listName);
        $card['idList'] = $listID;

        //
        // Create new cards on target list.
        //
        $CREATE_CARD_URL = "https://api.trello.com/1/cards";
        $params          = array_merge(FJ::deepCopy($this->params), $card);

        $cardJSON = FJ::postAPI($CREATE_CARD_URL, $params, $this->debug);
        $card     = FJ::js($cardJSON);

        clog("Created card", $card);

        return $card;
    }



    public function updateCardLabel ( $boardName, $cardID, $label )
    {
        $boardID  = $this->boardMap[$boardName];
        $labelMap = $this->boardLabelMap[$boardID];
        $labelID  = $labelMap[$label];

        $labelParams = [
            "value" => $labelID,
        ];
        $URL         = "https://api.trello.com/1/cards/$cardID/idLabels";
        $params      = array_merge(FJ::deepCopy($this->params), $labelParams);

        $json = FJ::postAPI($URL, $params, $this->debug);
        $obj  = FJ::js($json);

        clog("Adding label to card [ $cardID ]", $obj);

        return $obj;
    }



    public function updateCardCustomFields ( $boardName, $cardID, $fieldMap )
    {
        $boardID          = $this->boardMap[$boardName];
        $customFieldIDMap = $this->boardCustomFieldMap[$boardID];

        foreach ( $fieldMap as $field => $value )
        {
            $fieldID = $customFieldIDMap[$field];

            $p      = [
                "value" => [
                    "text" => $value,
                ],
            ];
            $URL    = "https://api.trello.com/1/cards/$cardID/customField/$fieldID/item";
            $params = array_merge(FJ::deepCopy($this->params), $p);

            $json = FJ::postAPI($URL, $params, $this->debug);
            $obj  = FJ::js($json);

            clog("Adding label to card [ $cardID ]", $obj);
        }
    }



    public function moveCard ( $boardName, $targetListName, $cardID )
    {
        $listMap = $this->boardListMap[$boardName];
        $listID  = $listMap[$targetListName];

        clog("CARD --> Target List ID", $listID);

        $p      = [
            "idList" => $listID,
        ];
        $URL    = "https://api.trello.com/1/cards/$cardID";
        $params = array_merge(FJ::deepCopy($this->params), $p);

        $json = FJ::callAPI("PUT", $URL, $params, $this->debug);
        $obj  = FJ::js($json);

        clog("Moving card [ $cardID ] ->", $obj);

        return $obj;

    }



    public function searchForCards ( $searchParams )
    {
        //
        // Search entire board for this card.
        //
        $SEARCH_URL   = "https://api.trello.com/1/search";
        $searchParams = array_merge(FJ::deepCopy($this->params), $searchParams);

        $json = FJ::getAPI($SEARCH_URL, $searchParams, $this->debug);
        $objs = FJ::js($json);

        //clog("  -> Raw Search Results", $objs);

        $cards = $objs['cards'];

        //clog("Matching cards", $cards);

        return $cards;
    }
}

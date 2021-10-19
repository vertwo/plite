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



use function vertwo\plite\clog;



/**
 * See Dashboard/Endpoints.
 *
 * Class TD_AgentList
 */
abstract class TableDataHelper
{
    const DEBUG_FLATTEN_FILL = false;



    const DELIM = "__";



    /**
     * @param array      $map
     * @param bool|array $hiddenMap
     *
     * @return array
     */
    public static function getMetadata ( $map, $hiddenMap = false )
    {
        $md = [];

        foreach ( $map as $colName => $displayName )
        {
            $isHidden = false !== $hiddenMap && array_key_exists($colName, $hiddenMap);

            $md[] = [
                "title"    => $displayName,
                "data"     => $colName,
                "isHidden" => $isHidden,
            ];
        }

        return $md;
    }



    public static function newBall ( $item ) { return new Ball($item, self::DELIM); }
    public static function fromFlat ( $item ) { return Ball::fromFlat($item, self::DELIM); }



    public static function flattenFill ( $data, $meta )
    {
        $flat = [];

        foreach ( $data as $key => $item )
        {
            $flat[] = self::flattenFillObject($item, $meta);
        }

        return $flat;
    }



    private static function flattenFillObject ( $item, $meta )
    {
        if ( self::DEBUG_FLATTEN_FILL ) clog("meta", $meta);

        $ar = [];

        $ball = self::newBall($item);
        $flat = $ball->flatten();

        if ( self::DEBUG_FLATTEN_FILL ) clog("flat item", $flat);

        foreach ( $meta as $idx => $entry )
        {
            $data = $entry['data'];

            $v         = array_key_exists($data, $flat) ? $flat[$data] : "";
            $ar[$data] = $v;
        }

        if ( self::DEBUG_FLATTEN_FILL ) clog("flat-filled", $ar);

        return $ar;
    }
}

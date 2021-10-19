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



namespace vertwo\plite\Provider\Database;



use vertwo\plite\FJ;
use vertwo\plite\Log;
use function vertwo\plite\clog;



class PG
{
    const DEBUG_SQL    = false;
    const DEBUG_ESCAPE = false;
    const DEBUG_SCALAR = false;
    const DEBUG_LIST   = false;
    const DEBUG_MAP    = false;

    const NULL = "NULL";
    const T    = "true";
    const F    = "false";



    protected $db      = false;
    protected $isDebug = self::DEBUG_SQL;

    /**
     * This variable is a map of maps: [ tableName => [ $col => $type ] ]
     *
     * @var array $typeMap
     */
    protected $typeMap = [];



    /**
     * PG constructor.
     *
     * @param $connString
     *
     * @throws PGException
     */
    function __construct ( $connString, $initEsc = true )
    {
        $this->db = pg_connect($connString);
        if ( false === $this->db )
        {
            throw new PGException("Cannot open Postgres database; check server and connection string.");
        }
    }



    /**
     * @throws PGException
     */
    public function initTypes ()
    {
        $sql = <<<EOF
select table_schema,
       table_name,
       ordinal_position as position,
       column_name,
       data_type,
       case when character_maximum_length is not null
            then character_maximum_length
            else numeric_precision end as max_length,
       is_nullable,
       column_default as default_value
from information_schema.columns
where table_schema not in ('information_schema', 'pg_catalog')
order by table_schema, 
         table_name,
         ordinal_position;
EOF;

        $outerMap = $this->queryMap($sql);

        foreach ( $outerMap as $map )
        {
            $table = $map['table_name'];
            $col   = $map["column_name"];
            $type  = $map["data_type"];
//            $nullable = $map["is_nullable"];
//
//            $entry = [
//                "col"  => $col,
//                "type" => $type,
//                "isN"  => $nullable,
//            ];

            if ( !array_key_exists($table, $this->typeMap) )
                $this->typeMap[$table] = [];

            $this->typeMap[$table][$col] = $type;
        }

        if ( self::DEBUG_ESCAPE ) clog("type map", $this->typeMap);
    }



    function __destruct ()
    {
        if ( false !== $this->db && !$this->db )
        {
            if ( $this->isDebug() ) clog("Closing database connection...");
            pg_close($this->db);
            $this->db = false;
        }
    }



    function setDebugging ( $is ) { $this->isDebug = $is; }
    function isDebug () { return $this->isDebug; }



    /**
     * Creates type-specific escaping.
     *
     * @param string $table - Name of table.
     * @param array  $map   - Map of values.
     */
    protected function escape ( $table, $map )
    {
        $evs     = []; // Escaped Values
        $typeMap = $this->typeMap[$table];
        foreach ( $map as $col => $v )
        {
            $type      = $typeMap[$col];
            $ev        = $this->escapeUsingType($type, $v);
            $evs[$col] = $ev;
        }
        return $evs;
    }
    private function escapeUsingType ( $type, $v )
    {
        switch ( $type )
        {
            case 'character varying':
                return (false === $v) ? '(NULL)' : pg_escape_literal($v);
            case 'boolean':
                return (true === $v) ? '(true)' : ((false === $v) ? '(false)' : '(NULL)');
            case 'jsonb':
                return (false === $v || null == $v || !$v || (is_array($v) && count($v) == 0)) ? '(NULL)' : pg_escape_literal(FJ::js($v));
            case 'timestamp without time zone':
                return !$v ? '(NULL)' : ((is_numeric($v)) ? ("'" . date('Y-m-d H:i:s', $v) . "'") : pg_escape_literal($v));
            case 'real':
                return (false === $v) ? '(NULL)' : (is_numeric($v) ? $v : '(NULL)');
            default:
                return (false === $v) ? '(NULL)' : pg_escape_literal($v);
        }
    }



    /**
     * @param $result
     *
     * @throws PGException
     */
    protected function checkResult ( $result, $sql )
    {
        if ( false === $result )
        {
            clog(Log::color(Log::TEXT_COLOR_BG_RED, "SQL failure"), $sql);
            throw new PGException(pg_last_error($this->db));
        }
    }



    protected function getColumnNames ( $result )
    {
        $columns = [];
        $count   = pg_num_fields($result);
        for ( $i = 0; $i < $count; ++$i )
        {
            $columns[$i] = pg_field_name($result, $i);
        }

        return $columns;
    }



    /**
     * Executes a query, and returns the raw result from pg_query().
     *
     * @param $sql
     *
     * @return array
     *
     * @throws PGException
     *
     * @see pg_query()
     */
    public function query ( $sql )
    {
        if ( $this->isDebug() ) clog("sql", $sql);

        $result = pg_query($this->db, $sql);

        $this->checkResult($result, $sql);

        return $result;
    }



    /**
     * Executes a query, and returns the zeroth element of the zeroth row as a scalar.
     *
     * e.g., select count(1) from users;
     *
     * @param $sql
     *
     * @return mixed
     *
     * @throws PGException
     */
    public function queryScalar ( $sql )
    {
        if ( $this->isDebug() ) clog("sql", $sql);

        $result = pg_query($this->db, $sql);

        $this->checkResult($result, $sql);

        if ( $row = pg_fetch_row($result) )
        {
            if ( self::DEBUG_SCALAR ) clog("DB row", $row);

            $val = $row[0];

            if ( self::DEBUG_SCALAR ) clog("val", $val);

            return $val;
        }

        return false;
    }



    /**
     * Executes a query, and returns the zeroth element of each row as an array.
     *
     * e.g., select first_name from users;
     *
     * @param $sql
     *
     * @return array
     *
     * @throws PGException
     */
    public function queryList ( $sql )
    {
        if ( $this->isDebug() ) clog("sql", $sql);

        $result = pg_query($this->db, $sql);

        $this->checkResult($result, $sql);

        $list = [];
        while ( $row = pg_fetch_row($result) )
        {
            if ( self::DEBUG_LIST ) clog("DB row", $row);

            $val = $row[0];

            $list[] = $val;
        }

        if ( self::DEBUG_LIST ) clog("list", $list);
        if ( self::DEBUG_LIST ) clog("count", count($list));

        return $list;
    }



    /**
     * Executes a query, and returns a map with each row as an entry: [ row[0] => row[1] ].
     *
     * e.g., select user_id, dob from users;
     *
     * @param $sql
     *
     * @return array
     *
     * @throws PGException
     */
    public function querySimpleMap ( $sql )
    {
        if ( $this->isDebug() ) clog("sql", $sql);

        $result = pg_query($this->db, $sql);

        $this->checkResult($result, $sql);

        $map = [];
        while ( $row = pg_fetch_row($result) )
        {
            if ( self::DEBUG_MAP ) clog("DB row", $row);

            $key = $row[0];
            $val = $row[1];

            $map[$key] = $val;
        }

        if ( self::DEBUG_MAP ) clog("map", $map);
        if ( self::DEBUG_MAP ) clog("count", count($map));

        return $map;
    }



    /**
     * Executes a query, and returns a map with each row as an entry: [ colName[i] => [ row[0], ..., row[n] ] ]
     *
     * e.g., select * from users;
     *
     * @return array
     *
     * @throws PGException
     */
    public function queryMap ( $sql )
    {
        if ( $this->isDebug() ) clog("sql", $sql);

        $result = pg_query($this->db, $sql);

        $this->checkResult($result, $sql);

        $colNames = $this->getColumnNames($result);
        $map      = [];

        while ( $row = pg_fetch_row($result) )
        {
            $rowmap = array_combine($colNames, $row);
            $map[]  = $rowmap;
        }
        return $map;
    }



    private function createCondClause ( $table, $map )
    {
        $where = "";
        $and   = "";

        $evs = $this->escape($table, $map);

        foreach ( $evs as $col => $ev )
        {
            $where .= $and . "($col = $ev)";
            $and   = " AND ";
        }

        return $where;
    }



    /**
     * @param $table
     * @param $condMap
     *
     * @return boolean
     *
     * @throws PGException
     */
    public function exists ( $table, $condMap )
    {
        $cond = $this->createCondClause($table, $condMap);

        $sql = "SELECT count(1) FROM $table WHERE $cond;";

        $result = $this->queryScalar($sql);

        $this->checkResult($result, $sql);

        return 0 < $result;
    }



    /**
     * DANGER - Obviously, this is non-atomic.
     *
     * NOTE - For a totally serialized ETL session, this is a non-issue.
     * DANGER - For parallelized ETL, this is a problem.
     *
     * @param $table
     * @param $array
     * @param $condMap
     *
     * @throws PGException
     */
    public function upsert ( $table, $array, $condMap )
    {
        if ( $this->exists($table, $condMap) )
        {
            $this->update($table, $array, $condMap);
        }
        else
        {
            $this->insert($table, $array);
        }
    }



    /**
     * @param string     $table            - Name of table to update.
     * @param array      $array            - Map of [ key => $value ] -- '$value's will be escaped here (if 'areValuesEscaped' is false).
     * @param boolean    $areValuesEscaped - If true, then values are already escaped, and should be used directly.
     * @param array|bool $conditions       - Map of [ value => $value ] -- '$value's will be escaped here (if 'areValuesEscaped' is false).
     *
     * @throws PGException
     */
    public function insert ( $table, $array ) // $areValuesEscaped = false )
    {
        //$needsEscaping = !$areValuesEscaped;

        $keys = array_keys($array);
        $cols = implode(", ", $keys);

        $evs = $this->escape($table, $array);

        $values = [];
        foreach ( $keys as $col )
            $values[] = $evs[$col];

//        $values = array_values($array);
//        if ( $needsEscaping )
//        {
//            $escs = [];
//            foreach ( $values as $v )
//                $escs[] = self::esc($v);
//            $values = $escs;
//        }
        $vals = implode(", ", $values);

        //
        // Building SQL.
        //
        $sql = "INSERT INTO $table ( $cols ) VALUES ( $vals );";

        if ( $this->isDebug() ) clog("SQL", $sql);

        //
        // Run query.
        //
        $this->query($sql);
    }



    /**
     * @param string     $table   - Name of table to update.
     * @param array      $array   - Map of [ key => $value ] -- '$value's will be escaped here (if 'areValuesEscaped' is false).
     * @param array|bool $condMap - Map of [ value => $value ] -- '$value's will be escaped here (if 'areValuesEscaped' is false).
     *
     * @throws PGException
     */
    public function update ( $table, $array, $condMap )
    {
        $evs = $this->escape($table, $array);

        //
        // Building SET changes.
        //
        $kvs = [];
        foreach ( $array as $k => $v )
        {
            $esc   = $evs[$k];
            $kv    = "$k = $esc";
            $kvs[] = $kv;
        }
        $sets = implode(", ", $kvs);

        //
        // Get WHERE clause.
        //
        $cond = $this->createCondClause($table, $condMap);

        //
        // Building SQL.
        //
        $sql = "UPDATE $table SET $sets WHERE $cond;";

        if ( $this->isDebug() ) clog("SQL", $sql);

        //
        // Run query.
        //
        $this->query($sql);
    }



    /**
     * @param $table
     * @param $condMap
     *
     * @throws PGException
     */
    public function delete ( $table, $condMap )
    {
        $cond = $this->createCondClause($table, $condMap);

        $sql = "DELETE FROM $table WHERE $cond;";

        $result = $this->query($sql);

        $this->checkResult($result, $sql);
    }
}

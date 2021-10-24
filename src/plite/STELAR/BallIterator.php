<?php



namespace vertwo\plite\STELAR;



class BallIterator implements \Iterator
{
    private $keys;
    private $count;
    private $idx = -1;


    /**
     * BallIterator constructor.
     *
     * @param Ball $ball
     */
    function __construct ( $ball )
    {
        $flat        = $ball->flattenNicely();
        $this->keys  = array_keys($flat);
        $this->count = count($this->keys);

        if ( 0 < $this->count )
        {
            ++$this->idx;
        }
    }


    /**
     * Return the current element
     *
     * @link  https://php.net/manual/en/iterator.current.php
     * @return mixed Can return any type.
     * @since 5.0.0
     */
    public function current ()
    {
        return $this->keys[$this->idx];
    }
    /**
     * Move forward to next element
     *
     * @link  https://php.net/manual/en/iterator.next.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function next ()
    {
        ++$this->idx;
    }
    /**
     * Return the key of the current element
     *
     * @link  https://php.net/manual/en/iterator.key.php
     * @return mixed scalar on success, or null on failure.
     * @since 5.0.0
     */
    public function key ()
    {
        return $this->keys[$this->idx];
    }
    /**
     * Checks if current position is valid
     *
     * @link  https://php.net/manual/en/iterator.valid.php
     * @return boolean The return value will be casted to boolean and then evaluated.
     * Returns true on success or false on failure.
     * @since 5.0.0
     */
    public function valid ()
    {
        return 0 <= $this->idx && $this->idx < $this->count;
    }
    /**
     * Rewind the Iterator to the first element
     *
     * @link  https://php.net/manual/en/iterator.rewind.php
     * @return void Any returned value is ignored.
     * @since 5.0.0
     */
    public function rewind ()
    {
        $this->idx = $this->count > 0 ? 0 : -1;
    }
}

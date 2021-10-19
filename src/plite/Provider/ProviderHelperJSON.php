<?php



namespace vertwo\plite\Provider;



use Exception;
use vertwo\plite\FJ;
use vertwo\plite\Provider\Exception\ProviderMergeException;
use vertwo\plite\STELAR\Ball;



class ProviderHelperJSON
{
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
     * @param array       $origData     - JSON object [ id => [record] ]
     * @param string      $existingID   - Existing ID
     * @param Ball        $newBall      - New data.
     * @param string      $newBallDelim - Delimiter for data.
     * @param string|bool $newID        - New ID (if editing changes ID)
     *
     * @return array - JSON object, MERGED [ id => [record] ]
     *
     * @throws ProviderMergeException
     */
    public static function merge ( $origData, $existingID, $newBall, $newBallDelim = Ball::DEFAULT_DELIM, $newID = false )
    {
        if ( !array_key_exists($existingID, $origData) ) throw new ProviderMergeException();

        $mergedData = FJ::deepCopy($origData);

        //
        // Gather existing record, and merge with new record.
        //
        $existing   = $mergedData[$existingID];
        $mergedBall = new Ball($existing, $newBallDelim);

        $mergedBall->mergeBall($newBall);
        $datum = $mergedBall->data();

        //
        // NOTE - On the off chance that editing changes the ID,
        //  1. Remove old ID.
        //  2. Create object with new ID.
        //

        if ( $newID !== false ) // Unset old ID, and add new ID.
        {
            unset($mergedData[$existingID]);
            $mergedData[$newID] = $datum;
        }

        else // Otherwise, just replace old ID.
        {
            $mergedData[$existingID] = $datum;
        }

        //$json = FJ::jsPrettyEncode($mergedData);
        //$this->saveData($json);
        //return $mergedBall;

        return $mergedData;
    }
}

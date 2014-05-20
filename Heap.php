<?php
/**
 * A class that extends SplHeap for sorting ip addresses with the earliest access time
 */
class MinHeap extends SplHeap {
    /**
     * We modify the abstract method compare so we can sort our
     * rankings using the values of a given array
     * Method takes 2 arrays as parameters.
     * Arrays contain [back queueID, time for next fetch]
     */
    public function compare($array1, $array2){
        $value1 = $array1['time'];
        $value2 = $array2['time'];
        if ($value1 === $value2) return 0;
        return ($value1 < $value2)? 1 : -1;
    }
}
?>
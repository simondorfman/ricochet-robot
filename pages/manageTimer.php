<?php

class GameManager{
    
    var $MAX_TIME = 0;
    var $TIMER_FILE = "files/timer.txt";

    function __construct($time){
        $this->MAX_TIME = $time;
    }

    /**
     * Aggiorna il file del tempo
     */
    function updateTimer($time){
        try{
            $fileName = $this->TIMER_FILE;
            
            $fp = fopen($fileName, 'a+') or die("-1");

            fwrite($fp, $time);
                    
            fclose($fp);
            echo "0";
        }catch(Exception $e){
            echo "-2";
        }
    }

    function setMaster
}
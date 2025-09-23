<?php
/******************************************************************************
 * Fabio Lucattini <fabio.ttini [at] gmail.com>
 ******************************************************************************
 * RicochetRobot project for MakeFair
 ******************************************************************************
 MIT License
 Copyright (c) [2017] [Fabio Lucattini]

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The above copyright notice and this permission notice shall be included in all
 copies or substantial portions of the Software.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 SOFTWARE.
 ******************************************************************************/

class GameManager{
    var $BASE_DIR      = "files/";
    var $BASE_DIR_ROOT = "../files/";
    var $BASE_JSON_DIR = "../jsonConfiguration/";
    var $IPFILE        = "ip/";
    var $ROUND_FOLDER  = "round/";

    var $TIMER_FILE  = "timer.txt";
    var $MASTER_FILE = "masterId.txt";
    var $ROUND_NUM   = "roundNumber.txt";

    var $ROBOT_JSON  = "robots.json";
    var $WINNER_FILE = "winner_result.json";
    var $DESTIN_JSON = "destination.json";
    var $OPTIMAL_JSON = "optimal.json";

    function __construct(){
        $this->createBaseDir();
    }

    /**
     * Crea le directory base se non esistono
     */
    function createBaseDir(){
        //creo la dir base in cui c'e' tutto
        if(!file_exists($this->BASE_DIR)){
            if(!mkdir($this->BASE_DIR)) return -1;            
        }
        //creo la dir degli ip
        if(!file_exists($this->BASE_DIR_ROOT.$this->IPFILE)){
            if(!mkdir($this->BASE_DIR_ROOT.$this->IPFILE)) return -1;
        }
        //creo la dir dei round
        if(!file_exists($this->BASE_DIR_ROOT.$this->ROUND_FOLDER)){
            if(!mkdir($this->BASE_DIR_ROOT.$this->ROUND_FOLDER)) return -1;
        }
    }

    /**
     * Scrivo sul file l'argomento passato
     * @param $path     il path base in cui andare a cercare il file
     * @param $fileName il file in cui scrivere
     * @param $arg      cosa scrivere nel file
     * @param $own      come scriver 'a+' 'w+'
     * @return -1 errore apertura file
     * @return -2 eccezione
     * @return 0  ok
     */
    function writeOnFile($path,$fileName, $arg, $how){
        try{            
            $fileName = $path.$fileName;
            if( !($fp = fopen($fileName, $how)) ) return -1;
            if( !fwrite($fp, $arg) ) return -1;
            fclose($fp);
            return 0;
        }catch(Exception $e){
            return -2;
        }
    }

    /**
     * Scrive su un file passato l'array CSV
     * @param $path     il path base in cui andare a cercare il file
     * @param $fileName il file in cui scrivere
     * @param $arg      array CSV di valori da scrivere
     * @param $own      come scriver 'a+' 'w+'
     * @return -1 eccezione
     * @return 0  ok 
     */
    function writeOnCSVFile($path,$fileName, $arg, $how){
        try{
            $fileName = $path.$fileName;
                    
            $fp = fopen($fileName, $how);
        
            fputcsv($fp, $arg);
        
            fclose($fp);
            return 0;
        }catch(Exception $e){
            return -1;
        }
    }

    /**
     * Leggo il file l'argomento passato
     * @param $path     il path base in cui andare a cercare il file
     * @param $fileName il file da leggere
     * @return String   contenuto
     * @return null       file non trovato/eccezione
     */
    function readFile($path,$fileName){
        try{            
            $fileName = $path.$fileName;
            if( !file_exists($fileName) ) return null;

            $fileContent = file_get_contents($fileName);
            return $fileContent;
        }catch(Exception $e){
            error_log("[readFile] $path: ".$path." $fileName: ".$fileName." - ".$e->getMessage());
            return null;
        }
    }

    function readLastLineFile($path,$fileName){
        try{
            $line = '';

            $fileName = $path.$fileName;
            if( !file_exists($fileName) ) return null;
            
            $f = fopen($fileName, 'r');
            $cursor = -1;
            
            fseek($f, $cursor, SEEK_END);
            $char = fgetc($f);
            
            /**
             * Trim trailing newline chars of the file
             */
            while ($char === "\n" || $char === "\r") {
                fseek($f, $cursor--, SEEK_END);
                $char = fgetc($f);
            }
            
            /**
             * Read until the start of file or first newline char
             */
            while ($char !== false && $char !== "\n" && $char !== "\r") {
                /**
                 * Prepend the new char
                 */
                $line = $char . $line;
                fseek($f, $cursor--, SEEK_END);
                $char = fgetc($f);
            }
            
            return $line;
        }catch(Exception $e){
            error_log("[readLastLineFile] $path: ".$path." $fileName: ".$fileName." - ".$e->getMessage());
            return null;
        }
    }

    function countLineInFile($path,$fileName){
        try{
            $fileName = $path.$fileName;
            if( !file_exists($fileName) ) return null;
            
            $linecount = 0;
            $handle = fopen($fileName, 'r');
            while(!feof($handle)){
                $line = fgets($handle);
                $linecount++;
            }
            
            fclose($handle);
            
            return $linecount;
        }catch(Exception $e){
            error_log("[countLineInFile] $path: ".$path." $fileName: ".$fileName." - ".$e->getMessage());
            return null;
        }
    }

    /**
     * Rimuove il file passato come path
     * @param $path     il path base in cui andare a cercare il file
     * @param $fileName il file da rimuovere
     * @return -1 errore apertura file
     * @return -2 eccezione
     */
    function deleteFile($BASE_DIR,$fileName){
        try{            
            $fileName = $this->BASE_DIR.$fileName;
            if( !file_exists($fileName) ) return -1;
            if( !unlink($fileName) ) return -1;
            return 0;
        }catch(Exception $e){
            error_log("[deleteFile] ".$e->getMessage());            
            return -2;
        }
    }

    /**
     * Pulisce il testo in input
     */
    function test_input($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    /**
     * Legge il numero del round a cui siamo
     * @return -1 errore
     * @return 0  se il file non esiste ed è stato creato a 0
     * @return N  il valore del round
     */
    function getRound(){
        if(!file_exists($this->BASE_DIR.$this->ROUND_NUM)){
            if( $this->writeOnFile($this->BASE_DIR,$this->ROUND_NUM,0,'w+') == 0 ){
                return 0;
            }
        }
        if( ($roundNum = $this->readFile($this->BASE_DIR,$this->ROUND_NUM)) ==null){
            if( $this->writeOnFile($this->BASE_DIR,$this->ROUND_NUM,0,'w+') == 0 ){
                return 0;
            }
            return -1;
        }
        return intval($roundNum);
    }

    /**
     * Aggiorna il valore della partita lo scrive sul file e lo ritorna
     * @return -1 errore lettura
     * @return -2 errore aggiornamento
     * @return -3 errore creazione dir round
     * @return -4 errore eccezione
     * @return N>0 ok
     */
    function newRound(){
        try{
            if( ($roundNum = $this->getRound()) >= 0 ){
                $roundNum=intval($roundNum);
                $roundNum++;

                //aggiorno il valore nel file
                if( $this->writeOnFile($this->BASE_DIR,$this->ROUND_NUM,$roundNum,'w+') != 0 )
                    return -2;

                //creo la nuova cartella
                if(!file_exists($this->BASE_DIR_ROOT.$this->ROUND_FOLDER.$roundNum)){
                    if(!mkdir($this->BASE_DIR_ROOT.$this->ROUND_FOLDER.$roundNum)) return -3;
                }
                
                return $roundNum;
            }else{
                return -1;
            }
        }catch(Exception $e){
            error_log("[newRound] ".$e->getMessage()); 
            return -4;
        }
    }

    /**
     * Restituisce il path della dir del round corrente
     * @return null errore
     * @return String ok
     */
    function getRoundPath(){
        if( ($roundNum = $this->getRound()) >= 0 ){
            $path = $this->ROUND_FOLDER.$roundNum."/";
            return $path;
        }
        return null;
    }

    /**
     * Ritorna l'IP del client
     */
    function getIP(){
        $ip = null;
        if(isset($_SERVER['HTTP_CLIENT_IP'])){
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        }else if(isset($_SERVER['HTTP_X_FORWARDE‌​D_FOR'])){
            $ip = $_SERVER['HTTP_X_FORWARDE‌​D_FOR'];
        }else{
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return $ip;        
    }

    /**
     * Aggiorna l'IP della sessione 
     */
    function updateIP($IP,$sessionID){
        if( $this->writeOnFile($this->BASE_DIR_ROOT.$this->IPFILE, $sessionID, $IP, 'w+') != 0 ) return -1;
        return 0;
    }

    function deleteFolder($folderPath){
        $cdir = scandir($folderPath); 
        foreach ($cdir as $key => $value) { 
            if (!in_array($value,array(".",".."))){ 
                if (is_dir($folderPath.$value)){ 
                    $this->deleteFolder($folderPath.$value);
                }else { 
                    unlink($folderPath."/".$value);
                }
            }
        }
        rmdir($folderPath);
    }

    /**
     * Rimuove tutti gli elementi del gioco e lo riporta alla condizione iniziale
     */
    function cleanAll(){
        try{
            $roundFolder   = $this->BASE_DIR_ROOT.$this->ROUND_FOLDER;
            $confFolder    = $this->BASE_DIR;

            //echo getcwd();

            $cdir = scandir($roundFolder); 
            foreach ($cdir as $key => $value) { 
                if (!in_array($value,array(".",".."))){ 
                    if (is_dir($roundFolder.$value)){ 
                        $this->deleteFolder($roundFolder.$value);
                    }else { 
                        unlink($roundFolder.$value);
                    }
                }
            }

            if(file_exists($confFolder.$this->ROUND_NUM)) unlink($confFolder.$this->ROUND_NUM);
            if(file_exists($confFolder.$this->TIMER_FILE)) unlink($confFolder.$this->TIMER_FILE);
            if(file_exists($confFolder.$this->MASTER_FILE)) unlink($confFolder.$this->MASTER_FILE);
            echo "0";
        }catch(Exception $e){
            echo "-1";
        }
    }

    /************************************************************************************************
     ************************************************************************************************/

    /**
     * Aggiorna il file del tempo
     * @param $time      il tempo da scrivere
     * @param $idSession l'id di chi fa la richiesta di scrittura
     * @return -1 errore 
     * @return -2 se aggiornato correttamente
     */
    function updateTimer($time,$idSession){
        if( ($localIdSession = $this->checkMaster()) == -1){
            $this->setMaster($idSession);
        }else if($localIdSession == $idSession){
            if( $this->writeOnFile($this->BASE_DIR, $this->TIMER_FILE, $time, 'w+') != 0 ) return -1;
        }else{
            return $this->readTimer();
        }        
        return -2;
    }

    /**
     * Legge il file del tempo
     * @return -1 errore 
     * @return TIME 
     */
    function readTimer(){
        $fileContent = null;
        if( !file_exists($this->BASE_DIR.$this->TIMER_FILE) ) return -1;
        if( ( $fileContent = $this->readFile($this->BASE_DIR,$this->TIMER_FILE) ) == null) return -1;
        else return $fileContent;
    }

    /**
     * Setta il primo che ha fatto partire il gioco (ovvero chi gestisce il tempo)
     * @return -1 errore 
     * @return 0  se aggiornato correttamente
     */
    function setMaster($idSession){
        if( $this->writeOnFile($this->BASE_DIR, $this->MASTER_FILE, $idSession, 'w+') != 0 ) return -1;
        return 0;
    }

    /**
     * Rimuove il file associato al master
     * @param $idSession l'id di chi fa la richiesta di scrittura
     * @return -1 errore 
     * @return 0  se aggiornato correttamente
     * @return -2 se l'id della sessione non è corretta
     */
    function removeMaster($idSession){
        if( ($localIdSession = $this->checkMaster()) == -1 ){
            $this->setMaster($idSession);
        }else if($localIdSession == $idSession){
            if( $this->deleteFile($this->BASE_DIR, $this->MASTER_FILE) != 0 ) return -1;
        }else{
            return -2;
        }        
        return 0;
    }

    /**
     * Controlla se è stato settato il master master
     * @return -1 non settato/errore lettura
     * @return idSession  l'id di chi l'ha settato
     */
    function checkMaster(){
        $fileContent = null;
        if( !file_exists($this->BASE_DIR.$this->MASTER_FILE) ) return -1;
        if( ( $fileContent = $this->readFile($this->BASE_DIR,$this->MASTER_FILE) ) == null) return -2;
        else return $fileContent;
    }

    /**
     * Aggiorna il file delle partite della squadra (gruppo)
     * @return -1 errore scrittura
     * @return -2 esiste già il file
     * @return 0 ok aggiornato correttamente
     */
    function recScore($group, $robot, $move, $numMosse, $time){
        throw new RuntimeException('Legacy recScore handler has been removed. Use the API bid endpoint.');
    }

    /**
     * Controlla se esiste un file con il nome del gruppo passato per il round corrente
     */
    function checkIfGroupNameIsValid($group){
        $fileName = $group.'.csv';

        if( ($roundPath = $this->getRoundPath()) != null){
            if(file_exists($this->BASE_DIR_ROOT.$roundPath.$fileName)) return -2; //group exists
            else return 0;
        }else{
            return -1;
        }
    }

    /**
     * 1) Copia il file robot.json attuale nella nuova confiurazione 
     * 2) aggiorna il file robot.json con il valore passato 
     */
    function writeCurrentRobotConfig($JSONVAL){
        $JSONVAL         = htmlspecialchars_decode($JSONVAL);
        $currentRound    = $this->getRound();
        $prevRound       = $currentRound-1;
        
        $prevRound       = ($prevRound>0)?$prevRound:1; //only for the first case
        $currentDirRound = $this->BASE_DIR_ROOT.$this->ROUND_FOLDER.$currentRound."/";
        $prevDirRound    = $this->BASE_DIR_ROOT.$this->ROUND_FOLDER.$prevRound."/";

        $prevDEST_FULLPATH = $this->BASE_JSON_DIR.$this->DESTIN_JSON;
        $prevOPTM_FULLPATH = $this->BASE_JSON_DIR.$this->OPTIMAL_JSON;

        $writeOut = $this->writeOnFile($this->BASE_JSON_DIR, $this->ROBOT_JSON, $JSONVAL, 'w+');
        $this->writeOnFile($currentDirRound, $this->ROBOT_JSON, $JSONVAL, 'w+'); //copio la configurazione 

        if(file_exists($this->BASE_JSON_DIR.$this->DESTIN_JSON)){ copy($prevDEST_FULLPATH, $prevDirRound.$this->DESTIN_JSON); }//copio il file destination.json del round precedente 
        if(file_exists($this->BASE_JSON_DIR.$this->OPTIMAL_JSON)){ copy($prevOPTM_FULLPATH, $prevDirRound.$this->OPTIMAL_JSON); }//copio il file destination.json del round precedente 

        //scrivo anche questo file per evitare che durante il gioco qualcuno possa scegliere quel nome
        touch($currentDirRound.$this->WINNER_FILE); 
        touch($currentDirRound.$this->DESTIN_JSON); 
        touch($currentDirRound.$this->OPTIMAL_JSON);
        return $writeOut;
    }

    function readWinner(){
        $currentRound    = $this->getRound();
        $currentDirRound = $this->BASE_DIR_ROOT.$this->ROUND_FOLDER.$currentRound."/";

        //file,tempo,mosse
        $res = array(null,null,null);

        $cdir = scandir($currentDirRound); 
        foreach ($cdir as $key => $value) { 
            if (!in_array($value,array(".",".."))){ 
                if (is_dir($currentDirRound . DIRECTORY_SEPARATOR . $value)){ } 
                else { 
                    if( ($lineNumber = $this->countLineInFile($currentDirRound,$value)) == null) return -1;
                    if( ($line       = $this->readLastLineFile($currentDirRound,$value)) == null) return -2;
                    $line       = explode(",",$line);
                    $lineNumber = intval($lineNumber)-2; // 1 lo /n finale + 1 end value

                    if($line[0]=="--END--"){ //e' una line di fine 
                        if($res[1]==null){
                            $res[0] = $value;
                            $res[1] = $line[1];
                            $res[2] = $lineNumber;
                        }else if( ($res[1]>=$line[1]) && ($res[2]>$lineNumber) ){ //se il tempo e' maggiore o uguale e il numero di mosse e' minore
                            $res[0] = $value;
                            $res[1] = $line[1];
                            $res[2] = $lineNumber;
                        }else if($res[1]>$line[1]){ //se il tempo e' minore
                            $res[0] = $value;
                            $res[1] = $line[1];
                            $res[2] = $lineNumber;
                        }
                    }
                } 
            } 
        } 
        $res = json_encode($res);
        $writeOut = $this->writeOnFile($currentDirRound, $this->WINNER_FILE, $res, 'w+');
        return $res;
    }
}
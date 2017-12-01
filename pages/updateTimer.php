<?php
session_start();
$idSession=session_id();
if(isset($_REQUEST['t'])){
    require_once("gameManager.php");
    $gm = new GameManager();
    
    $gm->updateIP($gm->getIP(),$idSession);
    $time=$gm->test_input($_REQUEST['t']);

    echo $gm->updateTimer($time,$idSession);
}else{
    echo "-1";
}
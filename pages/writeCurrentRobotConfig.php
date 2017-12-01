<?php
require_once("gameManager.php");
$gm = new GameManager();

$robot      = null;

if(isset($_REQUEST["rc"])){
    $robot    = $gm->test_input($_REQUEST["rc"]);
    echo $gm->writeCurrentRobotConfig($robot);
}else{
    echo -1;
}

<?php
require_once("gameManager.php");
$gm = new GameManager();

$group      = null;
$robot      = null;
$move       = null;
$numMosse   = null;
$time       = null;

if(isset($_REQUEST["g"],$_REQUEST["r"],$_REQUEST["m"],$_REQUEST["s"])){
    $group    = $gm->test_input($_REQUEST["g"]);    
    $robot    = $gm->test_input($_REQUEST["r"]);
    $move     = $gm->test_input($_REQUEST["m"]);
    $numMosse = $gm->test_input($_REQUEST["s"]);
}else if(isset($_REQUEST["g"],$_REQUEST["t"]) ){
    $group    = $gm->test_input($_REQUEST["g"]);    
    $time     = $gm->test_input($_REQUEST["t"]);
}

echo $gm->recScore($group, $robot, $move, $numMosse, $time);
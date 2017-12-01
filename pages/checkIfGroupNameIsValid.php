<?php
require_once("gameManager.php");
$gm = new GameManager();

$group      = null;

if(isset($_REQUEST["g"])){
    $group    = strtolower($gm->test_input($_REQUEST["g"])); 
    echo $gm->checkIfGroupNameIsValid($group);   
}else{
    echo -1;
}

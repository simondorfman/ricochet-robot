<?php
require_once("gameManager.php");
$gm = new GameManager();

echo $gm->readWinner();
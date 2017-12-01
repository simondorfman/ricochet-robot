<?php
session_start();
$idSession=session_id();
require_once("gameManager.php");

$gm = new GameManager();

echo $gm->newRound();
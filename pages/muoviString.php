<?php
//require_once('HttpRequest.php');
$str=$_REQUEST["s"];
/*
$req = new HttpRequest($str, "POST");
try {
    $req->headers["Connection"] = "close";
    $req->send();// or die("Couldn't send!");
    echo("test". $req->getResponseBody() );
} catch (HttpException $ex) {
    //echo $ex;
}     
*/

echo file_get_contents("$str"); 
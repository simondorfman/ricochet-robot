<?php
try{
    /*
    chmod("../jsonConfiguration/optimal.json", 777);
    chmod("../jsonConfiguration/destination.json", 777);
*/ 
    $command = escapeshellcmd("python main.py ../jsonConfiguration/robots.json res/finaldestinations.json ../jsonConfiguration/optimal.json ../jsonConfiguration/destination.json");
    $output = shell_exec($command);
    echo "0"; 
}catch(Exception $e){
    die("-1");
}
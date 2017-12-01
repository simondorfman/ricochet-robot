var CURRENT_ROBOT = null;

var proto           = "http://";

var robotBlue       = "192.168.1.11";
var robotRed        = "192.168.1.12";
var robotGreen      = "192.168.1.14";
var robotYellow     = "192.168.1.13";

var IP_RASP         = proto+"192.168.1.254";

function debug(msg){
    console.log(msg);
}

function error(msg){
    console.error(msg)
}

function chooseRobot(chi){
    switch(chi){
        case "blue":
            CURRENT_ROBOT = proto+robotBlue;
            break;
        case "red":
            CURRENT_ROBOT = proto+robotRed;
            break;
        case "yellow":
            CURRENT_ROBOT = proto+robotYellow;
            break;
        case "green":
            CURRENT_ROBOT = proto+robotGreen;
            break;
        default:
            CURRENT_ROBOT = null;
    }
}

function decodeMovement(move){
    var ret = "";
    switch(move){
        case 0:
            ret = "UP";
            break;
        case 1:
            ret = "DOWN";
            break;
        case 2:
            ret = "RIGHT";
            break;
        case 3:
            ret = "LEFT";
            break;
        default:
            ret = null;
    }
    return ret;
}
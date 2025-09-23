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

var jsonBaseFolder  = "jsonConfiguration/";
var wallJSON        = jsonBaseFolder+"wall.json";
var robotJSON       = jsonBaseFolder+"robots.json";
var destinationJSON = jsonBaseFolder+"destination.json";

var robM = null, jsM = null, tabM = null, over = null, fm = null, tm = null, gen = null;
var layoutRandomizer = null;

var offsetTOP = 0, offsetLEFT=0;

var row = 16, col = 16;
var cellSizeH=53, cellSizeW=53;
var tableContainer = "core";
var robotNAME = ["blue","red","yellow","green"];
var lastRobotClick = null;
var NUMERO_MOSSE=0;
var GROUP_NAME=null;

var JOLLY="jolly";

var DIREZIONI=["UP","DOWN","RIGHT","LEFT"];

var PAGES_FOLDER = "pages/";
var OPTIMAL_FOLDER = "optimalGenerator/"; 
var JSONCONFIGURATION_FOLDER = "jsonConfiguration/";
var FILES_ROUND = "files/round/";

var MINUTE = 15, LOOPID = null;
var CURRENT_TIMER = null;

var WINNER = null;

var LOCAL_SESSION_ID = null;
var I_AM_MASTER      = null;
var CORREZIONE_TIMER_UPDATE = 1;

var ROUND_NUMBER = null;

var LOOP_CHECK_RELOAD_END_GAME = null;

var SLEEP_BEFORE_NEXT_STEP = 2000;
var ROBOT_ANIMATE_MUOVI    = 1000;
 
var TMP = null;

var DEFAULT_SIMULATORE_GROUP_NAME = "simulatore_fabio";

var SIMULATION_LAST_CLICK = null, MOVE_DONE=false;

var SIMULAZIONE_INTERVAL=null, SIMULAZIONE_CHI=null;

var FOUND_OSTACOLO  = false;
var ENABLE_NEXT_STEP = null, ENABLE_NEXT_STEP_i = 0, ENABLE_NEXT_STEP_MAX = 50;


$( document ).ready(function() {
    $.ajaxSetup({ cache: false }); //per evitare il caching del file json

    jsM  = new jsonManager(row,col,wallJSON,destinationJSON);
    robM = new robotManager(tableContainer,robotJSON,robotNAME);    
    tabM = new tableManager(tableContainer,robM,robotNAME);
    over = new overlayManager();
    fm   = new fileManager();
    tm   = new timerManager();
    gen  = new generic();
    layoutRandomizer = new LayoutRandomizer(tabM, robM);
    window.layoutRandomizer = layoutRandomizer;
    robC = new robotComunication();

    jsM.generateBaseJson(tabM);

    gen.loadPage(); //azioni da fare quando si carica la pagina

    var errorParam = readParameter("error");
    if(errorParam == 1){
        $("#msgTop").html("<h4 class='erroreNome'>NOME GIA' IN USO!!</h4>"+$("#msgTop").html())
    }
});

window.onresize = function(event) {
    tabM.updateCellSize()
    tabM.updateTabHeight()
 
    $(".cella").width(tabM.cellSize+"px").height(tabM.cellSize+"px")

    for (i=0;i<this.robotNAME.length;i++){
        var RN = this.robotNAME[i];
        var robot     = $("#"+RN)
        var robotROW  = robot.data("actualrow");
        var robotCOL  = robot.data("actualcol");
        robM.robotPosition(RN,robotROW,robotCOL); 
        var rS = robM.cellSize
        robot.width(rS+"px").height(rS+"px") 
    }

    if(lastRobotClick != null){
        robM.opzioniRobot(lastRobotClick)
    }
}


function updateData(){
    $("#numeroMosse").html("<b>ROUND #:</b> "+ROUND_NUMBER+"<br><b>MOVES #:</b> "+NUMERO_MOSSE)
}

function resetGame(){
    location.reload();
}

function resetGameParam(param){
    window.location.href = window.location.href + "?"+ param;
}

function readParameter(paramName){
    var url_string = window.location.href; //window.location.href
    var url = new URL(url_string);
    var c = url.searchParams.get(paramName);
    return c;
}

function setLocalGroupName(val){
    debug("entro setlocal: "+val)
    if(val==null || val == "" ||val.length==0){ 
        error("LOCAL GROUP NAME NOT SET")                
        richiediNome();
    }else{
        GROUP_NAME = val;
        fm.checkIfGroupNameIsValid();
    }
}

function richiediNome(){
    over.createOverlayInput("<h4>INSERISCI IL NOME DEL TEAM</h4>","START",setLocalGroupName,false,true)
}

function sleep(milliseconds, callback) {
    var start = new Date().getTime();
    for (var i = 0; i < 1e7; i++) {
      if ((new Date().getTime() - start) > milliseconds){
        break;
      }
    }
    if(typeof callback  == "function"){ callback(); }
  }

  $.fn.redraw = function(){
    $(this).each(function(){
      var redraw = this.offsetHeight;
    });
  };
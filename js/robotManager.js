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

function robotManager(tableContainer,robotJSON,robotColor){
    
    this.tableContainer = tableContainer;
    this.robotJSON = robotJSON;
    this.robotColor = robotColor;
    this.cellSize = 50;
    this.pendentClick = null;

    this.prevPosX = null;
    this.prevPosY = null;
    this.prevRow  = null;
    this.prevCol  = null;
    this.lastRobot = null;
 
    this.retrieveCellInfo = function(r,c){
        //debug("[retrieveCellInfo]: "+"#r"+r+"_c"+c)
        var cell      = $("#r"+r+"_c"+c);                        
        var cellOffX  = cell.offset()["top"];
        var cellOffY  = cell.offset()["left"];
        var width     = cell.width()+5;
        var height    = cell.height()+5;
        var cellBW    = cell.css("border-width");
        var cellBG    = cell.css("background-color");
        return [cellOffX,cellOffY,width,height,cellBW,cellBG];
    }

    this.clickFunRobot=function(event){
        var name = event.data.name;
        robM.opzioniRobot(name) 
    }
    
    this.robotPosition=function(robotName, robotROW, robotCOL){
        var infoCell = this.retrieveCellInfo(robotROW,robotCOL);
        //debug(this.retrieveCellInfo(robotROW,robotCOL))
        var cellOffX  = infoCell[0];
        var cellOffY  = infoCell[1];
        var cellW     = infoCell[2];
        var cellH     = infoCell[3];
        var cellBW    = infoCell[4];
        /*
        var border = (parseInt(cellBW) * 2)
        var width  = (cellW-border)-$("#"+robotName).width();
        var height = (cellH-border)-$("#"+robotName).height();
        
        var offsetYRobot =  (cellOffY+width);
        var offsetXRobot =  (cellOffX+height);
        */
        $("#"+robotName).css({
            /* "background-color":robotName, */
            "position":"absolute",
            "left": cellOffY+"px",
            "top": cellOffX+"px"
        })
        //debug("robotPosition: [left: "+cellOffY+"px; top"+cellOffX+"px]")
    }
    this.createRobot = function(jsonRobot){
        for (i=0;i<this.robotColor.length;i++){
            var robotName = this.robotColor[i];
            var robotROW  = jsonRobot[this.robotColor[i]].row;
            var robotCOL  = jsonRobot[this.robotColor[i]].col;

            var robotSize = this.cellSize-3;

            var rob =  $('<div/>', {
                id: robotName,
                "data-robotname": robotName,
                "data-startcellrow": robotROW,
                "data-startcellcol": robotCOL,
                "data-actualrow": robotROW,
                "data-actualcol": robotCOL,
                class: "robot",
                style: "width:"+robotSize+"px; \
                        height:"+robotSize+"px; \
                        background-image: url('css/img/r"+robotName+".png'); \
                        background-repeat: no-repeat; \
                        background-size: contain; \
                        background-position: center; ",
                        /**\
                        border: 1px solid black; */
                click: function(e){ robM.opzioniRobot($(this).data("robotname")) }
            }).appendTo("#"+tableContainer); 
            
            this.robotPosition(robotName,robotROW,robotCOL);
        }        
    }

    this.checkPositionOtherRobot = function(robotID,startRow,startCol,direzione){
        var stopRow = -1;
        var stopCol = -1;

        for (i=0;i<this.robotColor.length;i++){
            var currentRobot = this.robotColor[i];
            if(currentRobot != robotID){
                var currentRow = $("#"+currentRobot).data("actualrow");
                var currentCol = $("#"+currentRobot).data("actualcol");
                /*
                *  - 0 SU
                *  - 1 GIU
                *  - 2 DESTRA
                *  - 3 SINISTRA
                */
                switch(direzione){
                    case 0:
                        //  X
                        // /\
                        // ||
                        //  *
                        if(currentCol==startCol){ //se mi sposto in alto mi interessa sapere solo se sono nella stezza colonna
                            if(startRow >= currentRow && ( stopRow == -1 || stopRow <= currentRow )) {
                                stopRow = currentRow+1;
                            }
                        }
                        stopCol = startCol;
                        break;
                    case 1:
                        //  *
                        // ||
                        // \/
                        // X
                        if(currentCol==startCol){ //se mi sposto in basso mi interessa sapere solo se sono nella stezza colonna
                            if(startRow <= currentRow && ( stopRow == -1 || stopRow >= currentRow ) ){
                                stopRow = currentRow-1;
                            }
                        }
                        stopCol = startCol;
                        break;
                    case 2:
                        // * --> X
                        if(currentRow==startRow){ //se mi sposto verso destra mi interessa sapere solo se sono nella stezza riga
                            if(startCol <= currentCol && ( stopCol == -1 || stopCol >= currentCol) ){
                                stopCol = currentCol-1;
                            }
                        }
                        stopRow = startRow;
                        break;
                    case 3: 
                        // X <-- * 
                        if(currentRow==startRow){ //se mi sposto verso sinistra mi interessa sapere solo se sono nella stezza riga
                            if(startCol >= currentCol && ( stopCol == -1 || stopCol <= currentCol ) ) {
                                stopCol = currentCol+1;
                            }
                        }
                        stopRow = startRow;
                        break;
                }
                
                //   debug(currentRobot+": ["+currentRow+","+currentCol+"] => ["+stopRow+","+stopCol+"]")
                
            }
        }
        return [stopRow,stopCol];
    }

    /**
     * @param {Integer} direzione: 
     *  - 0 SU
     *  - 1 GIU
     *  - 2 DESTRA
     *  - 3 SINISTRA
     */
    this.muovi = function(robotID,direzione){
        lastRobotClick = null;

        NUMERO_MOSSE+=1;
        updateData();
        
        var robot = $("#"+robotID);
        var actualCellRow = parseInt(robot.data("actualrow"));
        var actualCellCol = parseInt(robot.data("actualcol"));

        var robotCollision = this.checkPositionOtherRobot(robotID,actualCellRow,actualCellCol,direzione);        

        this.prevPosX = robot.offset()["top"];
        this.prevPosY = robot.offset()["left"];
        this.prevRow  = actualCellRow;
        this.prevCol  = actualCellCol;
        this.lastRobot = robotID;
        this.undoButtonUNBLOCK();

        $("#frecciaUp").remove();
        $("#frecciaDown").remove();
        $("#frecciaLeft").remove();
        $("#frecciaRight").remove();
        

        //debug("[muovi]: "+robotID+", "+direzione);

        var limit = -1;
        var actualVal = -1;
        var stopAT = -1;
        switch(direzione){
            case 0:
                //  X
                // /\
                // ||
                //  *
                stopAT = limit = 0;
                actualVal = actualCellRow;
                for(i=actualVal;i>=limit;i--){
                    var currentCell = $("#r"+i+"_c"+actualCellCol);
                    //debug("#r"+i+"_c"+actualCellCol);
                    if( currentCell.hasClass("topWall") ){ 
                        stopAT = i;
                        break; 
                    }
                    if( i<actualVal && currentCell.hasClass("bottomWall") ){ 
                        stopAT = i+1; 
                        break; 
                    }
                }
                //debug("["+stopAT+","+actualCellCol+"]: "+"TROVATA")
                if(robotCollision[0] > stopAT){
                    stopAT = robotCollision[0];
                }
                actualCellRow = stopAT;                
                //debug("* ["+actualCellRow+","+actualCellCol+"]: "+"TROVATA")                
                break; 
            case 1:
                //  *
                // ||
                // \/
                // X
                stopAT = limit = row -1;
                actualVal = actualCellRow;
                for(i=actualVal;i<limit;i++){
                    var currentCell = $("#r"+i+"_c"+actualCellCol);
                    if( i>actualVal && currentCell.hasClass("topWall") ) {
                        //debug("#r"+i+"_c"+actualCellCol+": topWall")                        
                        stopAT = i-1;
                        break;
                    }
                    if( currentCell.hasClass("bottomWall") ){ 
                        //debug("#r"+i+"_c"+actualCellCol+": bottomWall")                                                
                        stopAT = i;
                        break; 
                    }
                }
                //debug("["+stopAT+","+actualCellCol+"]: "+"TROVATA")                
                if(robotCollision[0] < stopAT && robotCollision[0]!= -1){
                    stopAT = robotCollision[0];
                }
                actualCellRow = stopAT;
                //debug("* ["+actualCellRow+","+actualCellCol+"]: "+"TROVATA")                
                break;
            case 2:
                // * --> X
                stopAT = limit = col-1;
                actualVal = actualCellCol;
                //debug("stopAT: "+stopAT)
                for(i=actualVal;i<limit;i++){
                    var currentCellNome = "#r"+actualCellRow+"_c"+i;
                    var currentCell = $(currentCellNome);
                    if( currentCell.hasClass("leftWall")  && i!=actualVal ){
                        stopAT = i-1;
                        //debug("COLLISIONE A SINISTRA i: "+i+"  i-1:"+i-1)
                        break;
                    }
                    if( currentCell.hasClass("rightWall") ){
                        stopAT = i;
                        //debug("COLLISIONE A DESTRA i: "+i+"  i-1:"+(i-1))
                        break;
                    }
                }
                //debug("stopAT: "+stopAT)
                //debug("> ["+actualCellRow+","+stopAT+"]: "+"TROVATA")                
                if(robotCollision[1] < stopAT && robotCollision[1]!= -1){
                    stopAT = robotCollision[1];
                }
                actualCellCol = stopAT;
                //debug("* ["+actualCellRow+","+actualCellCol+"]: "+"TROVATA")
                break;
            case 3:
                // X <-- *
                stopAT = limit = 0;
                actualVal = actualCellCol;
                for(i=actualVal;i>=limit;i--){
                    var currentCell = $("#r"+actualCellRow+"_c"+i);
                    if( currentCell.hasClass("leftWall")  ) {
                        //debug("#r"+actualCellRow+"_c"+i+": leftWall")
                        stopAT = i;
                        break;
                    }
                    if( currentCell.hasClass("rightWall") && i!=actualVal ){ 
                        //debug("#r"+actualCellRow+"_c"+i+": rightWall")
                        stopAT = i+1;
                        break;
                    }
                }
               // debug("["+actualCellRow+","+stopAT+"]: "+"TROVATA")                
                if(robotCollision[1] > stopAT){
                    stopAT = robotCollision[1];
                }
                actualCellCol = stopAT;
                //debug("* ["+actualCellRow+","+actualCellCol+"]: "+"TROVATA")                
                break;
        }
        actualCellRow = (actualCellRow == -1)? row-1 : actualCellRow ;
        actualCellCol = (actualCellCol == -1)? col-1 : actualCellCol ;
        var ret = [actualCellRow,actualCellCol];

        robot.data("actualrow",actualCellRow);
        robot.data("actualcol",actualCellCol);

        var infoCell = this.retrieveCellInfo(actualCellRow,actualCellCol)
        var cellOffX  = infoCell[0];
        var cellOffY  = infoCell[1];
        var cellW     = infoCell[2];
        var cellH     = infoCell[3];
        var cellBW    = infoCell[4];
        var cellcolor = infoCell[5];

        /**
         * RICONOSCIMENTO DELLA VITTORIA
         */
        var robotName = robot.data("robotname");
        var cellDestination = $("#r"+actualCellRow+"_c"+actualCellCol).data("destination");
        var vittoria = (robotName==cellDestination || cellDestination==JOLLY)? true:false;
    /*
        var border = (parseInt(cellBW) * 2)
        var width  = (cellW-border)-$("#"+robotID).width();
        var height = (cellH-border)-$("#"+robotID).height();
    
        var offsetYRobot =  (cellOffY+width);
        var offsetXRobot =  (cellOffX+height);
    */
        fm.writeStep(robotID,DIREZIONI[direzione]);

        $("#"+robotID).animate({
            "left":cellOffY+"px",
            "top":cellOffX+"px",
        },{ 
            duration: ROBOT_ANIMATE_MUOVI, 
            queue: false, 
            complete: function(){
                MOVE_DONE=true;
                if(vittoria){
                    robM.resetRobotClick();
                    fm.writeEND()
                    over.baseOverlay(0,0,false)
                    WINNER=true;
                    gen.checkReloadGame();
                }else{
                    robM.opzioniRobot(robotID)
                }

                
            }
        })

        //debug(ret)
        return ret;
    }

    /**
     * Data una cordinata di una cella ritorna la presenza di robot nelle celle vicine
     */
    this.checkIFNearRobot = function(rowPARAM,colPARAM){
        var retPosRobot = new Array(false,false,false,false);
        
        for(i=0;i<robotColor.length;i++){
            var robotName = robotColor[i];
            var tmpRobot = $("#"+robotName);
            var tmpRCurrentRow = tmpRobot.data("actualrow");
            var tmpRCurrentCol = tmpRobot.data("actualcol");
            
            //debug("rowPARAM: "+rowPARAM+" colPARAM:"+colPARAM+"  >  ("+robotName+") tmpRCurrentRow: "+tmpRCurrentRow+" tmpRCurrentCol:"+tmpRCurrentCol)
            
            if(rowPARAM>0 && !retPosRobot[0]){
                retPosRobot[0] = ( tmpRCurrentCol == colPARAM && tmpRCurrentRow == (rowPARAM-1) ) ? true : false; //top
            }
            if(!retPosRobot[1]){
                retPosRobot[1] = ( tmpRCurrentCol == colPARAM && tmpRCurrentRow == (rowPARAM+1) ) ? true : false; //bottom
            }
            if(colPARAM>0 && !retPosRobot[2]){
                retPosRobot[2] = ( tmpRCurrentCol == colPARAM-1 && tmpRCurrentRow == rowPARAM ) ? true : false; //left
            }
            if(!retPosRobot[3]){
                retPosRobot[3] = ( tmpRCurrentCol == colPARAM+1 && tmpRCurrentRow == rowPARAM ) ? true : false; //right    
            }
            //debug(retPosRobot)
        }
        return retPosRobot;
    }

    /**
     * Data una cordinata di una cella ritorna la presenza di pareti nelle celle vicine
     */
    this.checkIFNearWall = function(rowPARAM,colPARAM){
        var retPosRobot = new Array(false,false,false,false);
        
        var currentCell = $("#r"+(rowPARAM)+"_c"+(colPARAM))

        var cellTop    = (rowPARAM>0) ? $("#r"+(rowPARAM-1)+"_c"+(colPARAM)) : null;
        var cellBottom = (rowPARAM<(row-1)) ? $("#r"+(rowPARAM+1)+"_c"+(colPARAM)) : null;
        var cellLeft   = (colPARAM>0) ? $("#r"+(rowPARAM)+"_c"+(colPARAM-1)) : null;
        var cellRight  = (colPARAM<(col-1)) ? $("#r"+(rowPARAM)+"_c"+(colPARAM+1)) : null;

        retPosRobot[0] = (currentCell.hasClass("topWall"))? true : false;
        retPosRobot[1] = (currentCell.hasClass("bottomWall"))? true : false;
        retPosRobot[2] = (currentCell.hasClass("leftWall"))? true : false;
        retPosRobot[3] = (currentCell.hasClass("rightWall"))? true : false;
/*
        retPosRobot[0] = (cellTop != null && (!retPosRobot[1] && cellTop.hasClass("bottomWall")) )? true : false; //from top cell
        retPosRobot[1] = (cellBottom != null && (!retPosRobot[0] && cellBottom.hasClass("topWall")) )? true : false; //from bottom cell
        retPosRobot[3] = (cellLeft != null && (!retPosRobot[3] && cellLeft.hasClass("rightWall")) )? true : false; //from left cell
        retPosRobot[2] = (cellRight != null && (!retPosRobot[2] && cellRight.hasClass("leftWall")) )? true : false; //from right cell
*/        
        retPosRobot[0] = (cellTop       != null && (!retPosRobot[1] && cellTop.hasClass("bottomWall")) )?   true : false;  //top
        retPosRobot[1] = (cellBottom    != null && (!retPosRobot[0] && cellBottom.hasClass("topWall")) )?   true : false;  //bottom 
        retPosRobot[3] = (cellRight     != null && (!retPosRobot[2] && cellRight.hasClass("leftWall")) )?  true : false;  //right 
        retPosRobot[2] = (cellLeft      != null && (!retPosRobot[3] && cellLeft.hasClass("rightWall")) )?    true : false;  //left 
        
        return retPosRobot;
    }
    
    
    this.resetRobotClick = function(){
        if(this.pendentClick!=null){
            $("#"+this.pendentClick).click({name: this.pendentClick}, this.clickFunRobot);
            this.pendentClick = null;
        }
    }

    this.opzioniRobot = function(robotID){
        if(lastRobotClick != null){
            $("#frecciaUp").remove();
            $("#frecciaDown").remove();
            $("#frecciaLeft").remove();
            $("#frecciaRight").remove();
        } 
        
        lastRobotClick = robotID;

        this.resetRobotClick();

        var robot = $("#"+robotID);
        robot.prop('onclick',null).off('click'); //tolgo l'on-click per poter levare le frecce durante lo spostamento
        this.pendentClick = robotID;
        //debug(this.pendentClick)

        var tabella = $("#tabella");
        
        /*
        var robotW = robot.width();
        var robotH = robot.height();

        var marginTop = parseFloat(robot.css("top"))-parseFloat(tabella.css("margin-top"));
        var marginLeft = parseFloat(robot.css("left"))-parseFloat(tabella.css("margin-left"));
        var marginRight = parseFloat(robot.css("right"))-parseFloat(tabella.css("margin-right"));
        var marginBottom = parseFloat(robot.css("bottom"))-parseFloat(tabella.css("margin-bottom"));
        */

        var actualCellRow = parseInt(robot.data("actualrow"));
        var actualCellCol = parseInt(robot.data("actualcol"));

        var currentCell = $("#r"+actualCellRow+"_c"+actualCellCol);
        var currentCell_topWall = currentCell.hasClass("topWall");
        var currentCell_bottomWall = currentCell.hasClass("bottomWall");
        var currentCell_leftWall = currentCell.hasClass("leftWall");
        var currentCell_rightWall = currentCell.hasClass("rightWall");

        // debug("actualCellRow: "+actualCellRow+"  actualCellCol: "+actualCellCol);

        var nearRobot = this.checkIFNearRobot(actualCellRow,actualCellCol);
        var nearWall  = this.checkIFNearWall(actualCellRow,actualCellCol);

        /*
        debug("ROBOT ARROW TOP: "+(marginTop-robotH))
        debug("ROBOT ARROW BOTTOM: "+(marginBottom-robotH))
        debug("ROBOT ARROW RIGHT: "+(marginRight-robotW))
        debug("ROBOT ARROW LEFT: "+(marginLeft-robotW))
        */

        if(actualCellRow > 0 && !currentCell_topWall && !nearRobot[0] && !nearWall[0]){
            $('<img/>', {
                id: "frecciaUp",
                src: "css/img/freccia.png", 
                class: "frecciaRobot",
                click: function(){ robM.muovi(robotID,0) }
            }).css({
                "margin-top":"-"+(this.cellSize-5)+"px",
                "width":this.cellSize
            }).appendTo("#"+robotID); 
        }
        if(actualCellRow < (row-1) && !currentCell_bottomWall && !nearRobot[1] && !nearWall[1]){
            $('<img/>', {
                id: "frecciaDown",
                src: "css/img/freccia.png",
                class: "frecciaRobot",
                click: function(){ robM.muovi(robotID,1) }
            }).css({
                "margin-top":(this.cellSize-5)+"px",
                "width":this.cellSize,
                "-ms-transform": "rotate(180deg)", /* IE 9 */
                "-webkit-transform": "rotate(180deg)", /* Chrome, Safari, Opera */
                "transform": "rotate(180deg)"
            }).appendTo("#"+robotID); 
        }
        if(actualCellCol < (col-1) && !currentCell_rightWall && !nearRobot[3] && !nearWall[3]){
            $('<img/>', {
                id: "frecciaRight",
                src: "css/img/freccia.png",
                class: "frecciaRobot",
                click: function(){ robM.muovi(robotID,2) }
            }).css({
                "margin-top":"0px",
                "margin-left":(this.cellSize-5)+"px",
                "width":this.cellSize,
                "-ms-transform": "rotate(90deg)", /* IE 9 */
                "-webkit-transform": "rotate(90deg)", /* Chrome, Safari, Opera */
                "transform": "rotate(90deg)"
            }).appendTo("#"+robotID); 
        }
        if(actualCellCol > 0 && !currentCell_leftWall  && !nearRobot[2]  && !nearWall[2]){
            $('<img/>', {
                id: "frecciaLeft",
                src: "css/img/freccia.png",
                class: "frecciaRobot",
                click: function(){ robM.muovi(robotID,3) }
            }).css({
                "margin-top":"0px",
                "margin-left":"-"+(this.cellSize-5)+"px",
                "width":this.cellSize,
                "-ms-transform": "rotate(270deg)", /* IE 9 */
                "-webkit-transform": "rotate(270deg)", /* Chrome, Safari, Opera */
                "transform": "rotate(270deg)"
            }).appendTo("#"+robotID); 
        }

    }

    this.readJSONRobot = function(){
        var self = this;
        $.getJSON(this.robotJSON, function(json) {
                if(json!=null && json!=undefined){
                    self.createRobot(json);               
                }
            }
        ).fail(function(jqXHR, textStatus, errorThrown) { error('[readJSONRobot] request fail: ' + textStatus); });
    }

    
    this.readJSONRobotAndDestinationRound = function(roundNumber){
        var folderRound = "";
        
        if(roundNumber>0 && roundNumber!=ROUND_NUMBER){
            folderRound = FILES_ROUND+roundNumber+"/";
        }else{
            folderRound = JSONCONFIGURATION_FOLDER;
        }

        var self = this;
        var jsonRobot = folderRound+"robots.json" 
        var jsonDestination = folderRound+"destination.json" 

        $.getJSON(jsonRobot, function(json) {
                if(json!=null && json!=undefined){
                    $(".robot").remove();
                    self.createRobot(json);               
                }
            }
        ).fail(function(jqXHR, textStatus, errorThrown) { error('[readJSONRobotAndDestinationRound] request fail: ' + textStatus); });

        $.getJSON(jsonDestination, function(json) {
                if(json!=null && json!=undefined){
                    for(var keys in json){
                        var spl = keys.split(",");
                        var cell = $("#r"+spl[0]+"_c"+spl[1]);
                        cell.data("destination",json[keys])
                        $("td").css("background-color","");
                        cell.css("background-color",json[keys])
                    }
                    debug(json);
                                  
                }
            }
        ).fail(function(jqXHR, textStatus, errorThrown) { error('[readJSONRobotAndDestinationRound] request fail: ' + textStatus); });
    }

    this.undoButtonBLOCK = function(){
        $("#btnUNDO").removeClass("btnUNDO").addClass("btnUNDODisabled")
    }
    this.undoButtonUNBLOCK = function(){
        $("#btnUNDO").addClass("btnUNDO").removeClass("btnUNDODisabled")
    }

    this.undoLastMove = function(){

        var robotID = robM.lastRobot
        var offsetX = robM.prevPosX
        var offsetY = robM.prevPosY
        var lastRow = robM.prevRow
        var lastCol = robM.prevCol

        if(robotID == undefined||robotID==null) return;

        robM.undoButtonBLOCK();
        var robot = $("#"+robotID);

        $("#frecciaUp").remove();
        $("#frecciaDown").remove();
        $("#frecciaLeft").remove();
        $("#frecciaRight").remove();

        NUMERO_MOSSE -= 1;
        updateData()

        robot.animate({
            left:offsetY+"px",
            top:offsetX+"px",
        },1000,function(){
            robot.data("actualrow",lastRow);
            robot.data("actualcol",lastCol);
            robM.lastRobot = robM.prevPosX = robM.prevPosY = robM.prevRow = robM.prevCol = null;
            robM.opzioniRobot(robotID)
        })
    }

    /**
     * Ritorna il JSON della posizione attuale dei robot
     */
    this.saveCurrentRobotConfig = function(){
        var rp = new Object();
    
        for(i=0;i<robotColor.length;i++){
            var currentRobotColor   = robotColor[i];
            var currentOBJ          = new Object();
            if($("#"+currentRobotColor).length>0){
                    
                currentOBJ.row=$("#"+currentRobotColor).data("actualrow")
                currentOBJ.col=$("#"+currentRobotColor).data("actualcol")
                rp[currentRobotColor] = currentOBJ;
            }else{
                return null;
            }
        }
        return JSON.stringify(rp);
    }

    /**
     * Sostituisco file robot.json
     */
    this.writeCurrentRobotConfig = function(){
        var robotConfig = robM.saveCurrentRobotConfig();
        if(robotConfig!=null){
            $.ajax({
                type: "POST",
                url: PAGES_FOLDER+"writeCurrentRobotConfig.php",
                dataType: "text",
                data: "rc="+robotConfig,
                success: function (response) {
                    if(response<=0){ //error
                        error("errore writeCurrentRobotConfig");
                    }
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    error(xhr)
                    error(thrownError)
                }
            });    
        }    
    }
}
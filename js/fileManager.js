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


 function fileManager(){

    this.writeStep = function(robotID,moveTO){
        if(GROUP_NAME!=DEFAULT_SIMULATORE_GROUP_NAME){
            var groupNAME = GROUP_NAME;
            $.ajax({
                type: "POST",
                url: PAGES_FOLDER+"recScore.php",
                dataType: "text",
                data: "g="+groupNAME+"&r="+robotID+"&m="+moveTO+"&s="+NUMERO_MOSSE,
                success: function (response) {
                    switch(response){
                        case "0": //ok
                            break;
                        case "-1": //ERROR IN WRITING
                            break;
                        case "-2": //file alredy exists
                            over.createOverlayMSG(1,"<h2>ERROR IN WRITING!!!</h2> <br>check with our team!!!")
                            break;
                    }
                    debug(response)
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    error(xhr)
                    error(thrownError)
                }
            });
        }
    }

    this.writeEND = function(){
        var groupNAME = GROUP_NAME;
        if(GROUP_NAME!=DEFAULT_SIMULATORE_GROUP_NAME){
            var time = CURRENT_TIMER;//($("#countdown").html()) //salvo il tempo un secondi che e' meglio
            $.ajax({
                type: "POST",
                url: PAGES_FOLDER+"recScore.php",
                dataType: "text",
                data: "g="+groupNAME+"&t="+time,
                success: function (response) {
                debug(response)
                },
                error: function (xhr, ajaxOptions, thrownError) {
                    error(xhr)
                    error(thrownError)
                }
            });
        }
    }

    this.checkIfGroupNameIsValid = function(){
        var groupNAME = GROUP_NAME;
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"checkIfGroupNameIsValid.php",
            dataType: "text",
            data: "g="+groupNAME,
            success: function (response) {
                switch(response){
                    case "0": //ok
                        tm.updateTimer(MINUTE);
                        break;
                    default: //ERROR
                        resetGameParam("error=1");
                        break;
                }
                debug(response)
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
          });
    }


    this.readJSONFromGiuseppeP =  function(LOCAL_ROUND_NUMBER){
        GROUP_NAME = DEFAULT_SIMULATORE_GROUP_NAME;
        over.closeOverlay();
        if(LOCAL_ROUND_NUMBER==null||LOCAL_ROUND_NUMBER===undefined) LOCAL_ROUND_NUMBER = ROUND_NUMBER;
        var folderRound = "";

        robM.readJSONRobotAndDestinationRound(LOCAL_ROUND_NUMBER);
        
        var numRound = LOCAL_ROUND_NUMBER;
        if(numRound>0 && numRound!=ROUND_NUMBER){
            folderRound = FILES_ROUND+numRound+"/";
        }else{
            folderRound = JSONCONFIGURATION_FOLDER;
        }

        $.ajax({
            type: "POST",
            url: folderRound+"optimal.json", // JSONCONFIGURATION_FOLDER+"optimal.json",
            dataType: "text",
            success: function (response) {
                if(response==null) return;
                var jsonValue = JSON.parse(response)
                var order    = jsonValue["order"];
                var solution = jsonValue["solution"];
                var blue     = solution["blue"];
                var red      = solution["red"];
                var green    = solution["green"];
                var yellow   = solution["yellow"];

                var blueStep    = 0;
                var redStep     = 0;
                var greenStep   = 0;
                var yellowStep  = 0;
                var i           = 0;

                var arrayOrder = new Array();
                
                for(var key in order){
                    var currentOrder = order[key];
                    arrayOrder.push(currentOrder);
                }

                SIMULAZIONE_INTERVAL = setInterval(function(){
                    if(i<arrayOrder.length){

                        var movement = null;
                        currentOrder = arrayOrder[i];

                        debug("STEP: "+i);

                        if(currentOrder=="blue"){
                            movement = blue[blueStep];
                            blueStep++;
                        }else if(currentOrder=="red"){
                            movement = red[redStep];
                            redStep++;
                        }else if(currentOrder=="green"){
                            movement = green[greenStep];
                            greenStep++;
                        }else if(currentOrder=="yellow"){
                            movement = yellow[yellowStep];
                            yellowStep++;
                        }
                            
                        if(movement!=null){
                            fm.execMoveGiuseppeP(currentOrder,movement);
                        }

                        i++;
                    }else{
                        clearInterval(SIMULAZIONE_INTERVAL)
                    }
                },SLEEP_BEFORE_NEXT_STEP);

            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
          });
    }

    this.readJSONFromGiuseppeP_SIMULAZIONE_ROBOT =  function(){
        GROUP_NAME = DEFAULT_SIMULATORE_GROUP_NAME;
        over.closeOverlay();

        var folderRound = "";
        
        var numRound = ROUND_NUMBER-1;
        if(numRound>0){
            folderRound = FILES_ROUND+numRound+"/";
        }else{
            folderRound = JSONCONFIGURATION_FOLDER;
        }

        debug(folderRound)
        
        $.ajax({
            type: "POST",
            url: folderRound+"optimal.json", // JSONCONFIGURATION_FOLDER+"optimal.json",
            dataType: "text",
            success: function (response) {
                if(response==null) return;
                var jsonValue = JSON.parse(response)
                var order    = jsonValue["order"];
                var solution = jsonValue["solution"];
                var blue     = solution["blue"];
                var red      = solution["red"];
                var green    = solution["green"];
                var yellow   = solution["yellow"];

                var blueStep    = 0;
                var redStep     = 0;
                var greenStep   = 0;
                var yellowStep  = 0;
                var i           = 0;

                var arrayOrder = new Array();
                
                for(var key in order){
                    var currentOrder = order[key];
                    arrayOrder.push(currentOrder);
                }

                SIMULAZIONE_INTERVAL = setInterval(function(){
                    debug("ENABLE_NEXT_STEP: "+ENABLE_NEXT_STEP);
                    if(ENABLE_NEXT_STEP==true || ENABLE_NEXT_STEP==null){
                        ENABLE_NEXT_STEP_i = 0;
                        ENABLE_NEXT_STEP = false;
                        if(i<arrayOrder.length){

                            var movement = null;
                            currentOrder = arrayOrder[i];

                            debug("STEP: "+i);

                            if(currentOrder=="blue"){
                                movement = blue[blueStep];
                                blueStep++;
                            }else if(currentOrder=="red"){
                                movement = red[redStep];
                                redStep++;
                            }else if(currentOrder=="green"){
                                movement = green[greenStep];
                                greenStep++;
                            }else if(currentOrder=="yellow"){
                                movement = yellow[yellowStep];
                                yellowStep++;
                            }
                                
                            if(movement!=null){
                                fm.execMoveGiuseppeP_SIMULAZIONE_ROBOT(currentOrder,movement);
                            }

                            i++;
                        }else{
                            clearInterval(SIMULAZIONE_INTERVAL)
                        }
                    }else if(ENABLE_NEXT_STEP_i>=ENABLE_NEXT_STEP_MAX){
                        ENABLE_NEXT_STEP_i  = 0;
                        ENABLE_NEXT_STEP    = null;
                        clearInterval(SIMULAZIONE_INTERVAL)
                    }else{
                        ENABLE_NEXT_STEP_i++;
                    }
                },SLEEP_BEFORE_NEXT_STEP);

            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
          });
    }

    this.execMoveGiuseppeP = function(chi,movement){
        /**
         *  - 0 SU
         *  - 1 GIU
         *  - 2 DESTRA
         *  - 3 SINISTRA
         */
        /*
        if(SIMULAZIONE_CHI!=chi) {
            SIMULAZIONE_CHI=chi;
            $("#"+chi).click();
        }
        */
        switch(movement){
            case "r": //right
                debug(chi+" destra")                
                robM.muovi(chi,2)
                //$("#frecciaRight").click();
                break;
            case "l": //left
                debug(chi+" sinistra")
                robM.muovi(chi,3)
                //$("#frecciaLeft").click();
                break;
            case "t": //top
                debug(chi+" su")
                robM.muovi(chi,0)
                //$("#frecciaUp").click();
                break;
            case "b": //bottom
                debug(chi+" giu")
                robM.muovi(chi,1)
                //$("#frecciaDown").click();
                break;
        }

    }

    this.execMoveGiuseppeP_SIMULAZIONE_ROBOT = function(chi,movement){
        /**
         *  - 0 SU
         *  - 1 GIU
         *  - 2 DESTRA
         *  - 3 SINISTRA
         */
        /*
        if(SIMULAZIONE_CHI!=chi) {
            SIMULAZIONE_CHI=chi;
            $("#"+chi).click();
        }
        */
        chooseRobot(chi);
        if(CURRENT_ROBOT!=null){
            switch(movement){
                case "r": //right
                    debug(chi+" destra")                
                    frecciaComandiDX()
                    //$("#frecciaRight").click();
                    break;
                case "l": //left
                    debug(chi+" sinistra")
                    frecciaComandiSX()
                    //$("#frecciaLeft").click();
                    break;
                case "t": //top
                    debug(chi+" su")
                    frecciaComandiSu()
                    //$("#frecciaUp").click();
                    break;
                case "b": //bottom
                    debug(chi+" giu")
                    frecciaComandiGiu()
                    //$("#frecciaDown").click();
                    break;
            }
        }
    }
 }
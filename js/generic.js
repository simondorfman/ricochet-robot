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


function generic(){

    /**
     * Legge il round num e lo carica nella var ROUND_NUMBER
     */
    this.loadRound = function(){
        var that = this;
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"loadRoundNumber.php",
            dataType: "text",
            success: function (response) {
                debug("[loadRound] "+response );
                if(response<0){ //error
                    error("errore caricamento new round");
                }else if(response==0    ){ //non e' stato ancora creato un round
                    that.createNewRound(true);
                    debug("FROM 0 ROUND_NUMBER: "+ROUND_NUMBER);
                }else{
                    ROUND_NUMBER = response;
                    updateData();
                    debug("ROUND_NUMBER: "+ROUND_NUMBER);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
    }

    /**
     * Crea un nuovo round (una nuova cartella) e aggiorna il precedente valore SSE e' il vincitore 
     */
    this.createNewRound = function(writeCurrentConfig){
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"loadNewRoundNumber.php",
            dataType: "text",
            success: function (response) {
                if(response<0){ //error
                    error("errore caricamento new round");
                }else{
                    ROUND_NUMBER = response;
                    debug("ROUND_NUMBER: "+ROUND_NUMBER);
                    if(writeCurrentConfig==true){
                        robM.writeCurrentRobotConfig();
                    }
                    gen.generateOptimalFile();
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
    }

    /**
     * Esegue lo script di generazione dei fileOttimi
     */
    this.generateOptimalFile = function(){
        $.ajax({
            type: "POST",
            url: OPTIMAL_FOLDER+"exec.php",
            dataType: "text",
            success: function (response) {
                if(response=="0"){ //TUTTO OK
                    over.resetGameOverlay();                    
                }else{ //errore
                    error("errore GENERAZIONE OPTIMAL FILE");
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                /*error(xhr)
                error(thrownError)*/
            }
        });
    }

    /**
     * Legge il JSON che indica il nome del vincitore e altre info
     */
    this.readWinnerThisRound = function(){
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"readWinner.php",
            dataType: "text",
            success: function (response) {
                var obj = JSON.parse(response);
                var groupName = obj[0];
                var tempo     = obj[1];
                var numeroMos = obj[2];
                groupName = groupName.split("."); //splitto per levare il .csv
                if(groupName == GROUP_NAME){
                    gen.createNewRound(true);
                }else{
                    gen.createNewRound(false);
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
    }

    /**
     * Le azioni da fare quando finisce il round
     */
    this.todoWhenFinishRound = function(){
        debug("START todoWhenFinishRound")
        this.readWinnerThisRound(); //creo un nuovo round
    }

    this.loadPage = function(){
        this.loadRound();

        $(".btnUNDO").click({},robM.undoLastMove);
        $(".btnRESET").click({},resetGame);

        robM.undoButtonBLOCK();

        //GROUP_NAME="pppp";    
        richiediNome(); 
    }    

    this.checkReloadGame = function(){
        var that = this;
        LOOP_CHECK_RELOAD_END_GAME = setInterval(function () {
            if(CURRENT_TIMER==0){
                clearInterval(LOOP_CHECK_RELOAD_END_GAME);                
                that.todoWhenFinishRound();
            }
        }, 1000);
    }
    

    /**
     * Ripulisce tutti i file del gioco e lo riporta allo stato iniziale
     */
    this.cleanAll = function(){
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"cleanAll.php",
            dataType: "text",
            success: function (response) {
                if(response=="0"){
                    debug("[CLEAN ALL] RESET GAME DONE!")
                    over.resetGameOverlay(); 
                }else{
                    error("[CLEAN ALL] ERROR")
                    error(response)
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
    }

    /**
     * Genera Random nomi robot
     * @return {String} {"blue","red","yellow","green"} 
     */
    this.randomRobotName = function(){
        var MAX = robotNAME.length;
        var positionNum = Math.floor(Math.random() * MAX) + 1;
        return robotNAME[positionNum];
    }

    /**
     * Genera Random movimenti del robot
     * @return {Int} 0-3
     */
    this.randomRobotDirection = function(){
        var MAX = 3;
        var positionNum = Math.floor(Math.random() * MAX) ;
        return positionNum;
    }

    var iSimulation=0;
    this.runSimulation = function(MAX_SIMULATION_STEP){
        GROUP_NAME = DEFAULT_SIMULATORE_GROUP_NAME;
        over.closeOverlay();
        iSimulation=0;

        SIMULAZIONE_INTERVAL = setInterval(function(){
            if ( iSimulation < MAX_SIMULATION_STEP){
                var movement = gen.randomRobotDirection();
                var chi      = gen.randomRobotName();
                    
                if(movement!=null){                    
                    gen.execMove(chi,movement);
                }
            }else{
                debug("[SIMULATION] END");
                clearInterval(SIMULAZIONE_INTERVAL);
            }            
        },SLEEP_BEFORE_NEXT_STEP);
    }

    this.execMove = function(chi,movement){
        /**
         *  - 0 SU
         *  - 1 GIU
         *  - 2 DESTRA
         *  - 3 SINISTRA
         */
        if(chi!=null && movement!=null){
            debug(iSimulation+") ["+chi+"]: "+decodeMovement(movement))
            robM.muovi(chi,movement)
            iSimulation++;
            gen.checkSimulationState();
        }
    }

    this.checkSimulationState = function(){
        var positionRow = new Array();
        var positionCol = new Array();
        for(i=0;i<robotNAME.length;i++){
            var posRow = $("#"+robotNAME[i]).data("actualrow");
            var posCol = $("#"+robotNAME[i]).data("actualcol");
            var getEXRow = positionRow.indexOf(posRow);
            var getEXCol = positionCol.indexOf(posCol);
            if(getEXRow==-1||getEXCol==-1){
                positionRow.push(posRow)
                positionCol.push(posCol)
            }else if(getEXRow==getEXCol){ //caso in cui si sovrappongono 2 robot
                debug("[SIMULATION] END FAIL - "+robotNAME[i]+" - ROW: "+posRow+" COL: "+posCol);
                clearInterval(SIMULAZIONE_INTERVAL);
            }
        }
    }

}
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

function robotComunication(){
    var PAGINA_COMUNICA = IP_RASP+"/pages/muoviString.php"
 
    var AVANTI          = "/arduino/digital/9/1";
    var AVANTI_STOP     = "/arduino/digital/9/0";
    var BTN_AVANTI      = "muoviSu";

    var INDIETRO        = "/arduino/digital/10/1";
    var INDIETRO_STOP   = "/arduino/digital/10/0";
    var BTN_INDIETRO    = "muoviGiu";
    
    var DESTRA          = "/arduino/digital/11/1";
    var DESTRA_STOP     = "/arduino/digital/11/0";
    var BTN_DESTRA      = "muoviDX";
    
    var SINISTRA        = "/arduino/digital/12/1";
    var SINISTRA_STOP   = "/arduino/digital/12/0";
    var BTN_SINISTRA    = "muoviSX";
    
    var CHEK_SENSORE    = "/arduino/digital/13";

    var STOP            = "/arduino/digital/14/1";
    var STOP_STOP       = "/arduino/digital/14/0";
    var BTN_STOP        = "stop";

    var BTN_GIRA_DX     = "ruotaDX"
    var BTN_GIRA_SX     = "ruotaSX"
    

    var TIMER_TRA_ISTRUZIONI = 2*1000;
    var TIMER_CHECK_OSTACOLO = 800;
    var WAIT_TEMPO_IN_PIU = 3;


    var robotIntervalTimeout = null;

    var STOPCOMANDO = null;

    var lastButton        = null;
    var AGGIORNA_PULSANTI = true;

    this.setAggiornaPulsanti = function(val){
        AGGIORNA_PULSANTI = val;
    }

    this.comanda = function(chi, comando, callbackSuccess, callbackFail,setPulsante){
        if(setPulsante!=null && setPulsante.length>0 && AGGIORNA_PULSANTI == true ){
            lastButton = setPulsante;
            $("#"+setPulsante).css("background-color","orange");
        }else if(setPulsante==null && AGGIORNA_PULSANTI == true){
            $("#"+lastButton).css("background-color","");
            lastButton = null;
        }
     
        var richiesta = chi+comando;
        debug("COMANDO: "+PAGINA_COMUNICA+"?s="+richiesta);        
          
        $.ajax({
            type: "POST", 
            url: PAGINA_COMUNICA,
            dataType: "text",
            data: "s="+richiesta,
            success: function (response) {
                if(typeof callbackSuccess  == "function"){ callbackSuccess(); }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                if(typeof callbackFail  == "function"){ callbackFail(); }
                //error(xhr)
                //error(thrownError)
            }
        }); 
        
    }

    this.checkSensore = function(chi){
        var richiesta = chi+CHEK_SENSORE;
        $.ajax({
            type: "POST",
            url: PAGINA_COMUNICA,
            dataType: "text",
            data: "s="+richiesta,
            success: function (response) {               
                if(response=="1"){
                    FOUND_OSTACOLO = true;
                    ENABLE_NEXT_STEP = true;
                }else{
                    FOUND_OSTACOLO = false;
                    ENABLE_NEXT_STEP = false;
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                //error(xhr)
                //error(thrownError)
            }
        });
    }

    
    this.STOP = function(chi){
        /*
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,STOP_STOP,null,null);
            }, TIMER_TRA_ISTRUZIONI);
        }*/

        this.comanda(chi,STOPCOMANDO, null, null);
    }
    

    this.muoviAvanti = function(chi){
        FOUND_OSTACOLO = false;
        ENABLE_NEXT_STEP = false;
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                if(FOUND_OSTACOLO==true){
                    FOUND_OSTACOLO = false;                    
                    clearInterval(robotIntervalTimeout);
                    robotIntervalTimeout = null;
                    robC.comanda(chi,AVANTI_STOP,null,null,null);
                }else{
                    robC.checkSensore(chi);
                }
            },TIMER_CHECK_OSTACOLO);            
        }

        STOPCOMANDO = AVANTI_STOP;
        this.comanda(chi,AVANTI, success, null,BTN_AVANTI);
    }

    this.giraDestra = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,DESTRA_STOP,null,null,null);
            }, TIMER_TRA_ISTRUZIONI);
        }
        STOPCOMANDO = DESTRA_STOP;
        this.comanda(chi,DESTRA, success, null,BTN_GIRA_DX);
    }

    this.giraSinistra = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,SINISTRA_STOP,null,null,null);
            }, TIMER_TRA_ISTRUZIONI);
        }
        STOPCOMANDO = SINISTRA_STOP;
        this.comanda(chi,SINISTRA, success, null,BTN_GIRA_SX);
    }

    this.giraGiu = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,SINISTRA_STOP,null,null,null);
            }, TIMER_TRA_ISTRUZIONI*WAIT_TEMPO_IN_PIU);
        }
        STOPCOMANDO = SINISTRA_STOP;
        this.comanda(chi,SINISTRA, success, null,BTN_SINISTRA);
    }

    this.muoviDestra = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,DESTRA_STOP,null,null,null);
                robC.muoviAvanti(chi);
            }, TIMER_TRA_ISTRUZIONI);
        }
        STOPCOMANDO = DESTRA_STOP;
        this.comanda(chi,DESTRA, success, null,BTN_DESTRA);
    }

    this.muoviSinistra = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,SINISTRA_STOP,null,null,null);
                robC.muoviAvanti(chi);
            }, TIMER_TRA_ISTRUZIONI);
        }
        STOPCOMANDO = SINISTRA_STOP;
        this.comanda(chi,SINISTRA, success, null,BTN_SINISTRA);
    }

    this.muoviGiu = function(chi){
        clearInterval(robotIntervalTimeout);
        var success = function(){ 
            robotIntervalTimeout = setInterval(function () {
                clearInterval(robotIntervalTimeout);
                robotIntervalTimeout = null;
                robC.comanda(chi,SINISTRA_STOP,null,null,null);
                robC.muoviAvanti(chi);
            }, TIMER_TRA_ISTRUZIONI*WAIT_TEMPO_IN_PIU);
        }
        STOPCOMANDO = SINISTRA_STOP;
        this.comanda(chi,SINISTRA, success, null,BTN_SINISTRA);
    }
}

function frecciaComandiSu(){
    if(CURRENT_ROBOT!=null)  robC.muoviAvanti(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL")
}
 
function frecciaComandiGiu(){
    if(CURRENT_ROBOT!=null)  robC.muoviGiu(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL")
}

function frecciaComandiSX(){
    if(CURRENT_ROBOT!=null)  robC.muoviSinistra(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL")
}

function frecciaComandiDX(){
    if(CURRENT_ROBOT!=null)  robC.muoviDestra(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL")
}

function frecciaComandiRuotaDX(){
    if(CURRENT_ROBOT!=null)  robC.giraDestra(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL") 
}

function frecciaComandiRuotaSX(){
    if(CURRENT_ROBOT!=null)  robC.giraSinistra(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL")
}

function stopComandi(){
    if(CURRENT_ROBOT!=null)  robC.STOP(CURRENT_ROBOT); 
    else error("CURRENT_ROBOT NULL") 
}
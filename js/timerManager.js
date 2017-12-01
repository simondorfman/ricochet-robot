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

 
function timerManager(){    

    this.updateTimer = function(currentMinute){
        var that = this;
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"updateTimer.php",
            dataType: "text",
            data: "t="+currentMinute,
            success: function (response) {
                switch(response){
                    case "-2": //ok aggiornato e sono il master
                        if (LOCAL_SESSION_ID==null){
                            that.readSessionId();
                            I_AM_MASTER      = true;
                            that.loadTimer(MINUTE);     
                        }                                               
                        break;
                    case "-1": //errore aggiornamento
                        error("ERRORE AGGIORNAMENTO TIME");
                        break;
                    default: //ok non sono il master ma ho il tempo corrente
                        if (LOCAL_SESSION_ID==null){
                            I_AM_MASTER  = false;
                            if(response>=0){
                                MINUTE       = response/60; //aggiorno il tempo                                                                 
                                that.loadTimer(MINUTE);     
                            }else{
                                error("UPDATETIMER: "+response)
                            }
                        }                                      
                        break;
                }
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
    }

    this.readSessionId = function(){
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"readSessionId.php",
            dataType: "text",
            success: function (response) {
                LOCAL_SESSION_ID = response;
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
        return 0;
    }

    this.deleteMaster = function(){
        $.ajax({
            type: "POST",
            url: PAGES_FOLDER+"deleteMaster.php",
            dataType: "text",
            success: function (response) {
                console.log("DELETE MASTER: "+response)
            },
            error: function (xhr, ajaxOptions, thrownError) {
                error(xhr)
                error(thrownError)
            }
        });
        return 0;
    }

    this.loadTimer = function(minute){
        var fiveMinutes = 60 * minute,
        display = document.querySelector('#countdown');
        this.startTimer(fiveMinutes,display);
    }

    this.stopTimer = function(){
        clearInterval(LOOPID); 
    }

    this.startTimer = function(duration, display) {
        var that = this;
        var timer = duration, minutes, seconds;
        LOOPID = setInterval(function () {

            CURRENT_TIMER = timer;

            minutes = parseInt(timer / 60, 10)
            seconds = parseInt(timer % 60, 10);

            minutes = minutes < 10 ? "0" + minutes : minutes;
            seconds = seconds < 10 ? "0" + seconds : seconds;

            if(I_AM_MASTER) {
                that.updateTimer(timer-CORREZIONE_TIMER_UPDATE)
            }

            display.textContent = minutes + ":" + seconds;

            if (--timer < 0) {
                timer = duration;
                tm.stopTimer();
                if(I_AM_MASTER) that.deleteMaster();
                if(WINNER==null || WINNER==false){
                    over.baseOverlay(1,1,false);
                    WINNER = false;
                    gen.checkReloadGame();
                }
            }
        }, 1000);
    }

}
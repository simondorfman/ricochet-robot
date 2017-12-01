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


 function overlayManager(){
    this.showOverlay = function(){
        if( $("#overlay").length == 0 ) return false;
        var overlay = $("#overlay");
        overlay.removeClass("hideOverlay")
    }

    this.hideOverlay = function(){
        if( $("#overlay").length == 0 ) return false;
        var overlay = $("#overlay");
        if( overlay.hasClass("overlayNeutral") ) overlay.removeClass("overlayNeutral")        
        if( overlay.hasClass("overlayWinner") ) overlay.removeClass("overlayWinner")
        if( overlay.hasClass("overlayLoser") ) overlay.removeClass("overlayLoser") 
        overlay.addClass("hideOverlay")        
    }

    this.createOverlayMSG = function(type,msg){
        if( $("#overlay").length == 0 ) return false;
        
        var overlay = $("#overlay");
        overlay.addClass((type==0)?"overlayWinner":"overlayLoser")        
        this.showOverlay()

        $('<div/>', {
            id: "overlayPopup"
        }).appendTo(overlay)

        $('<p/>',{
            class: "msgTop"
        }).appendTo("#overlayPopup").html(msg); 
        
        $('<div/>', {
            id: "overlayPopupXButton",
            click: function(){ over.resetGameOverlay() }
        }).appendTo("#overlayPopup"); 

    }

    this.createOverlayInput = function(msg,btnLabel,callback,closeOption,specialClean){
        if( $("#overlay").length == 0 ) return false;
        
        var overlay = $("#overlay");
        overlay.addClass("overlayNeutral")         
        this.showOverlay()

        $('<div/>', {
            id: "overlayPopup"
        }).appendTo(overlay)

        $('<div/>',{
            id: "msgTop",
            class: "msgTop"
        }).appendTo("#overlayPopup").html(msg); 

        if(closeOption==true){
            $('<div/>', {
                id: "overlayPopupXButton",
                click: function(){ 
                    over.azioneInsertClose();
                }
            }).appendTo("#overlayPopup"); 
        }
        
        $('<div/>',{
            id: "certerArea",
        }).appendTo("#overlayPopup"); 

        $('<input/>', {
            id: "inputDataField",
            type: "input",
            style: "border: 1px solid black;"
        }).appendTo("#certerArea")
        .keypress(function(e) {
            if(e.which == 13) {
                over.azioneInsertClose(callback, specialClean);
            }
        }); 

        $('<div/>',{
            id: "certerArea2",
        }).appendTo("#certerArea"); 

        $('<button/>', {
            id: "startButton",
            class: "btnSTART",
            click: function(){  
                over.azioneInsertClose(callback,specialClean);
            }
        }).html(btnLabel).appendTo("#certerArea2"); 
    }

    this.azioneInsertClose = function(callback,specialClean){
        var inputBoxData=$("#inputDataField").val(); 
        if(specialClean==true){
            inputBoxData = inputBoxData.replace(" ", "_"); //clean all space char            
            inputBoxData = inputBoxData.replace(/[^a-zA-Z0-9_]/g, ""); //clean all special char     
            //console.log(inputBoxData)          
        }
        over.closeOverlay();                                 
        if(typeof callback  == "function"){ callback(inputBoxData); }
    }

    /**
     * Crea un overlay con il messaggio di vittoria o sconfitta
     */
    this.baseOverlay = function(type,who,xbutton){
        
        if( $("#overlay").length == 0 ) return false;
        if( $("#overlayPopup").length > 0 ) return false;

        var overlay = $("#overlay");
        overlay.addClass((type==0)?"overlayWinner":"overlayLoser")        
        this.showOverlay()

        //tm.stopTimer();
        var getTime = $("#countdown").html();

        var nomeGruppo = null;
        if(GROUP_NAME!=null){
            nomeGruppo = (GROUP_NAME.length > 25)?GROUP_NAME.substr(0,25)+"[...]":GROUP_NAME;
            nomeGruppo = "<h3>GROUP: "+nomeGruppo+" </h3>";            
        }else{
            nomeGruppo = "";
        }

        var msgToShow = (type==0)?"<h2>YOU ARE THE WINNER!!!</h2>":"<h2>LOOOSER!!!</h2>";
        if(who==1){ //TIMEOUT
            msgToShow="<h2>TIMEOUT!!! SORRY!!!</h2>"
        }

        $('<div/>', {
            id: "overlayPopup"
        }).appendTo(overlay)

        $('<p/>',{
            class: "msgTop"
        }).appendTo("#overlayPopup").html(msgToShow); 
        
        if(xbutton==true||xbutton==undefined){
            $('<div/>', {
                id: "overlayPopupXButton",
                click: function(){ over.resetGameOverlay() }
            }).appendTo("#overlayPopup"); 
        }

        $('<div/>', {
            id: (type==0)?"winnerGif":"loserGif"
        }).appendTo("#overlayPopup"); 
        
        var ENDMSG = (who==0||who==undefined)? nomeGruppo+"<h3>Moves #: "+NUMERO_MOSSE+" - TIME: "+getTime+"</h3>" : nomeGruppo+"<h3>Moves #: "+NUMERO_MOSSE+"</h3>";

        $('<p/>',{
            class: "msgEnd"
        }).appendTo("#overlayPopup")
        .html(ENDMSG); 
    }

    this.closeOverlay = function(){
        //resetGame()
        var overlay = $("#overlay");
        overlay.html("");
        this.hideOverlay();
    }

    this.resetGameOverlay = function(){
        resetGame()
    }
 }
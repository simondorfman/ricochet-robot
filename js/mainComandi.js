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

var robC = null;
var PREV_COLOR = null; 


$( document ).ready(function() {
    $.ajaxSetup({ cache: false }); //per evitare il caching del file json
    robC = new robotComunication();
        $("#tabComandi").height($("body").height()); 
                          
});


function setRobot(robotColor){
    if(PREV_COLOR!=null){
        $("#"+PREV_COLOR).html("");
    }
    chooseRobot(robotColor);
    if(CURRENT_ROBOT!=null){
        $("#"+robotColor).html("X");
        PREV_COLOR = robotColor;
        $("body").css("background-color",robotColor);
    }
}


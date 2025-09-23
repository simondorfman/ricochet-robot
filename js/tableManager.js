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

/**
 * Crea una tabella html leggendo i dati dal json passato come parametro
 * @param {String} divName il nome del div dove caricare la tabella if null tableGenerator() return the html of the table
 * @param {String} jsonString il json stringify con il contenuto
 */
function tableManager(tableContainer, robotOBJ){
    this.divLoad = null;
    if(tableContainer!=null && tableContainer!="") this.divLoad = tableContainer;
    this.robotOBJ = robotOBJ;

    /**
     * imposta il json con i muri + le altre celle e plotta la rappresentazione in tabella
     */
    this.setJSON = function(jsonString){ 
        try{
            this.jsonDecode = JSON.parse(jsonString);
        }catch(err){
            error(err.message);
        }
        this.tableGenerator();        
    }

    this.updateCellSize = function(){
        var pageH = $( document ).height()-offsetTOP;
        var pageW = $( document ).width()-offsetLEFT;
        var maxCellNum = (row>col)? row:col;
        this.cellSize = (pageH >= pageW)? parseInt(pageW/maxCellNum) : parseInt(pageH/maxCellNum);
        this.cellSize-=5;
        robM.cellSize = this.cellSize;
        return this.cellSize;
    }

    this.updateTabHeight = function(){
        var pageH = $( document ).height();
        var pageW = $( document ).width();
        var cellH = parseInt(pageH/row);
        var cellW = parseInt(pageW/col);
        if(cellH>(cellSizeH+3) && cellH>0 && cellSizeH>0){
            while(cellH>(cellSizeH+3) && cellH>0 && cellSizeH>0){
                pageH -= 10;
                cellH = parseInt(pageH/row);
            }
            $(".tabella").height( pageH+"px")
        }else{
            $(".tabella").height( (pageH-60) +"px")
        }
        $(".robot").height( (cellH-1) + "px" )
    }

    /**
     * create the html table 
     */
    this.tableGenerator = function(){
        var tableName = "tabella";

        var tab =  $('<table/>', {
            id: tableName,
            border: 1,
            class: "tabella"
        });

        this.updateCellSize();
        
        if(this.divLoad != null){
            $("#"+this.divLoad).html(tab);
        }else{
            $("<div/>",{
                id: "hiddenTMPDIV",
                class: "hidden"
            }).appendTo("body");
            $("#hiddenTMPDIV").html(tab);
        }
        var currentRow = null;
        var currentCell= null;

        var pageH = $( document ).height();
        var pageW = $( document ).width();
        var cellH = parseInt(pageH/row);
        var cellW = parseInt(pageW/col);

        for(r=0;r<this.jsonDecode.length;r++){
            currentRow = 'r'+r;
            $('<tr/>', {
                id: currentRow,
            }).appendTo('#'+tableName);
            
            for(c=0;c<this.jsonDecode[r].length;c++){
                currentCell = currentRow+'_c'+c;
                
                var cellOBJ = this.jsonDecode[r][c];                
                var top         = cellOBJ["top"];
                var bottom      = cellOBJ["bottom"];
                var left        = cellOBJ["left"];
                var right       = cellOBJ["right"];
                var special     = cellOBJ["special"];
                var destination = cellOBJ["destination"];

                $('<td/>', {
                    id: currentCell,
                    class: "cella",
                    width: cellW+"px",
                    /*height: cellH+"px",*/
                    "data-destination": destination
                })
                .appendTo('#'+currentRow)
                .css("text-align","center").html("["+r+","+c+"]");

                if(top    == 1) $("#"+currentCell).addClass("topWall");
                if(bottom == 1) $("#"+currentCell).addClass("bottomWall");
                if(left   == 1) $("#"+currentCell).addClass("leftWall");
                if(right  == 1) $("#"+currentCell).addClass("rightWall");
                
                if(special== 1) $("#"+currentCell).addClass("specialCenter");

                if(destination != 0){
                    if(destination==JOLLY) $("#"+currentCell).css("background-color","pink"); //for the cell with the jolly case
                    else $("#"+currentCell).css("background-color",destination);
                }
            }
        }
        
        this.updateTabHeight();

        if (window.layoutRandomizer && typeof window.layoutRandomizer.onBoardReady === "function") {
            window.layoutRandomizer.onBoardReady();
        } else {
            this.robotOBJ.readJSONRobot();
        }

        if(this.divLoad == null){
            var tmp = $("#hiddenTMPDIV").html();
            $("#hiddenTMPDIV").remove();
            return tmp;
        }else{
            return "0";
        }
       
    }
}



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
 * Gestisce il file di json della tabella per generarne di più tipi e dimensioni al volo
 * @param {Integer} rowVal numero di righe
 * @param {Integer} colVal numero di colonne
 */
function jsonManager(rowVal,colVal,wallFilename,destinationFilename){
    
        //controlli di sicurezza
        if(rowVal != 0 && rowVal != null && !isNaN(rowVal)) this.row = rowVal; else return null;
        if(colVal != 0 && colVal != null && !isNaN(colVal)) this.col = colVal; else return null;
        
        if(wallFilename!=null && wallFilename!="") this.wallFilename = wallFilename;
        if(destinationFilename!=null && destinationFilename!="") this.destinationFilename = destinationFilename;
    
        this.centerStart_row = Math.round((this.row/2) - 1) 
        this.centerEnd_row   = Math.round((this.row/2)) 
    
        this.centerStart_col = Math.round((this.col/2) - 1) 
        this.centerEnd_col   = Math.round((this.col/2)) 
        
        //DEBUG debug(this.centerStart_row+" - "+this.centerEnd_row)
        //DEBUG debug(this.centerStart_col+" - "+this.centerEnd_col)
    
        this.wall = undefined;
        this.destinat = undefined;
    
        /**
         * Genera l'intero JSON partendo dalla lettura del file json con la configurazione dei muri
         */
        this.generateBaseJson = function(tabManagerOBJ){
            var wall = this.readDestination(tabManagerOBJ);
        }

        /**
         * Genera una singola cella con parametri base (senza muri)
         */
        this.generateSingleCell = function(currentRow,currentCol,top,bottom,left,right,special,destination){
            var singleCell = '{ \
                "top" : '+top+',\
                "bottom" : '+bottom+',\
                "left" : '+left+',\
                "right" : '+right+',\
                "special" : '+special+',\
                "destination" : "'+destination+'"\
            }';
            return singleCell;
        }
    
        /**
         * restituisce un oggetto tableManager con la configurazione della tabella completa caricata
         */
        this.generateCompleteJSON = function(tabManagerOBJ){
            var output = '[';
            for (i=0;i<this.row;i++){
                output += '[';
                for(j=0;j<this.col;j++){
                    //debug(""+i+","+j+"   "+jsM.destinat[i+","+j])
                    
                    /*DEBUG
                    debug("["+i+","+j+"]: "+i+" >= "+this.centerStart_row+" && "+i+" <= "+this.centerEnd_row+": "+(i >= this.centerStart_row && i <= this.centerEnd_row)+" <> "+j+" >= "+this.centerStart_col+" && "+j+" <= "+this.centerEnd_col+": "+(j >= this.centerStart_col && j <= this.centerEnd_col))
                    */
                    //cerco il centro della matrice
                    if( (i >= this.centerStart_row && i <= this.centerEnd_row) &&
                        (j >= this.centerStart_col && j <= this.centerEnd_col) ){
                        output += this.generateSingleCell(i,j,1,1,1,1,1);
                        //DEBUG debug("["+i+","+j+"]: special");
                    }else if((this.wall[i+","+j] != undefined) && (this.destinat[i+","+j] != undefined)){
                        // LOAD DESTINATION + WALL
                        var arrDest = this.destinat[i+","+j];
                        var arrWall = this.wall[i+","+j];
                        if(arr.length == 4){
                            output += this.generateSingleCell(i,j,arrWall[0],arrWall[1],arrWall[2],arrWall[3],0,arrDest);
                        }else{
                            output += this.generateSingleCell(i,j,0,0,0,0,0,arrDest);                            
                            debug("WALL ARRAY: "+i+","+j+" WRONG FORMAT!!!");
                        }
                        //output += this.generateSingleCell(i,j,0,0,0,0,0,arrDest);
                    }else if(this.wall[i+","+j] != undefined){ 
                        // LOAD WALL
                        var arr = this.wall[i+","+j];
                        if(arr.length == 4){
                            output += this.generateSingleCell(i,j,arr[0],arr[1],arr[2],arr[3],0,0);
                        }else{
                            debug("WALL ARRAY: "+i+","+j+" WRONG FORMAT!!!");
                        }
                    }else if(jsM.destinat[i+","+j] != undefined){
                        // LOAD DESTINATION
                        var arr = this.destinat[i+","+j];
                        debug(""+i+","+j+"   "+arr)
                        output += this.generateSingleCell(i,j,0,0,0,0,0,arr);
                    }else{
                        output += this.generateSingleCell(i,j,0,0,0,0,0,0);
                    }
                    if(j<this.col-1) output+=",";
                }
                output += ']';
                if(i<this.row-1) output+=",";
            }
            output += "]";
            tabManagerOBJ.setJSON(output);
            return output;
        }
    
    
        /**
         * legge la configurazione dei muri
         */
        this.readWall = function(tabManagerOBJ){
            var self = this;
            $.getJSON(
                this.wallFilename,
                function(json) {
                    if(json!=null && json!=undefined){
                        self.wall = json;                
                    }
                    return self.generateCompleteJSON(tabManagerOBJ);
                }).fail(function(jqXHR, textStatus, errorThrown) { error('[readWall] request fail: ' + textStatus); });
        }
    
        /**
         * legge la configurazione delle caselle di destinazione
         */
        this.readDestination = function(tabManagerOBJ){
            var self = this;
            $.getJSON(
                this.destinationFilename,
                function(json) {
                    if(json!=null && json!=undefined){
                        self.destinat = json;   
                    }
                    return self.readWall(tabManagerOBJ);
                }).fail(function(jqXHR, textStatus, errorThrown) { error('[readDestination] request fail: ' + textStatus); });
        }
    }
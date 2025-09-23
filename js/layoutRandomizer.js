/******************************************************************************
 * Fabio Lucattini <fabio.ttini [at] gmail.com>
 ******************************************************************************/
 * RicochetRobot project for MakeFair
 ******************************************************************************/
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

function LayoutRandomizer(tableManager, robotManager){
    this.tableManager = tableManager;
    this.robotManager = robotManager;
    this.boardReady = false;
    this.button = $("#btnRandomize");

    this.quadrants = [
        { x: [0, 7],  y: [0, 7] },   // top left
        { x: [8, 15], y: [0, 7] },   // top right
        { x: [0, 7],  y: [8, 15] },  // bottom left
        { x: [8, 15], y: [8, 15] }   // bottom right
    ];

    this.colors = ["red", "green", "blue", "yellow"];
    this.shapes = ["Dot", "Square", "Heart", "Book"];

    this.emojiMap = {
        "Dot":    { "red": "🔴", "green": "🟢", "blue": "🔵", "yellow": "🟡" },
        "Square": { "red": "🟥", "green": "🟩", "blue": "🟦", "yellow": "🟨" },
        "Heart":  { "red": "❤️", "green": "💚", "blue": "💙", "yellow": "💛" },
        "Book":   { "red": "📕", "green": "📗", "blue": "📘", "yellow": "📙" }
    };
}

LayoutRandomizer.prototype.onBoardReady = function(){
    this.boardReady = true;
    if(this.button && this.button.length){
        this.button.prop("disabled", false);
    }
    this.randomizeLayout();
};

LayoutRandomizer.prototype.randomizeLayout = function(){
    if(!this.boardReady){
        return;
    }

    try{
        this.clearEmojiTargets();
        var occupied = new Set();
        this.placeEmojiTargets(occupied);
        var robotLayout = this.placeRobots(occupied);
        this.renderRobots(robotLayout);
        if(typeof NUMERO_MOSSE !== "undefined"){
            NUMERO_MOSSE = 0;
            if(typeof updateData === "function"){
                updateData();
            }
        }
    }catch(err){
        console.error(err);
    }
};

LayoutRandomizer.prototype.clearEmojiTargets = function(){
    $(".emoji-target").remove();
    $(".cella").removeAttr("data-emoji-color").removeAttr("data-emoji-shape");
};

LayoutRandomizer.prototype.placeEmojiTargets = function(occupied){
    var baseShapes = this.shapes.slice();
    this.shuffleArray(baseShapes);

    for(var i=0; i<this.quadrants.length; i++){
        var combos = this.buildQuadrantCombos(i, baseShapes);
        this.shuffleArray(combos);
        for(var j=0; j<combos.length; j++){
            var combo = combos[j];
            var cellData = this.randomFreeCellInQuad(this.quadrants[i], occupied);
            occupied.add(cellData.key);
            this.renderEmojiTarget(cellData.x, cellData.y, combo);
        }
    }
};

LayoutRandomizer.prototype.placeRobots = function(occupied){
    var robotNames = this.robotManager.robotColor.slice();
    this.shuffleArray(robotNames);
    var layout = {};

    for(var i=0; i<this.quadrants.length && i<robotNames.length; i++){
        var cellData = this.randomFreeCellInQuad(this.quadrants[i], occupied);
        occupied.add(cellData.key);
        layout[robotNames[i]] = { row: cellData.y, col: cellData.x };
    }

    if(Object.keys(layout).length !== this.quadrants.length){
        throw new Error("Unable to place all robots in distinct quadrants.");
    }

    return layout;
};

LayoutRandomizer.prototype.renderRobots = function(robotLayout){
    $(".robot").remove();
    if(typeof this.robotManager.undoButtonBLOCK === "function"){
        this.robotManager.undoButtonBLOCK();
    }
    if(typeof this.robotManager.pendentClick !== "undefined"){
        this.robotManager.pendentClick = null;
    }
    this.robotManager.prevPosX = null;
    this.robotManager.prevPosY = null;
    this.robotManager.prevRow  = null;
    this.robotManager.prevCol  = null;
    this.robotManager.lastRobot = null;
    if(typeof lastRobotClick !== "undefined"){
        lastRobotClick = null;
    }
    this.robotManager.createRobot(robotLayout);
};

LayoutRandomizer.prototype.renderEmojiTarget = function(x, y, combo){
    var cell = this.getCell(x, y);
    if(!cell || !cell.length){
        throw new Error("Invalid cell for emoji target: [" + y + "," + x + "]");
    }
    var emoji = this.getEmojiSymbol(combo.color, combo.shape);
    cell.attr("data-emoji-color", combo.color);
    cell.attr("data-emoji-shape", combo.shape);
    cell.append($("<span/>", {
        class: "emoji-target",
        text: emoji
    }));
};

LayoutRandomizer.prototype.getCell = function(x, y){
    return $("#r" + y + "_c" + x);
};

LayoutRandomizer.prototype.getEmojiSymbol = function(color, shape){
    if(this.emojiMap[shape] && this.emojiMap[shape][color]){
        return this.emojiMap[shape][color];
    }
    throw new Error("Missing emoji for " + color + " " + shape);
};

LayoutRandomizer.prototype.buildQuadrantCombos = function(quadrantIndex, baseShapes){
    var rotatedShapes = this.rotateShapes(quadrantIndex, baseShapes);
    var combos = [];
    for(var i=0; i<this.colors.length; i++){
        combos.push({
            color: this.colors[i],
            shape: rotatedShapes[i]
        });
    }
    return combos;
};

LayoutRandomizer.prototype.rotateShapes = function(offset, baseShapes){
    var rotation = offset % baseShapes.length;
    var first = baseShapes.slice(rotation);
    var second = baseShapes.slice(0, rotation);
    return first.concat(second);
};

LayoutRandomizer.prototype.randomFreeCellInQuad = function(quad, occupied){
    var xRange = quad.x;
    var yRange = quad.y;
    for(var tries=0; tries<200; tries++){
        var x = this.randInt(xRange[0], xRange[1]);
        var y = this.randInt(yRange[0], yRange[1]);
        var key = x + "," + y;
        if(!this.isBlocked(x, y) && !occupied.has(key)){
            return { x: x, y: y, key: key };
        }
    }
    throw new Error("No free cell in quadrant (check board/wall density).");
};

LayoutRandomizer.prototype.isBlocked = function(x, y){
    var cell = this.getCell(x, y);
    if(!cell || !cell.length){
        return true;
    }
    return cell.hasClass("specialCenter");
};

LayoutRandomizer.prototype.randInt = function(min, max){
    return Math.floor(Math.random() * (max - min + 1)) + min;
};

LayoutRandomizer.prototype.shuffleArray = function(arr){
    for(var i=arr.length - 1; i>0; i--){
        var j = Math.floor(Math.random() * (i + 1));
        var tmp = arr[i];
        arr[i] = arr[j];
        arr[j] = tmp;
    }
};

function randomizeLayout(){
    if(window.layoutRandomizer && typeof window.layoutRandomizer.randomizeLayout === "function"){
        window.layoutRandomizer.randomizeLayout();
    }
}

window.randomizeLayout = randomizeLayout;


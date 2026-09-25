const fs = require('fs'), vm = require('vm'), assert = require('assert');
const code = fs.readFileSync(require('path').join(__dirname, '../acf/sp-interactive-map/assets/map-module.js'),'utf8').split('// Prefer above')[1].split('\n').slice(1).join('\n').split('onDomReady(() =>')[0];
const context = {}; vm.createContext(context);vm.runInContext(code,context);
const bounds={left:12,top:12,right:788,bottom:588};
const rect=(left,top)=>({left,top,right:left+20,bottom:top+20,width:20,height:20});
assert.equal(context.placeTooltip(rect(390,300),200,100,bounds).side,'top');
assert.equal(context.placeTooltip(rect(390,12),200,100,bounds).side,'bottom');
assert.equal(context.placeTooltip(rect(12,250),200,400,bounds).side,'right');
for(const x of [0,12,390,770,790]) for(const y of [0,12,300,570,590]) {
 const p=context.placeTooltip(rect(x,y),280,200,bounds);
 assert(p.x>=12 && p.x+280<=788 && p.y>=12 && p.y+200<=588);
}
console.log('PASS top/bottom/side selection and viewport containment at all edges');

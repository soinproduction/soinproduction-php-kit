const fs = require('fs');
const vm = require('vm');
const assert = require('assert');
function element() { return {listeners: {}, style:{setProperty(k,v){this[k]=v;}}, dataset:{}, classList:{toggle(){},add(){},remove(){}}, addEventListener(n,f){this.listeners[n]=f;}}; }
function mapMock(){
 const plus=element(), minus=element(), controls=element(), viewport=element(), layer=element(), map=element();
 viewport.clientWidth=448; viewport.clientHeight=248; layer.clientWidth=400; layer.clientHeight=200; viewport.setPointerCapture=()=>{};
 controls.querySelector=s=>s.includes('-in]')?plus:minus;
 const parts={'[data-map-zoom-viewport]':viewport,'[data-map-zoom-layer]':layer,'[data-map-zoom-controls]':controls};
 map.querySelector=s=>parts[s]; return {map,plus,minus,viewport,layer};
}
const a=mapMock(),b=mapMock();
vm.runInNewContext(fs.readFileSync(require('path').join(__dirname, '../acf/sp-interactive-map/assets/map-module.js'),'utf8').replace(/^import .*;\n/,'').replace('export function','function'),{window:{matchMedia:()=>({matches:true})},performance:{now:()=>0},cancelAnimationFrame(){},requestAnimationFrame(){},onDomReady:f=>f(),document:{readyState:'complete',querySelectorAll:s=>s==='[data-map-zoom]'?[a.map,b.map]:[]},ResizeObserver:class {constructor(f){} observe(){}}});
for(let i=0;i<8;i++)a.plus.listeners.click();
assert(a.plus.disabled); assert(a.layer.style.transform.endsWith('scale(3)')); assert(b.layer.style.transform.endsWith('scale(1)'));
a.viewport.listeners.pointerdown({button:0,pointerId:1,clientX:0,clientY:0,target:{closest:()=>null}});
a.viewport.listeners.pointermove({pointerId:1,clientX:10000,clientY:10000});
assert.equal(a.layer.style.transform,'translate(376px, 176px) scale(3)');
a.viewport.listeners.pointercancel();
for(let i=0;i<8;i++)a.minus.listeners.click();
assert(a.minus.disabled);assert.equal(a.layer.style.transform,'translate(0px, 0px) scale(1)');
console.log('PASS zoom limits, independent maps, pan bounds, cancel and reset');

a.viewport.getBoundingClientRect=()=>({left:0,top:0,width:448,height:248});
let prevented=false;
a.viewport.listeners.wheel({ctrlKey:false,preventDefault(){prevented=true;}});
assert.equal(prevented,false);
a.viewport.listeners.wheel({ctrlKey:true,deltaMode:0,deltaY:-Math.log(1.5)*100,clientX:244,clientY:124,preventDefault(){prevented=true;}});
assert(prevented);assert(a.layer.style.transform.endsWith('scale(1.5)'));
assert(a.layer.style.transform.startsWith('translate(-10px, 0px)'));
a.viewport.listeners.gesturestart({preventDefault(){}});
a.viewport.listeners.gesturechange({scale:2,clientX:224,clientY:124,preventDefault(){}});
assert(a.layer.style.transform.endsWith('scale(3)'));
a.viewport.listeners.gestureend({preventDefault(){}});
assert(b.layer.style.transform.endsWith('scale(1)'));
console.log('PASS ordinary scroll untouched, pinch anchor, Safari gestures and map isolation');

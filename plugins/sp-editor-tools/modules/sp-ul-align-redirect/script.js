(function(){
    'use strict';
    tinymce.PluginManager.add('ul_align_redirect',function(editor){
        var CMD2ALIGN={JustifyLeft:'left',JustifyCenter:'center',JustifyRight:'right',JustifyFull:'justify'};
        function hasUserClass(el){
            if(!el||!el.className)return!1;
            var tokens=String(el.className).trim().split(/\s+/).filter(Boolean);
            for(var i=0;i<tokens.length;i++){
                if(!/^mce-/.test(tokens[i]))return!0;
            }
            return!1;
        }
        function isAlignableList(el){
            if(!el||el.nodeType!==1)return!1;
            var tag=(el.nodeName||'').toLowerCase();
            if(tag!=='ul'&&tag!=='ol')return!1;
            if(el.getAttribute&&el.getAttribute('data-field')==='root')return!0;
            if(hasUserClass(el))return!0;
            return!1;
        }
        function closestListRoot(node){
            var el=(node&&node.nodeType===3)?node.parentNode:node;
            var body=editor.getBody();
            while(el&&el.nodeType===1&&el !== body){
                if(isAlignableList(el))return el;
                el=el.parentElement;
            }
            return null;
        }
        function applyAlign(listEl,alignValue){
            if(!listEl)return;
            var cur=(listEl.style&&listEl.style.textAlign)||editor.dom.getStyle(listEl,'text-align')||'';
            if((cur||'').toLowerCase()===(alignValue||'').toLowerCase()){
                listEl.style.removeProperty('text-align');
            }else{
                listEl.style.textAlign=alignValue;
            }
            editor.nodeChanged();
        }
        editor.on('BeforeExecCommand',function(e){
            var align=CMD2ALIGN[e.command];
            if(!align)return;
            var root=closestListRoot(editor.selection.getNode());
            if(!root)return;
            e.preventDefault();
            if(typeof e.stopImmediatePropagation==='function') e.stopImmediatePropagation();
            applyAlign(root,align);
            if(root.parentNode){
                var p=document.createElement('p');
                p.innerHTML='<br data-mce-bogus="1">';
                root.parentNode.insertBefore(p,root.nextSibling);
                editor.selection.setCursorLocation(p,0);
            }
        });
    });
})();

(function(w){
    'use strict';
    if(!w.tinymce)return;
    var LS_PREFIX='mce_darkmode_';
    function addHostStylesOnce(){
        if(document.getElementById('mce-darkmode-host-css'))return;
        var css='.mce-container.mce-dark .mce-statusbar{background:#686868 ;color:#bfc4cc;border-color:#2a2c31}'+'.mce-dark iframe,.tox.tox-tinymce.mce-dark iframe{background:#686868 }'+'.dm-switch{display:flex;align-items:center;gap:6px;}'+'.dm-switch label {cursor: pointer;}'+'.dm-badge{min-width:30px;text-align:center;border-radius:0;padding:1px 5px;font:500 11px/1.2 system-ui,sans-serif;border:1px solid #c3c4c7;background:#f6f7f7;color:#686868 }'+'.dm-badge--dark{background:#2c3338;border-color:#1f2328;color:#e5e7ea}'+'.dm-toggle{position:relative;display:inline-block;width:44px;height:20px;vertical-align:middle}'+'.dm-toggle input{opacity:0;width:0;height:0}'+'.dm-slider{position:absolute;inset:0;border-radius:0;background:#dcdcde;transition:.16s ease;border:1px solid #c3c4c7}'+'.dm-slider:before{content:"";position:absolute;height:16px;width:16px;left:1px;top:1px;border-radius:0;background:#fff;transition:.16s ease;box-shadow:0 1px 1px rgba(0,0,0,.15)}'+'.dm-toggle input:checked + .dm-slider{background:#3c3f46;border-color:#2a2c31}'+'.dm-toggle input:checked + .dm-slider:before{transform:translateX(24px);background:#e6e7ea}'+'.dm-toggle:hover .dm-slider{border-color:#8c8f94}'+'.dm-toggle input:focus + .dm-slider{outline:2px solid transparent;box-shadow:0 0 0 1px var(--wp-admin-theme-color), 0 0 0 3px rgba(34,113,177,.35)}'+'.dm-btnlike{appearance:none;border:0;background:none;padding:0;margin:0;}';
        var st=document.createElement('style');st.id='mce-darkmode-host-css';st.textContent=css;document.head.appendChild(st);
    }
    function addContentStylesOnce(doc){
        if(!doc||doc.getElementById('mce-darkmode-content-css'))return;
        var css=''+'body.mce-dark{background:#686868  !important;}'+'body.mce-dark a{color:#7cc7ff !important}'+'body.mce-dark hr{border-color:#2f3237}'+'body.mce-dark table{border-color:#333}'+'body.mce-dark td,body.mce-dark th{border-color:#333}'+'body.mce-dark blockquote{color:#cdd3db;border-left-color:#2f3237}'+'body.mce-dark code,body.mce-dark pre{background:#15171a;color:#e9ecf1;border-color:#2a2c31}'+'body.mce-dark ::selection{background:#3b4451;color:#fff}'+'body.mce-dark img,body.mce-dark video{filter:none}';
        var st=doc.createElement('style');st.id='mce-darkmode-content-css';st.textContent=css;(doc.head||doc.documentElement).appendChild(st);
    }
    function applyState(editor,isDark){
        try{
            var cont=editor.getContainer();if(cont)cont.classList.toggle('mce-dark',!!isDark);
            var doc=editor.getDoc&&editor.getDoc();if(doc){addContentStylesOnce(doc);doc.body&&doc.body.classList.toggle('mce-dark',!!isDark)}
        }catch(_){}
    }
    function buildSwitcher(editor){
        addHostStylesOnce();
        var wrap=editor.container&&editor.container.closest('.wp-editor-wrap');
        var host=wrap&&wrap.querySelector('.wp-editor-tabs');
        if(!host){
            var statusbar=editor.getContainer().querySelector('.mce-statusbar,.tox-statusbar');
            host=statusbar||editor.getContainer();
        }
        var switchId='dm-toggle-'+(editor.id||('ed-'+Math.random().toString(36).slice(2)));
        if(host.querySelector('#'+switchId))return;
        var holder=document.createElement('div');
        holder.className='dm-switch wp-switch-editor';
        holder.innerHTML='<label for="'+switchId+'" class="dm-badge dm-badge--light">Light</label>'+'<label class="dm-toggle dm-btnlike" title="Toggle dark mode">'+'<input id="'+switchId+'" type="checkbox" aria-label="Dark mode toggle">'+'<span class="dm-slider"></span>'+'</label>'+'<label for="'+switchId+'" class="dm-badge dm-badge--dark">Dark</label>';
        host.appendChild(holder);
        var input=holder.querySelector('input');
        var key=LS_PREFIX+(editor.id||'default');
        var enabled=(localStorage.getItem(key)==='1');
        input.checked=!!enabled;
        applyState(editor,enabled);
        input.addEventListener('change',function(){
            enabled=input.checked;localStorage.setItem(key,enabled?'1':'0');applyState(editor,enabled);editor.fire('Change');
        });
        editor.on('SetContent LoadContent NodeChange',function(){applyState(editor,enabled)});
    }
    tinymce.on('AddEditor',function(e){
        var ed=e.editor;if(!ed)return;ed.on('init',function(){buildSwitcher(ed)});
    });
    try{
        (tinymce.editors||[]).forEach(function(ed){
            if(ed.initialized)buildSwitcher(ed);
            else ed.on('init',function(){buildSwitcher(ed)});
        });
    }catch(_){}
})(window);

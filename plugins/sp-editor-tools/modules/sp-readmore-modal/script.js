(function (w) {
    'use strict';

    class ReadMoreConstructor {
        constructor(opts = {}) {
            this.opts = Object.assign({
                className: 'main-link',
                imgW: 24,
                imgH: 24,
                defaults: { more: 'See All', less: 'Hide', img: '' },
                mount: document.body
            }, opts);
            this._modal = null;
        }
        esc(s){ return String(s==null?'':s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;').replace(/>/g,'&gt;').replace(/'/g,'&#39;'); }
        pickImage(cb){
            const WP = (w.wp && w.wp.media) ? w.wp : (w.top && w.top.wp && w.top.wp.media ? w.top.wp : null);
            if (!WP){ alert('wp.media not found. Connect wp_enqueue_media().'); return; }
            const frame = WP.media({
                title:'Select icon', library:{type:['image','image/svg+xml','svg']},
                button:{text:'Select'}, multiple:false
            });
            frame.on('select', ()=> {
                const j = frame.state().get('selection').first().toJSON();
                cb(j && j.url ? j.url : '');
            });
            frame.open();
        }
        buildModalHTML(state){
            const esc = this.esc.bind(this);
            return `
              <div class="rm-overlay" data-sp-readmore-modal>
                <style>
                  .rm-overlay[data-sp-readmore-modal] {
                    position: fixed;
                    inset: 0;
                    z-index: 99999;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 16px;
                    background: rgb(26 31 36 / 42%);
                  }
                  .rm-overlay[data-sp-readmore-modal] *,
                  .rm-overlay[data-sp-readmore-modal] *::before,
                  .rm-overlay[data-sp-readmore-modal] *::after {
                    box-sizing: border-box;
                  }
                  .rm-overlay[data-sp-readmore-modal] .rm-modal {
                    min-width: 520px;
                    max-width: calc(100vw - 32px);
                    padding: 22px 22px 18px;
                    border: 1px solid var(--color-border, #e7eaee);
                    border-radius: var(--sp-admin-radius-lg, 18px);
                    background: var(--color-surface, #fff);
                    color: var(--color-text, #1a1f24);
                    box-shadow: var(--sp-admin-shadow-hover, 0 14px 36px rgb(26 31 36 / 18%));
                    font-family: var(--sp-admin-font, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif);
                  }
                  .rm-overlay[data-sp-readmore-modal] h3 {
                    margin: 0 0 16px;
                    color: var(--color-text, #1a1f24);
                    font-size: 18px;
                    line-height: 1.3;
                  }
                  .rm-grid {
                    display: grid;
                    grid-template-columns: 42px minmax(0, 1fr) minmax(0, 1fr);
                    gap: 12px;
                    align-items: center;
                  }
                  .rm-label {
                    color: var(--color-text-2, #525b66);
                    font-size: 12px;
                    font-weight: 650;
                  }
                  .rm-icon-field {
                    position: relative;
                  }
                  .rm-clear {
                    position: absolute;
                    top: -7px;
                    right: -7px;
                    z-index: 1;
                    width: 22px;
                    height: 22px;
                    padding: 0;
                    border: 1px solid var(--color-border-strong, #d6dbe1);
                    border-radius: 999px;
                    background: var(--color-surface, #fff);
                    color: var(--color-text-2, #525b66);
                    box-shadow: var(--sp-admin-shadow-xs, 0 1px 2px rgb(26 31 36 / 4%));
                    cursor: pointer;
                  }
                  .rm-preview {
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    width: 100%;
                    height: 100%;
                    aspect-ratio: 1 / 1;
                    overflow: hidden;
                    border: 1px solid var(--color-border-strong, #d6dbe1);
                    border-radius: var(--sp-admin-radius-sm, 9px);
                    background: var(--color-surface-alt, #f8fafc);
                    color: var(--color-text-3, #8a919b);
                    cursor: pointer;
                    transition: border-color 160ms ease, background 160ms ease;
                  }
                  .rm-preview:hover {
                    border-color: var(--color-accent, #3858e9);
                    background: var(--sp-admin-accent-softer, #f7f8ff);
                  }
                  .rm-preview img {
                    max-width: 100%;
                    max-height: 100%;
                  }
                  .rm-preview-placeholder {
                    opacity: .72;
                    font-size: 20px;
                  }
                  .rm-i {
                    width: 100%;
                    min-height: 38px;
                    padding: 8px 10px;
                    border: 1px solid var(--color-border-strong, #d6dbe1);
                    border-radius: var(--sp-admin-radius-sm, 9px);
                    background: var(--color-input-bg, #fdfefe);
                    color: var(--color-text, #1a1f24);
                  }
                  .rm-i:focus {
                    border-color: var(--color-accent, #3858e9);
                    box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                    outline: 0;
                  }
                  .rm-actions {
                    display: flex;
                    justify-content: flex-end;
                    gap: 10px;
                    margin-top: 18px;
                    padding-top: 16px;
                    border-top: 1px solid var(--color-border, #e7eaee);
                  }
                  .rm-actions button {
                    min-height: 36px;
                    padding: 7px 14px;
                    border: 1px solid var(--color-border-strong, #d6dbe1);
                    border-radius: var(--sp-admin-radius-sm, 9px);
                    background: var(--color-surface, #fff);
                    color: var(--color-text, #1a1f24);
                    font-weight: 600;
                    cursor: pointer;
                  }
                  .rm-actions button:hover {
                    border-color: var(--color-accent, #3858e9);
                    background: var(--color-surface-alt, #f8fafc);
                  }
                  .rm-actions button:focus-visible,
                  .rm-clear:focus-visible {
                    border-color: var(--color-accent, #3858e9);
                    box-shadow: var(--sp-admin-focus, 0 0 0 3px rgb(56 88 233 / 18%));
                    outline: 0;
                  }
                  .rm-actions .rm-del {
                    margin-right: auto;
                    border-color: var(--color-error, #e74c3c);
                    background: var(--color-error, #e74c3c);
                    color: var(--color-surface, #fff);
                  }
                  .rm-actions .rm-del:hover {
                    border-color: var(--color-error, #e74c3c);
                    background: var(--color-error, #e74c3c);
                    filter: brightness(.94);
                  }
                  .rm-actions .rm-ok {
                    border-color: var(--color-accent, #3858e9);
                    background: var(--color-accent, #3858e9);
                    color: var(--color-on-accent, #fff);
                  }
                  .rm-actions .rm-ok:hover {
                    border-color: var(--color-accent-hover, #2145e6);
                    background: var(--color-accent-hover, #2145e6);
                    color: var(--color-on-accent, #fff);
                  }
                </style>
                <div class="rm-modal">
                  <h3>${state._editing ? 'Edit' : 'Insert'} Read More</h3>
                  <div class="rm-grid">
                    <div class="rm-label">Icon</div>
                    <div class="rm-label">More text</div>
                    <div class="rm-label">Less text</div>

                    <div class="rm-icon-field">
                      <button type="button" class="rm-clear" title="Clear" style="display:${state.img ? 'block':'none'};">×</button>
                      <div class="rm-preview">
                        ${state.img ? `<img src="${esc(state.img)}">` : '<span class="rm-preview-placeholder">+</span>'}
                      </div>
                    </div>

                    <input type="text" class="rm-i" data-k="more" value="${esc(state.more)}">
                    <input type="text" class="rm-i" data-k="less" value="${esc(state.less)}">
                  </div>

                  <div class="rm-actions">
                    ${state._editing ? '<button class="rm-del">Delete</button>' : ''}
                    <button class="rm-cancel">Cancel</button>
                    <button class="rm-ok">${state._editing ? 'Update' : 'Insert'}</button>
                  </div>
                </div>
              </div>`;
        }
        buildButtonHTML(state, existingClass){
            const cls = existingClass || this.opts.className;
            const esc = this.esc.bind(this);
            const img = state.img ? `<img src="${esc(state.img)}" width="${this.opts.imgW}" height="${this.opts.imgH}" alt="">` : '';
            return `<button type="button" class="${esc(cls)}" data-read-more data-more-text="${esc(state.more)}" data-less-text="${esc(state.less)}">${img}<span class="read-more-text">${esc(state.more)}</span></button>`;
        }
        open(options, onDone){
            const state = Object.assign({_editing:false, more:this.opts.defaults.more, less:this.opts.defaults.less, img:''}, options?.value||{});
            state._editing = !!options?.editing;

            this.close();
            const wrap = document.createElement('div');
            wrap.innerHTML = this.buildModalHTML(state);
            this._modal = wrap.firstElementChild;
            this.opts.mount.appendChild(this._modal);

            const root = this._modal;
            const preview = root.querySelector('.rm-preview');
            const clearBtn = root.querySelector('.rm-clear');
            const moreI = root.querySelector('.rm-i[data-k="more"]');
            const lessI = root.querySelector('.rm-i[data-k="less"]');

            const done = (action) => {
                if (action === 'delete') { onDone && onDone({ action:'delete' }); this.close(); return; }
                if (action === 'cancel') { onDone && onDone({ action:'cancel' }); this.close(); return; }
                const out = { more: (moreI.value || this.opts.defaults.more), less: (lessI.value || this.opts.defaults.less), img: (preview.querySelector('img')?.src || '') };
                onDone && onDone({ action: state._editing ? 'update' : 'insert', html: this.buildButtonHTML(out, options?.value?.class) });
                this.close();
            };

            root.addEventListener('click', (e) => {
                if (e.target.classList.contains('rm-cancel')) { done('cancel'); }
                if (e.target.classList.contains('rm-ok'))     { done('ok'); }
                if (e.target.classList.contains('rm-del'))    { done('delete'); }
                if (e.target.closest('.rm-preview')) {
                    this.pickImage((url)=> {
                        if (!url) return;
                        preview.innerHTML = `<img src="${this.esc(url)}">`;
                        if (clearBtn) clearBtn.style.display = 'block';
                    });
                }
                if (e.target.classList.contains('rm-clear')) {
                    preview.innerHTML = '<span class="rm-preview-placeholder">+</span>';
                    e.target.style.display = 'none';
                }
            });
        }
        close(){ if (this._modal) { this._modal.remove(); this._modal = null; } }
    }
    w.ReadMoreConstructor = ReadMoreConstructor;

    function resolveButton(editor, seed){
        const dom = editor.dom;

        if (seed && seed.nodeType === 1) {
            const fromSeed = seed.closest ? seed.closest('button[data-read-more]') : dom.getParent(seed, 'button[data-read-more]');
            if (fromSeed) return fromSeed;
        }

        let n = editor.selection && (editor.selection.getNode ? editor.selection.getNode() : editor.selection.getStart());
        if (n) {
            const bySel = n.closest ? n.closest('button[data-read-more]') : dom.getParent(n, 'button[data-read-more]');
            if (bySel) return bySel;
        }

        if (n && n.nodeType === 1) {
            const prev = n.previousSibling, next = n.nextSibling;
            if (prev && prev.nodeType === 1 && prev.matches && prev.matches('button[data-read-more]')) return prev;
            if (next && next.nodeType === 1 && next.matches && next.matches('button[data-read-more]')) return next;
        }
        return null;
    }

    function openForEditor(editor, isT5, ctxBtn){
        const existing = resolveButton(editor, ctxBtn);

        const ctor = new ReadMoreConstructor();
        const initial = { more:'See All', less:'Hide', img:'', class:'main-link' };

        if (existing) {
            initial.more  = existing.getAttribute('data-more-text') || initial.more;
            initial.less  = existing.getAttribute('data-less-text') || initial.less;
            initial.class = existing.getAttribute('class') || initial.class;
            const img = existing.querySelector && existing.querySelector('img');
            if (img) initial.img = img.getAttribute('src') || '';
        }

        ctor.open({ editing: !!existing, value: initial }, (res) => {
            if (!res || res.action === 'cancel') return;

            if (res.action === 'delete' && existing) {
                if (editor.undoManager && editor.undoManager.transact) {
                    editor.undoManager.transact(()=> existing.remove());
                } else {
                    existing.remove();
                }
                return;
            }

            if (!res.html) return;
            const html = res.html;
            const doInsert = ()=> { if (existing) existing.outerHTML = html; else editor.insertContent(html); };
            if (editor.undoManager && editor.undoManager.transact) editor.undoManager.transact(doInsert);
            else doInsert();
        });
    }

    tinymce.PluginManager.add('sp_read_more_modal_img', function(editor){
        const major = parseInt((tinymce.majorVersion || '4'), 10);
        const isT5 = !!(editor.ui && editor.ui.registry && typeof editor.ui.registry.addButton === 'function' && major >= 5);

        if (isT5) {
            editor.ui.registry.addButton('sp_read_more_modal_img', {
                tooltip: 'Read more',
                icon: 'insert-time',
                onAction: () => openForEditor(editor, true, null)
            });
        } else if (typeof editor.addButton === 'function') {
            editor.addButton('sp_read_more_modal_img', {
                tooltip: 'Read more',
                icon: 'wp_more',
                onclick: () => openForEditor(editor, false, null)
            });
        }

        editor.on('DblClick', function(e){
            let btn = resolveButton(editor, e.target);
            if (!btn) return;
            if (e.preventDefault) e.preventDefault();
            if (e.stopPropagation) e.stopPropagation();
            if (e.stopImmediatePropagation) e.stopImmediatePropagation();
            openForEditor(editor, isT5, btn);
        });

        return { getMetadata: () => ({ name: 'SP Read More Constructor (robust dblclick)' }) };
    });

})(window);

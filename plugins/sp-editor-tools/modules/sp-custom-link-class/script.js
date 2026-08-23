!function (e) {
  "use strict";
  new class t {
    constructor(t = {}) {
        this.SPRITE_URL = e.UIA_LINKPICKER && UIA_LINKPICKER.spriteUrl || "", this.PREVIEW_CSS_URL = e.UIA_LINKPICKER && UIA_LINKPICKER.pickerCssUrl || "",
        this.FAVORITE_IDS = e.UIA_LINKPICKER && Array.isArray(UIA_LINKPICKER.favoriteIconIDs) ? UIA_LINKPICKER.favoriteIconIDs : [],
        this.IDS = Object.assign({
          wrap: "#wp-link",
          text: "#wp-link-text",
          primary: "#wp-link .button-primary",
          fieldStyle: "#wp-link-style",
          fieldPos: "#wp-link-icon-pos",
          fieldIconPreview: "wp-link-icon-preview",
          btnBeforeIcon: "wp-link-icon-before",
          btnAfterIcon: "wp-link-icon-after",
          btnChoose: "wp-link-icon-choose",
          btnClear: "wp-link-icon-clear"
        }, t.ids || {}), this.CHOICES = t.choices || [], this.pendingIconPos = null, this.current = {
          cls: "",
          iconHref: "",
          iconMode: "sprite",
          pos: "before",
          beforeIconHref: "",
          beforeIconMode: "sprite",
          afterIconHref: "",
          afterIconMode: "sprite"
        }, this.ACF_CTX = null, this.defaultLinkContext = !1, this.lastActiveLink = null, this.existingEditorLinks = [], this._installed = !1, this._observer = null, this._previewSyncTimer = null, this._bindGlobal(),
        this._bootEditorIconGuards(), this._bootAcf(), this._waitWpLink();
    }
    escA(e) {
      return String(e).replace(/&/g, "&amp;").replace(/"/g, "&quot;").replace(/</g, "&lt;").replace(/>/g, "&gt;");
    }
    escT(e) {
      return String(e).replace(/&/g, "&amp;").replace(/</g, "&lt;");
    }
    visibleText(e) {
      return e ? String(e).replace(/\u200B/g, "").replace(/\s+/g, " ").trim() : "";
    }
    qs(e, t) {
      return (t || document).querySelector(e);
    }
    qsa(e, t) {
      return Array.prototype.slice.call((t || document).querySelectorAll(e));
    }
    on(e, t, i, s) {
      e && e.addEventListener && e.addEventListener(t, i, s || !1);
    }
    _ensureStylePreviewCss() {
      if (document.getElementById("sp-link-style-preview-css")) return;
      let e = document.createElement("style");
      e.id = "sp-link-style-preview-css", e.textContent = `
        #wp-link-wrap #link-selector:has(.sp-link-settings-drawer) {
          top: 37px !important;
        }

        #wp-link-style {
          position: absolute !important;
          width: 1px !important;
          height: 1px !important;
          padding: 0 !important;
          margin: -1px !important;
          overflow: hidden !important;
          clip: rect(0, 0, 0, 0) !important;
          white-space: nowrap !important;
          border: 0 !important;
        }
        #wp-link-wrap.sp-link-picker-enhanced {
          width: min(1240px, calc(100vw - 64px)) !important;
          height: min(900px, calc(100vh - 96px)) !important;
          left: 50% !important;
          margin-left: 0 !important;
          margin-top: calc(min(760px, calc(100vh - 96px)) / -2) !important;
          transform: translateX(-50%) !important;
        }
        #wp-link-wrap.sp-link-picker-enhanced #wp-link {
          height: 100% !important;
          overflow: hidden !important;
        }
        .sp-link-style-preview {
          width: 100%;
          margin: 0;
          display: grid;
          grid-template-columns: 1fr;
          gap: 10px;
        }
        .sp-link-style-preview__option {
          width: 100%;
          // min-height: 98px;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 0;
          border: 1px solid #c3c4c7;
          background: #fff;
          color: #1d2327;
          cursor: pointer;
          text-align: left;
          overflow: hidden;
          transition: border-color .15s, box-shadow .15s, background .15s;
        }
        .sp-link-style-preview__option:hover,
        .sp-link-style-preview__option:focus {
          border-color: var(--wp-admin-theme-color);
          outline: none;
        }
        .sp-link-style-preview__option.is-active {
          border-color: var(--wp-admin-theme-color);
          background: #f8faff;
        }
        .sp-link-style-preview__option.is-wide {
          grid-column: auto;
        }
        #wp-link-wrap.sp-link-picker-enhanced #link-selector {
          display: grid !important;
          grid-template-columns: minmax(0, 1fr) 520px !important;
          grid-template-rows: auto auto minmax(0, 1fr) !important;
          align-items: stretch !important;
          min-height: 0 !important;
          overflow: hidden !important;
          padding-right: 0 !important;
        }
        #wp-link-wrap.sp-link-picker-enhanced #link-options,
        #wp-link-wrap.sp-link-picker-enhanced #wplink-enter-url,
        #wp-link-wrap.sp-link-picker-enhanced #search-panel {
          grid-column: 1 !important;
          width: auto !important;
          max-width: none !important;
          min-width: 0 !important;
        }
        #wp-link-wrap.sp-link-picker-enhanced #search-panel {
          align-self: stretch !important;
          min-height: 0 !important;
          overflow: auto !important;
        }
        #wp-link-wrap.sp-link-picker-enhanced #wp-link .query-results {
          position: static !important;
          width: auto !important;
          max-height: none !important;
          margin-right: 16px;
        }
        .sp-link-settings-drawer {
          grid-column: 2 !important;
          grid-row: 1 / 4 !important;
          width: 520px !important;
          min-width: 0 !important;
          display: flex !important;
          flex-direction: column !important;
          padding: 22px 22px 0 !important;
          box-sizing: border-box !important;
          overflow: hidden !important;
          border-left: 1px solid #dcdcde;
          background: #f6f7f7;
          box-shadow: -10px 0 24px rgba(0, 0, 0, .04);
        }
        .sp-link-settings-drawer__title {
          margin: 0 0 4px;
          color: #1d2327;
          font-size: 15px;
          line-height: 1.3;
          font-weight: 700;
        }
        .sp-link-settings-drawer__hint {
          margin: 0 0 18px;
          color: #646970;
          font-size: 12px;
          line-height: 1.5;
        }
        .sp-link-settings-drawer__body {
          min-height: 0;
          display: flex;
          flex-direction: column;
          flex: 1 1 auto;
        }
        .sp-link-settings-drawer__scroll {
          min-height: 0;
          flex: 1 1 auto;
          overflow-y: auto;
          padding: 0 4px 18px 0;
        }
        .sp-link-settings-drawer__sticky {
          flex: 0 0 auto;
          margin: 0 -22px;
          padding: 16px 22px 18px;
          border-top: 1px solid #dcdcde;
          background: #fff;
          box-shadow: 0 -10px 22px rgba(0, 0, 0, .06);
        }
        .sp-link-settings-drawer .wp-link-style-field,
        .sp-link-settings-drawer .wp-link-icon-pos-field,
        .sp-link-settings-drawer .wp-link-icon-field {
          margin: 0 0 18px !important;
        }
        .sp-link-settings-drawer label {
          display: block;
          margin: 0;
        }
        .sp-link-settings-drawer label > span,
        .sp-link-settings-drawer .wp-link-icon-field > label > span {
          display: block;
          width: auto;
          margin: 0 0 8px;
          color: #1d2327;
          font-size: 12px;
          line-height: 1.4;
          font-weight: 700;
          text-align: left;
        }
        .sp-link-settings-drawer select {
          width: 100% !important;
          margin: 0 !important;
        }
        .sp-link-settings-drawer .wp-link-icon-field > div {
          width: 100% !important;
          margin: 0 !important;
          display: block !important;
        }
        .sp-link-icon-choices {
          display: grid !important;
          grid-template-columns: 1fr 1fr !important;
          gap: 20px !important;
        }
        .sp-link-icon-choice-wrapper {
          display: block;
        }
        .sp-link-icon-choice__title-label {
          display: block;
          margin: 0 0 8px;
          color: #1d2327;
          font-size: 12px;
          line-height: 1.4;
          font-weight: 700;
          text-align: left;
        }
        .sp-link-icon-choice {
          position: relative;
          width: 100%;
          aspect-ratio: 2 / 1;
          display: flex;
          align-items: center;
          justify-content: center;
          gap: 10px;
          padding: 12px;
          border: 1px dashed #8c8f94;
          background: #fff;
          color: #1d2327;
          cursor: pointer;
        }
        .sp-link-icon-choice.is-active {
          border-style: solid;
          border-color: var(--wp-admin-theme-color);
          background: #f8faff;
        }
        .sp-link-icon-choice__label {
          font-weight: 700;
        }
        .sp-link-icon-choice__preview {
          width: 34px;
          height: 34px;
          display: flex;
          align-items: center;
          justify-content: center;
          flex: 0 0 auto;
          color: #646970;
          pointer-events: none;
        }
        .sp-link-icon-choice__preview svg,
        .sp-link-icon-choice__preview img {
          max-width: 100%;
          max-height: 100%;
          display: block;
        }
        .sp-link-icon-choice__remove {
          position: absolute;
          top: 0px;
          right: 0px;
          transform: translate(50%, -50%);
          width: 18px;
          height: 18px;
          display: none;
          align-items: center;
          justify-content: center;
          border: 1px solid #ccd0d4;
          border-radius: 0;
          background: #fff;
          color: red;
          font-size: 13px;
          line-height: 1;
          font-weight: 500;
          cursor: pointer;
          transition: border-color .1s ease-in-out, background-color .1s ease-in-out, color .1s ease-in-out;
          z-index: 10;
          box-shadow: none;
        }
        .sp-link-icon-choice__remove svg {
          width: 80%;
          height: auto;
        }
        .sp-link-icon-choice__remove:hover {
          border-color: #d63638;
          background: #d63638;
          color: #fff;
          transform: translate(50%, -50%);
        }
        .sp-link-icon-choice.has-icon .sp-link-icon-choice__remove {
          display: flex;
        }
        .sp-link-icon-clear {
          display: none !important;
        }
        body.sp-link-icon-media-open .media-modal {
          z-index: 16000010 !important;
        }
        body.sp-link-icon-media-open .media-modal-backdrop {
          z-index: 16000000 !important;
        }
        .sp-link-style-preview__canvas {
          width: 100%;
          min-height: 82px;
          display: flex;
          align-items: center;
          justify-content: flex-start;
          overflow: hidden;
        }
        .sp-link-style-preview__frame {
          width: var(--sp-link-preview-viewport, 1920px);
          max-width: none;
          flex: 0 0 var(--sp-link-preview-viewport, 1920px);
          height: 100px;
          display: block;
          border: 0;
          pointer-events: none;
          background: transparent;
          opacity: 1;
          transition: opacity .18s ease;
        }
        .sp-link-style-preview__button {
          max-width: 100%;
          display: inline-flex;
          align-items: center;
          justify-content: center;
          text-decoration: none;
        }
        .sp-link-style-preview__button svg,
        .sp-link-style-preview__button img {
          display: block;
          flex: 0 0 auto;
        }
        .sp-link-editor-icon {
          display: inline-flex;
          align-items: center;
          justify-content: center;
          flex: 0 0 auto;
          pointer-events: none;
          user-select: none;
          -webkit-user-select: none;
        }
        .sp-link-editor-icon svg,
        .sp-link-editor-icon img {
          display: block;
          pointer-events: none;
          user-select: none;
          -webkit-user-select: none;
        }
        .sp-link-style-preview__button[data-style=""] {
          background: transparent;
          color: #2271b1;
          font-weight: 500;
        }
        @media (max-width: 782px) {
          #wp-link-wrap.sp-link-picker-enhanced {
            width: calc(100vw - 24px) !important;
          }
          #wp-link-wrap.sp-link-picker-enhanced #link-selector {
            display: block !important;
            min-height: 0 !important;
          }
          .sp-link-settings-drawer {
            width: auto !important;
            margin: 16px 0 0;
            padding: 16px !important;
            border-left: 0;
            border-top: 1px solid #dcdcde;
            box-shadow: none;
            overflow: visible;
          }
          .sp-link-settings-drawer__scroll {
            overflow: visible;
          }
          .sp-link-settings-drawer__sticky {
            margin: 0;
            padding: 16px 0 0;
            box-shadow: none;
          }
          .sp-link-style-preview {
            grid-template-columns: 1fr;
          }
        }
      `, document.head.appendChild(e), this._previewViewportResizeReady || (this._previewViewportResizeReady = !0, this.on(window, "resize", () => this._syncPreviewFrameViewports()));
    }
    _stylePreviewText(t) {
      let i = this.qs(this.IDS.text), s = this.visibleText(i ? i.value : "");
      return s || this.visibleText(t && t.textContent) || "Button text";
    }
    _stylePreviewIconHtml() {
      return this._iconHtml(arguments.length ? arguments[0] : this.current.pos, 'class="sprite" aria-hidden="true"', 'class="sprite"');
    }
    _stylePreviewCssUrl() {
      if (this.PREVIEW_CSS_URL) return this.PREVIEW_CSS_URL;
      let e = document.querySelector('link[href*="/assets/css/for-link-picker.css"]');
      return e ? e.href : "";
    }
    _syncPreviewFrameViewports(e) {
      this.qsa(".sp-link-style-preview__canvas", e || document).forEach(e => {
        let t = e.querySelector(".sp-link-style-preview__frame"), i = Math.max(1, Math.round(e.getBoundingClientRect().width || e.clientWidth || 520));
        e.style.setProperty("--sp-link-preview-visible-width", i + "px"), e.style.setProperty("--sp-link-preview-viewport", "1920px");
        if (!t) return;
        let s = () => {
          try {
            let e = t.contentDocument;
            e && e.documentElement && (e.documentElement.style.setProperty("--sp-preview-visible-width", i + "px"), e.body && e.body.style.setProperty("--sp-preview-visible-width", i + "px"));
          } catch (r) { }
        };
        s(), t.addEventListener && t.addEventListener("load", s, {
          once: !0
        });
      });
    }
    _stylePreviewButtonHtml(e, t) {
      let i = (e || "").trim(), s = this._stylePreviewText(t), r = this._stylePreviewIconHtml("before"), n = this._stylePreviewIconHtml("after"), l = `${r ? r + " " : ""}${this.escT(s)}${n ? " " + n : ""}`;
      return `<span class="sp-link-style-preview__button ${this.escA(i)}" data-style="${this.escA(i)}">${l}</span>`;
    }
    _stylePreviewDocumentHtml(e, t) {
      let i = this._stylePreviewCssUrl(), s = this._stylePreviewButtonHtml(e, t);
      return `<!doctype html><html><head><meta charset="utf-8"><style>html{font-size:var(--rem-func,10px);}body{width:var(--sp-preview-visible-width,520px);margin:0;overflow:hidden;background:transparent}.sp-link-picker-preview-stage{width:var(--sp-preview-visible-width,520px);box-sizing:border-box;padding:12px;display:flex;align-items:center;justify-content:center}.sp-link-picker-preview-stage .main-button{max-width:100%;}.sp-link-picker-preview-stage .main-button.full-width{width:100%;}</style>${i ? `<link rel="stylesheet" href="${this.escA(i)}">` : ""}</head><body><div class="sp-link-picker-preview-stage">${s}</div></body></html>`;
    }
    _stylePreviewSampleHtml(e, t) {
      let c = this._stylePreviewDocumentHtml(e, t);
      return `<span class="sp-link-style-preview__canvas"><iframe class="sp-link-style-preview__frame" title="${this.escA(this.visibleText(t && t.textContent) || "Link style preview")}" aria-hidden="true" tabindex="-1" srcdoc="${this.escA(c)}"></iframe></span>`;
    }
    _updateStylePreviewFrame(e, t, i) {
      let s = e && e.querySelector(".sp-link-style-preview__frame");
      if (!s) return !1;
      let r = () => {
        try {
          let n = s.contentDocument, l = n && n.querySelector(".sp-link-picker-preview-stage");
          if (!l) return !1;
          let o = this._stylePreviewButtonHtml(t, i);
          return l.innerHTML !== o && (l.innerHTML = o), this._syncPreviewFrameViewports(e), !0;
        } catch (o) {
          return !1;
        }
      };
      return r() || (s.addEventListener && s.addEventListener("load", r, {
        once: !0
      }), !1);
    }
    _syncStylePicker(e) {
      let t = e && e.closest(".wp-link-style-field"), i = t && t.querySelector(".sp-link-style-preview");
      if (!e || !i) return;
      this.qsa(".sp-link-style-preview__option", i).forEach(t => {
        let i = t.getAttribute("data-value") || "", s = i === (e.value || ""), r = Array.prototype.find.call(e.options, e => (e.value || "") === i);
        t.classList.toggle("is-active", s), t.setAttribute("aria-pressed", s ? "true" : "false"),
          t.querySelector(".sp-link-style-preview__frame") ? this._updateStylePreviewFrame(t, i, r) : (t.innerHTML = this._stylePreviewSampleHtml(i, r), this._syncPreviewFrameViewports(t));
      });
    }
    _scheduleStylePickerSync(e, t = 350) {
      clearTimeout(this._previewSyncTimer), this._previewSyncTimer = setTimeout(() => {
        this._syncStylePicker(e || this.qs(this.IDS.fieldStyle));
      }, t);
    }
    _ensureStylePicker(e) {
      if (!e || e.dataset.stylePreviewReady === "1") return;
      let t = e.closest(".wp-link-style-field");
      if (!t) return;
      this._ensureStylePreviewCss(), e.dataset.stylePreviewReady = "1";
      let s = document.createElement("div");
      s.className = "sp-link-style-preview", s.setAttribute("role", "group"), s.setAttribute("aria-label", "Link style");
      Array.prototype.forEach.call(e.options, t => {
        let i = document.createElement("button");
        i.type = "button", i.className = "sp-link-style-preview__option" + ((t.value || "").indexOf("full-width") !== -1 ? " is-wide" : ""), i.setAttribute("data-value", t.value || ""),
          i.innerHTML = this._stylePreviewSampleHtml(t.value || "", t), this.on(i, "click", () => {
            e.value = t.value || "", this.current.cls = (e.value || "").trim(), e.dispatchEvent(new Event("input", {
              bubbles: !0
            })), e.dispatchEvent(new Event("change", {
              bubbles: !0
            })), this._syncStylePicker(e);
          }), s.appendChild(i);
      }), t.appendChild(s), this._syncStylePicker(e), this._syncPreviewFrameViewports(s);
    }
    _ensureSettingsDrawer() {
      let e = this.qs(this.IDS.wrap);
      if (!e) return;
      let a = e.closest("#wp-link-wrap") || e;
      a.classList.add("sp-link-picker-enhanced");
      let o = e.querySelector("#link-selector") || e, t = o.querySelector(".sp-link-settings-drawer");
      if (!t) {
        t = document.createElement("aside"), t.className = "sp-link-settings-drawer", t.innerHTML = '<h3 class="sp-link-settings-drawer__title">Link appearance</h3><p class="sp-link-settings-drawer__hint">Choose the visual style, icon placement, and optional icon for this link.</p><div class="sp-link-settings-drawer__body"><div class="sp-link-settings-drawer__scroll"></div><div class="sp-link-settings-drawer__sticky"></div></div>',
          o.appendChild(t);
      }
      t.style.display = "";
      let i = t.querySelector(".sp-link-settings-drawer__scroll"), s = t.querySelector(".sp-link-settings-drawer__sticky"), r = e.querySelector(".wp-link-style-field"), n = e.querySelector(".wp-link-icon-pos-field"), l = e.querySelector(".wp-link-icon-field");
      r && i && r.parentNode !== i && i.appendChild(r);
      [n, l].forEach(e => {
        e && s && e.parentNode !== s && s.appendChild(e);
      });
    }
    _hideSettingsDrawer() {
      let e = this.qs(this.IDS.wrap);
      if (!e) return;
      let t = e.closest("#wp-link-wrap") || e, i = e.querySelector(".sp-link-settings-drawer");
      t.classList.remove("sp-link-picker-enhanced"), i && (i.style.display = "none");
    }
    toSlugFromFilename(e) {
      return (e = (e || "").split("/").pop().replace(/\.svg(?:\?.*)?$/i, "")).toLowerCase().replace(/[^a-z0-9\-_.]+/g, "-").replace(/^-+|-+$/g, "").replace(/-+/g, "-");
    }
    modalVisible() {
      let e = this.qs(this.IDS.wrap);
      return !!(e && "none" !== e.style.display && null !== e.offsetParent);
    }
    getActiveLink() {
      try {
        let t = e.tinymce && e.tinymce.activeEditor;
        if (!t) return null;
        let i = t.selection ? t.selection.getNode ? t.selection.getNode() : t.selection.getStart() : null;
        if (!i) return null;
        return i.closest && i.closest("a[href]") || t.dom && t.dom.getParent(i, "a[href]") || null;
      } catch (s) {
        return null;
      }
    }
    resetCurrent() {
      this.current = {
        cls: "",
        iconHref: "",
        iconMode: "sprite",
        pos: "before",
        beforeIconHref: "",
        beforeIconMode: "sprite",
        afterIconHref: "",
        afterIconMode: "sprite"
      };
    }
    _iconKey(e) {
      return "after" === e ? "after" : "before";
    }
    _getIcon(e) {
      let t = this._iconKey(e);
      return {
        href: this.current[t + "IconHref"] || "",
        mode: this.current[t + "IconMode"] || "sprite"
      };
    }
    _setIcon(e, t, i) {
      let s = this._iconKey(e);
      this.current[s + "IconHref"] = t || "", this.current[s + "IconMode"] = i || "sprite",
        this.current.pos = s, this.current.iconHref = this.current[s + "IconHref"],
        this.current.iconMode = this.current[s + "IconMode"];
    }
    _clearIcon(e) {
      let t = this._iconKey(e);
      this.current[t + "IconHref"] = "", this.current[t + "IconMode"] = "sprite",
        this._syncLegacyIcon();
    }
    _hasAnyIcon() {
      return !!(this.current.beforeIconHref || this.current.afterIconHref);
    }
    _syncLegacyIcon() {
      let e = this._getIcon(this.current.pos);
      e.href || (e = this._getIcon("before")), e.href || (e = this._getIcon("after")),
        this.current.iconHref = e.href || "", this.current.iconMode = e.mode || "sprite";
    }
    _iconHtml(e, t = "", i = "") {
      let s = this._getIcon(e);
      return s.href ? "sprite" === s.mode ? `<svg ${t}><use href="${this.escA(s.href)}"></use></svg>` : `<img ${i} alt="" src="${this.escA(s.href)}">` : "";
    }
    _editorIconSelector() {
      return ".sp-link-editor-icon,[data-sp-link-icon]";
    }
    _cssEscape(e) {
      return window.CSS && window.CSS.escape ? window.CSS.escape(e) : String(e).replace(/[^a-zA-Z0-9_-]/g, "\\$&");
    }
    _linkChoiceClassValues() {
      return Array.from(new Set((this.CHOICES || []).map(e => this.visibleText(e && e.value)).filter(Boolean)));
    }
    _linkChoiceSelector(e) {
      let t = this.visibleText(e).split(/\s+/).filter(Boolean);
      return t.length ? t.map(e => "." + this._cssEscape(e)).join("") : "";
    }
    _quad(e, t) {
      return [e[t + "Top"], e[t + "Right"], e[t + "Bottom"], e[t + "Left"]].join(" ");
    }
    _radiusQuad(e) {
      return [e.borderTopLeftRadius, e.borderTopRightRadius, e.borderBottomRightRadius, e.borderBottomLeftRadius].join(" ");
    }
    _editorSelectedLinkCss(e) {
      let t = this._linkChoiceClassValues(), i = t.map(e => this._linkChoiceSelector(e)).filter(Boolean);
      if (!i.length) return "";
      let s = i.map(e => `.mce-content-body a[data-mce-selected]${e}`).join(",");
      let r = `${s}{outline:2px solid rgba(72,125,228,.45)!important;outline-offset:3px!important;}`;
      if (!e || !e.body || !e.defaultView) return r;
      let n = e.createElement("div");
      n.style.cssText = "position:absolute!important;left:-99999px!important;top:-99999px!important;visibility:hidden!important;pointer-events:none!important;contain:layout style!important;", e.body.appendChild(n);
      t.forEach(t => {
        let i = this._linkChoiceSelector(t);
        if (!i) return;
        let s = e.createElement("a");
        s.href = "#", s.className = t, s.textContent = "Button text", n.appendChild(s);
        let l = e.defaultView.getComputedStyle(s);
        r += `.mce-content-body a[data-mce-selected]${i}{padding:${this._quad(l, "padding")}!important;margin:${this._quad(l, "margin")}!important;background-color:${l.backgroundColor}!important;background-image:${l.backgroundImage}!important;border-radius:${this._radiusQuad(l)}!important;box-shadow:${l.boxShadow}!important;color:${l.color}!important;}`;
      }), n.remove();
      return r;
    }
    _editorIconCss(e) {
      return `.sp-link-editor-icon{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;pointer-events:none;user-select:none;-webkit-user-select:none}.sp-link-editor-icon svg,.sp-link-editor-icon img{display:block;pointer-events:none;user-select:none;-webkit-user-select:none}.sp-link-editor-icon[contenteditable=false]{-webkit-user-modify:read-only}${this._editorSelectedLinkCss(e)}`;
    }
    _editorIconHtml(e) {
      let t = this._iconKey(e), i = this._iconHtml(t, `class="sprite sp-link-editor-icon__media" width="2.4rem" height="2.4rem" aria-hidden="true" focusable="false"`, `class="sprite sp-link-editor-icon__media" width="24" height="24" alt="" aria-hidden="true"`);
      return i ? `<span class="sp-link-editor-icon mceNonEditable" data-sp-link-icon="${this.escA(t)}" contenteditable="false" data-mce-contenteditable="false" data-mce-resize="false" draggable="false" unselectable="on" aria-hidden="true">${i}</span>` : "";
    }
    _isEditorIconNode(e) {
      return !!(e && 1 === e.nodeType && e.matches && e.matches(this._editorIconSelector()));
    }
    _prepareEditorIconNode(e, t) {
      if (!e || 1 !== e.nodeType) return null;
      let i = e;
      if (!this._isEditorIconNode(i)) {
        let s = i.ownerDocument.createElement("span");
        s.className = "sp-link-editor-icon mceNonEditable", s.setAttribute("data-sp-link-icon", this._iconKey(t)),
          s.setAttribute("contenteditable", "false"), s.setAttribute("data-mce-contenteditable", "false"),
          s.setAttribute("aria-hidden", "true"), i.parentNode && i.parentNode.insertBefore(s, i), s.appendChild(i), i = s;
      }
      return i.classList.add("sp-link-editor-icon", "mceNonEditable"), i.setAttribute("data-sp-link-icon", this._iconKey(t)),
        i.setAttribute("contenteditable", "false"), i.setAttribute("data-mce-contenteditable", "false"),
        i.setAttribute("data-mce-resize", "false"), i.setAttribute("draggable", "false"), i.setAttribute("unselectable", "on"),
        i.setAttribute("aria-hidden", "true"), i.setAttribute("tabindex", "-1"),
        this.qsa("svg,img", i).forEach(e => {
          e.setAttribute("aria-hidden", "true"), e.setAttribute("tabindex", "-1"), e.setAttribute("contenteditable", "false"),
            e.setAttribute("data-mce-contenteditable", "false"), e.setAttribute("data-mce-resize", "false"),
            e.setAttribute("draggable", "false"), e.setAttribute("unselectable", "on"), e.style.pointerEvents = "none", e.style.userSelect = "none";
        }), i;
    }
    _normalizeEditorIcons(e) {
      let t = e && e.getDoc && e.getDoc();
      t && this.qsa("a[href]", t).forEach(e => {
        let t = Array.prototype.slice.call(e.childNodes || []), i = t[0], s = t[t.length - 1];
        this._nodeHasLinkIcon(i) && this._prepareEditorIconNode(i, "before"), t = Array.prototype.slice.call(e.childNodes || []),
          s = t[t.length - 1], s && s !== t[0] && this._nodeHasLinkIcon(s) && this._prepareEditorIconNode(s, "after");
      });
    }
    _injectEditorIconCss(e) {
      let t = e && e.getDoc && e.getDoc();
      if (!t) return;
      let i = t.getElementById("sp-link-editor-icon-css"), s = this._editorIconCss(t);
      i || (i = t.createElement("style"), i.id = "sp-link-editor-icon-css", (t.head || t.documentElement).appendChild(i));
      i.textContent !== s && (i.textContent = s);
    }
    _iconSibling(e, t) {
      let i = e;
      for (; i = "previous" === t ? i.previousSibling : i.nextSibling;) {
        if (3 !== i.nodeType || this.visibleText(i.nodeValue || "")) return i;
      }
      return null;
    }
    _caretNearEditorIcon(e) {
      let t = e && e.selection && e.selection.getRng && e.selection.getRng();
      if (!t) return null;
      let i = t.startContainer;
      if (i && 1 === i.nodeType && i.closest && i.closest(this._editorIconSelector())) return i.closest(this._editorIconSelector());
      if (!t.collapsed) {
        let s = e.selection.getNode && e.selection.getNode();
        return s && s.closest && s.closest(this._editorIconSelector()) || null;
      }
      if (3 === i.nodeType) {
        if (0 === t.startOffset) {
          let r = this._iconSibling(i, "previous");
          if (this._isEditorIconNode(r)) return r;
        }
        if (t.startOffset === (i.nodeValue || "").length) {
          let n = this._iconSibling(i, "next");
          if (this._isEditorIconNode(n)) return n;
        }
        return null;
      }
      if (1 === i.nodeType) {
        let l = i.childNodes[t.startOffset], o = i.childNodes[t.startOffset - 1];
        if (this._isEditorIconNode(l)) return l;
        if (this._isEditorIconNode(o)) return o;
      }
      return null;
    }
    _selectionTouchesEditorIcon(e) {
      let t = e && e.selection && e.selection.getRng && e.selection.getRng(), i = e && e.getDoc && e.getDoc();
      if (!t || !i) return null;
      let s = t.commonAncestorContainer;
      s = s && 1 === s.nodeType ? s : s && s.parentNode;
      if (s && s.closest) {
        let r = s.closest(this._editorIconSelector());
        if (r) return r;
      }
      if (!t.collapsed && s && s.querySelectorAll) {
        let n = Array.prototype.slice.call(s.querySelectorAll(this._editorIconSelector()));
        for (let l = 0; l < n.length; l++) {
          if (!t.intersectsNode || t.intersectsNode(n[l])) return n[l];
        }
      }
      return this._caretNearEditorIcon(e);
    }
    _iconExitDirection(e, t) {
      return t || ("before" === (e && e.getAttribute && e.getAttribute("data-sp-link-icon")) ? "before" : "after");
    }
    _moveCaretOutsideEditorIcon(e, t, i) {
      let s = e && e.getDoc && e.getDoc(), r = t && t.closest && t.closest("a[href]") || t;
      if (!s || !r || !e.selection) return;
      let n = s.createRange();
      "before" === this._iconExitDirection(t, i) ? n.setStartBefore(r) : n.setStartAfter(r), n.collapse(!0), e.selection.setRng(n);
    }
    _repairEditorIconSelection(e, t) {
      if (e && e._spLinkIconAllowNativeSelectionUntil && Date.now() < e._spLinkIconAllowNativeSelectionUntil) return;
      this._normalizeEditorIcons(e);
      let i = this._selectionTouchesEditorIcon(e);
      i && this._moveCaretOutsideEditorIcon(e, i, t);
    }
    _installEditorIconGuards(e) {
      if (!e || e._spLinkIconGuards) return;
      e._spLinkIconGuards = !0;
      let t = () => {
        this._injectEditorIconCss(e), this._normalizeEditorIcons(e);
      }, i = s => {
        clearTimeout(e._spLinkIconRepairTimer), e._spLinkIconRepairTimer = setTimeout(() => this._repairEditorIconSelection(e, s), 0);
      };
      e.on && (e.on("init SetContent NodeChange Change Undo Redo", t), e.on("SelectionChange", () => i()), e.on("keydown", s => {
        let r = s && s.key || "";
        if ((s.metaKey || s.ctrlKey) && ("a" === String(r).toLowerCase() || "KeyA" === s.code || 65 === s.keyCode)) {
          e._spLinkIconAllowNativeSelectionUntil = Date.now() + 1000;
          return;
        }
        if (!/^(Enter|Backspace|Delete|ArrowLeft|ArrowRight|ArrowUp|ArrowDown)$/.test(r)) return;
        t();
        let n = /^(ArrowLeft|ArrowUp|Backspace)$/.test(r) ? "before" : "after", l = this._selectionTouchesEditorIcon(e);
        l ? (s.preventDefault(), s.stopPropagation(), this._moveCaretOutsideEditorIcon(e, l, n)) : /^Arrow/.test(r) && i(n);
      }), e.on("mousedown click", i => {
        let s = i && i.target && i.target.closest && i.target.closest(this._editorIconSelector());
        s && (i.preventDefault(), i.stopPropagation(), this._moveCaretOutsideEditorIcon(e, s));
      })), t();
    }
    _bootEditorIconGuards() {
      let t = 0, i = () => {
        let s = e.tinymce;
        if (!s) {
          t++ < 80 && setTimeout(i, 100);
          return;
        }
        s.on && !this._tinymceAddEditorBound && (this._tinymceAddEditorBound = !0, s.on("AddEditor", e => this._installEditorIconGuards(e.editor))),
          Array.isArray(s.editors) && s.editors.forEach(e => this._installEditorIconGuards(e));
      };
      i();
    }
    _isDefaultClass(e) {
      return /(?:^|\s)default(?:\s|$)/.test(String(e || ""));
    }
    _acfFieldIsDefault(e) {
      return !!(e && e.nodeType && (e.classList && e.classList.contains("default") || e.closest && e.closest(".acf-field-link.default, .acf-field.default")));
    }
    _acfCtxIsDefault(e) {
      return e ? this._acfFieldIsDefault(e) : !!(this.defaultLinkContext || this._acfFieldIsDefault(this.ACF_CTX) || this._isDefaultClass(this.current.cls));
    }
    _hideCustomFields() {
      let e = this.qs(this.IDS.wrap);
      e && [".wp-link-style-field", ".wp-link-icon-pos-field", ".wp-link-icon-field"].forEach(t => {
        let i = e.querySelector(t);
        i && (i.style.display = "none");
      });
    }
    _showCustomFields() {
      let e = this.qs(this.IDS.wrap);
      e && [".wp-link-style-field", ".wp-link-icon-field"].forEach(t => {
        let i = e.querySelector(t);
        i && (i.style.display = "");
      });
      let t = e && e.querySelector(".wp-link-icon-pos-field");
      t && (t.style.display = "none");
    }
    _bindGlobal() {
      let setAcfContext = e => {
        if (!e) return;
        this.ACF_CTX = e, this.defaultLinkContext = this._acfFieldIsDefault(e);
      };
      this.on(document, "mousedown", e => {
        let t = e.target.closest && e.target.closest(".acf-field-link");
        if (t) {
          setAcfContext(t);
          return;
        }
        if (!e.target.closest || !e.target.closest("#wp-link, .media-modal, .media-modal-backdrop")) {
          this.ACF_CTX = null, this.defaultLinkContext = !1;
        }
      }, !0), this.on(document, "focusin", e => {
        let t = e.target.closest && e.target.closest(".acf-field-link");
        t && setAcfContext(t);
      }, !0), this.on(document, "mousedown", e => {
        if (e.target.matches('.acf-field-link .acf-icon[data-name="edit"], .acf-field-link .link-wrap a.button') || e.target.closest && e.target.closest('.acf-field-link .acf-icon[data-name="edit"], .acf-field-link .link-wrap a.button')) {
          let t = e.target.closest(".acf-field-link");
          t && setAcfContext(t);
        }
      }, !0);
    }
    _acfGetExtras(e) {
      if (!e) return null;
      let beforeInput = e.querySelector("input.acf-link-before-icon-input") || e.querySelector("input[type='hidden'][name$='[_before_icon_url]']"),
          afterInput = e.querySelector("input.acf-link-after-icon-input") || e.querySelector("input[type='hidden'][name$='[_after_icon_url]']"),
          classInput = e.querySelector("input.acf-link-class-input") || e.querySelector("input[type='hidden'][name$='[_class]']"),
          preview = e.querySelector(".acf-link-icon-preview"),
          n = e.querySelector(".acf-link-extras-data"),
          seedBefore = n && (n.getAttribute("data-before-icon-url") || (n.getAttribute("data-icon-pos") !== "after" ? n.getAttribute("data-icon-url") : "")) || "",
          seedAfter = n && (n.getAttribute("data-after-icon-url") || (n.getAttribute("data-icon-pos") === "after" ? n.getAttribute("data-icon-url") : "")) || "",
          seedClss = n ? n.getAttribute("data-link-class") : null;
      return {
        beforeIconInput: beforeInput,
        afterIconInput: afterInput,
        classInput: classInput,
        preview: preview,
        seedBeforeUrl: seedBefore,
        seedAfterUrl: seedAfter,
        seedClss: seedClss
      };
    }
    _acfLoadIntoCurrent() {
      let e = this._acfGetExtras(this.ACF_CTX);
      if (!e) return;
      let t = e.classInput && e.classInput.value || e.seedClss || "",
          before = e.beforeIconInput && e.beforeIconInput.value || e.seedBeforeUrl || "",
          after = e.afterIconInput && e.afterIconInput.value || e.seedAfterUrl || "";
      t && (this.current.cls = t);
      if (before) {
        this._setIcon("before", before, /#.+$/.test(before) || this.SPRITE_URL && 0 === before.indexOf(this.SPRITE_URL) ? "sprite" : "img");
      } else {
        this.current.beforeIconHref = "";
      }
      if (after) {
        this._setIcon("after", after, /#.+$/.test(after) || this.SPRITE_URL && 0 === after.indexOf(this.SPRITE_URL) ? "sprite" : "img");
      } else {
        this.current.afterIconHref = "";
      }
    }
    _acfRenderWrapThumb(e) {
      let t = e && e.querySelector(".acf-link .link-wrap");
      if (!t) return;
      this.qsa(".acf-link-icon-thumb", t).forEach(e => e.remove());
      let titleEl = t.querySelector(".link-title");
      if (this.current.beforeIconHref) {
        let i = document.createElement("span");
        i.className = "acf-link-icon-thumb acf-link-icon-thumb--before";
        i.innerHTML = "sprite" === this.current.beforeIconMode ? `<svg width="18" height="18" style="display:block" aria-hidden="true"><use href="${this.escA(this.current.beforeIconHref)}"></use></svg>` : `<img width="18" height="18" style="display:block" alt="" src="${this.escA(this.current.beforeIconHref)}">`;
        titleEl ? titleEl.before(i) : t.insertBefore(i, t.firstChild);
      }
      if (this.current.afterIconHref) {
        let i = document.createElement("span");
        i.className = "acf-link-icon-thumb acf-link-icon-thumb--after";
        i.innerHTML = "sprite" === this.current.afterIconMode ? `<svg width="18" height="18" style="display:block" aria-hidden="true"><use href="${this.escA(this.current.afterIconHref)}"></use></svg>` : `<img width="18" height="18" style="display:block" alt="" src="${this.escA(this.current.afterIconHref)}">`;
        titleEl ? titleEl.after(i) : t.appendChild(i);
      }
    }
    _acfSaveFromCurrent() {
      if (this._acfCtxIsDefault()) return;
      let e = this._acfGetExtras(this.ACF_CTX);
      if (e) {
        e.classInput && (e.classInput.value = this.current.cls || "");
        e.beforeIconInput && (e.beforeIconInput.value = this.current.beforeIconHref || "");
        e.afterIconInput && (e.afterIconInput.value = this.current.afterIconHref || "");
        if (e.preview) {
          let html = "";
          if (this.current.beforeIconHref) {
            html += "sprite" === this.current.beforeIconMode
              ? `<svg style="max-width:100%;max-height:100%;display:block" aria-hidden="true"><use href="${this.escA(this.current.beforeIconHref)}"></use></svg>`
              : `<img style="max-width:100%;max-height:100%;display:block" alt="" src="${this.escA(this.current.beforeIconHref)}">`;
          }
          if (this.current.afterIconHref) {
            html += "sprite" === this.current.afterIconMode
              ? `<svg style="max-width:100%;max-height:100%;display:block" aria-hidden="true"><use href="${this.escA(this.current.afterIconHref)}"></use></svg>`
              : `<img style="max-width:100%;max-height:100%;display:block" alt="" src="${this.escA(this.current.afterIconHref)}">`;
          }
          e.preview.innerHTML = html;
        }
        this._acfRenderWrapThumb(this.ACF_CTX);
      }
    }
    _acfClearAll(e) {
      let t = this._acfGetExtras(e);
      if (!t) return;
      t.beforeIconInput && (t.beforeIconInput.value = "");
      t.afterIconInput && (t.afterIconInput.value = "");
      t.classInput && (t.classInput.value = "");
      t.preview && (t.preview.innerHTML = "");
      let i = e.querySelector(".acf-link .link-wrap");
      i && this.qsa(".acf-link-icon-thumb", i).forEach(e => e.remove());
      let s = e.querySelector(".acf-link-extras-data");
      s && (s.setAttribute("data-before-icon-url", ""), s.setAttribute("data-after-icon-url", ""));
      this.resetCurrent();
      this._renderIconPreview();
    }
    _initAcfLinkField(e) {
      let t = e && (e.nodeType ? e : e.el || e.$el || e);
      if (t && t[0] && t[0].nodeType && (t = t[0]), !t || !t.classList || t.hasAttribute("data-link-extras-inited") || (t.setAttribute("data-link-extras-inited", "1"),
        this._acfCtxIsDefault(t))) return;
      let i = this._acfGetExtras(t);
      if (i) {
        let before = i.beforeIconInput && i.beforeIconInput.value || i.seedBeforeUrl || "";
        let after = i.afterIconInput && i.afterIconInput.value || i.seedAfterUrl || "";
        if (before) {
          this._setIcon("before", before, /#.+$/.test(before) || this.SPRITE_URL && 0 === before.indexOf(this.SPRITE_URL) ? "sprite" : "img");
        }
        if (after) {
          this._setIcon("after", after, /#.+$/.test(after) || this.SPRITE_URL && 0 === after.indexOf(this.SPRITE_URL) ? "sprite" : "img");
        }
        this._acfRenderWrapThumb(t);
      }
      let r = t.querySelector('a[data-name="remove"]');
      r && this.on(r, "click", () => {
        setTimeout(() => this._acfClearAll(t), 0);
      });
    }
    _bootAcf() {
      this.qsa(".acf-field-link").forEach(e => this._initAcfLinkField(e)), e.acf && "function" == typeof e.acf.addAction && (e.acf.addAction("ready_field/type=link", e => this._initAcfLinkField(e)),
        e.acf.addAction("append_field/type=link", e => this._initAcfLinkField(e)));
    }
    _renderIconPreview() {
      let e = '<svg width="24" height="24" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M9 4h6l1.8 3H20a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V9a2 2 0 0 1 2-2h3.2L9 4Zm3 14a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm0-2.2a1.8 1.8 0 1 1 0-3.6 1.8 1.8 0 0 1 0 3.6Z"/></svg>';
      ["before", "after"].forEach(t => {
        let i = document.querySelector(`.sp-link-icon-choice[data-pos="${t}"]`), s = i && i.querySelector(".sp-link-icon-choice__preview"), r = this.current.pos === t, n = this._iconHtml(t, 'style="width:100%;height:100%;display:block"', 'style="width:100%;height:100%;object-fit:contain;display:block"');
        i && (i.classList.toggle("is-active", r), i.classList.toggle("has-icon", !!n)), s && (s.innerHTML = n || e);
      });
    }
    _ensureTextRowShownWith(e) {
      let t = this.qs(this.IDS.text);
      if (!t) return;
      let i = t.closest("div");
      i && (i.style.display = "block"), "string" == typeof e && (t.value = e);
    }
    _applyStyleValue(e) {
      if (!e) return;
      let t = this.current.cls || "";
      if (!t) {
        e.value = "";
        return;
      }
      let i = Array.prototype.some.call(e.options, e => e.value === t);
      if (i) {
        e.value = t;
        return;
      }
      let s = e.querySelector('option[data-custom="1"]');
      s ? (s.value = t, s.textContent = "Custom: " + t) : ((s = document.createElement("option")).dataset.custom = "1",
        s.value = t, s.textContent = "Custom: " + t, e.appendChild(s)), e.value = t;
    }
    _ensureFields() {
      let e = this.qs(this.IDS.wrap);
      if (!e) return;
      let t = this.qs(this.IDS.fieldStyle);
      if (!t) {
        let i = this.qs(this.IDS.text, e)?.closest("div") || e.querySelector(".link-target") || e, s = document.createElement("div");
        s.className = "wp-link-style-field", s.style.marginTop = "5px", s.innerHTML = `<label><span>Link Style</span> <select id="wp-link-style" style="margin-left:1px;width:70%;">${this.CHOICES.map(e => `<option value="${this.escA(e.value)}">${this.escT(e.label)}</option>`).join("")}</select></label>`,
          i.parentNode ? i.parentNode.insertBefore(s, i.nextSibling) : e.appendChild(s), t = this.qs(this.IDS.fieldStyle),
          this.on(t, "input", () => this.current.cls = (t.value || "").trim()), this.on(t, "change", () => this.current.cls = (t.value || "").trim());
      }
      this._applyStyleValue(t);
      this._ensureStylePicker(t), this._syncStylePicker(t);
      let r = this.qs(this.IDS.fieldPos);
      if (!r) {
        let n = document.createElement("div");
        n.className = "wp-link-icon-pos-field", n.style.display = "none", n.innerHTML = '<label><span>Icon Position</span> <select id="wp-link-icon-pos"><option value="before">Before text</option><option value="after">After text</option></select></label>';
        let l = this.qs(this.IDS.fieldStyle)?.closest("div") || e;
        l.parentNode ? l.parentNode.insertBefore(n, l.nextSibling) : e.appendChild(n), r = this.qs(this.IDS.fieldPos);
        let o = () => this.current.pos = "after" === r.value ? "after" : "before";
        this.on(r, "input", () => {
          o(), this._syncStylePicker(t);
        }), this.on(r, "change", () => {
          o(), this._syncStylePicker(t);
        });
      }
      if (r.value = this.current.pos, !document.getElementById(this.IDS.btnBeforeIcon)) {
        let c = document.createElement("div");
        c.className = "wp-link-icon-field", c.style.marginTop = "5px", c.innerHTML = `<div><div class="sp-link-icon-choices"><div class="sp-link-icon-choice-wrapper"><div class="sp-link-icon-choice__title-label">Before Text Icon</div><button type="button" class="sp-link-icon-choice" id="${this.IDS.btnBeforeIcon}" data-pos="before"><span class="sp-link-icon-choice__remove" data-clear-pos="before" aria-label="Remove before icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path fill="currentColor" d="M17.7 5.2a.8.8 0 1 1 1 1.1L6.4 18.8a.8.8 0 0 1-1-1.1z"/><path fill="currentColor" d="M5.2 5.2q.6-.5 1.1 0l12.5 12.5a.8.8 0 0 1-1.1 1L5.2 6.4a1 1 0 0 1 0-1"/></svg></span><span class="sp-link-icon-choice__preview"></span></button></div><div class="sp-link-icon-choice-wrapper"><div class="sp-link-icon-choice__title-label">After Text Icon</div><button type="button" class="sp-link-icon-choice" id="${this.IDS.btnAfterIcon}" data-pos="after"><span class="sp-link-icon-choice__remove" data-clear-pos="after" aria-label="Remove after icon"><svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24"><path fill="currentColor" d="M17.7 5.2a.8.8 0 1 1 1 1.1L6.4 18.8a.8.8 0 0 1-1-1.1z"/><path fill="currentColor" d="M5.2 5.2q.6-.5 1.1 0l12.5 12.5a.8.8 0 0 1-1.1 1L5.2 6.4a1 1 0 0 1 0-1"/></svg></span><span class="sp-link-icon-choice__preview"></span></button></div></div></div>`;
        let a = this.qs(this.IDS.fieldPos)?.closest("div") || e;
        a.parentNode ? a.parentNode.insertBefore(c, a.nextSibling) : e.appendChild(c);
        let h = document.getElementById(this.IDS.btnBeforeIcon), u = document.getElementById(this.IDS.btnAfterIcon), p = document.getElementById(this.IDS.btnClear);
        [h, u].forEach(e => this.on(e, "click", i => {
          let s = i.target && i.target.closest && i.target.closest("[data-clear-pos]");
          if (s) return i.preventDefault(), i.stopPropagation(), this._clearIcon(s.getAttribute("data-clear-pos")),
            this._renderIconPreview(), this._syncStylePicker(t), void (this.ACF_CTX && this._acfSaveFromCurrent());
          this._pickIcon(i, e && e.getAttribute("data-pos"));
        })), this.on(p, "click", () => {
          this._clearIcon("before"), this._clearIcon("after"), this._renderIconPreview(), this._syncStylePicker(t),
            this.ACF_CTX && this._acfSaveFromCurrent();
        });
      }
      this._acfCtxIsDefault() ? (this._hideCustomFields(), this._hideSettingsDrawer()) : (this._showCustomFields(),
        this._renderIconPreview(), this._syncStylePicker(t), this._ensureSettingsDrawer());
    }
    _nodeHasLinkIcon(e) {
      return !!(e && 1 === e.nodeType && (e.matches && (e.matches(this._editorIconSelector()) || e.matches("svg") || e.matches(".sprite") || e.matches("img")) || e.querySelector && (e.querySelector(this._editorIconSelector()) || e.querySelector("svg,img") || e.querySelector(".sprite"))));
    }
    _readLinkIconNode(e, t) {
      if (!e) return;
      let i = e.matches && e.matches("img") ? e : e.querySelector && e.querySelector("img"), s = e.matches && e.matches("svg") ? e.querySelector("use") : e.querySelector && e.querySelector("svg use");
      if (s) {
        let r = s.getAttribute("href") || s.getAttribute("xlink:href") || "";
        r && this._setIcon(t, r, "sprite");
      } else if (i) {
        let n = i.getAttribute("src") || "";
        n && this._setIcon(t, n, "img");
      }
    }
    _detectIconPosFromDom(e) {
      let t = (e.getAttribute("data-icon-pos") || "").toLowerCase();
      if ("before" === t || "after" === t) return t;
      let i = Array.prototype.slice.call(e.childNodes || []), s = i.findIndex(e => this._nodeHasLinkIcon(e)), r = i.findIndex(e => this.visibleText(e.textContent || ""));
      return -1 !== s && (-1 === r || s < r) ? "before" : "after";
    }
    _isMainButtonClass(e) {
      return /(?:^|\s)main-button(?:\s|$)/.test(String(e || ""));
    }
    _linkTextHtml(e) {
      let t = this.escT(e || "");
      return this._isMainButtonClass(this.current.cls) && t ? `<span class="main-button__text">${t}</span>` : t;
    }
    _syncFromSelection() {
      this.ACF_CTX && !document.documentElement.contains(this.ACF_CTX) && (this.ACF_CTX = null);
      this.defaultLinkContext = this._acfFieldIsDefault(this.ACF_CTX);
      this.resetCurrent(), this.ACF_CTX && this._acfLoadIntoCurrent();
      let editor = window.tinymce && window.tinymce.activeEditor;
      if (editor && editor.getDoc()) {
        this.existingEditorLinks = Array.from(editor.getDoc().querySelectorAll("a[href]"));
      } else {
        this.existingEditorLinks = [];
      }

      let activeLinkNode = this.getActiveLink(), t = !1;
      this.lastActiveLink = activeLinkNode || null;
      if (activeLinkNode) {
        this.current.cls = activeLinkNode.getAttribute("class") || this.current.cls, this.current.pos = this._detectIconPosFromDom(activeLinkNode);
        this.defaultLinkContext = this.defaultLinkContext || this._isDefaultClass(this.current.cls);
        let i = Array.prototype.filter.call(activeLinkNode.childNodes, child => this._nodeHasLinkIcon(child));
        1 === i.length ? this._readLinkIconNode(i[0], this.current.pos) : i.length > 1 && (this._readLinkIconNode(i[0], "before"), this._readLinkIconNode(i[i.length - 1], "after"));
        var l = activeLinkNode.cloneNode(!0);
        this.qsa(`${this._editorIconSelector()},svg,img,.sprite,[data-link-caret="1"]`, l).forEach(child => child.remove()), t = this.visibleText(l.textContent || "");
      }
      this._ensureFields();
      let o = this.qs(this.IDS.fieldStyle);
      this._applyStyleValue(o);
      let c = this.qs(this.IDS.fieldPos);
      c && (c.value = this.current.pos), this._ensureTextRowShownWith(t);
    }
    _pickIcon(t, i = null) {
      if (this._acfCtxIsDefault()) return;
      t && (t.preventDefault(), t.stopPropagation());
      i && (this.current.pos = "after" === i ? "after" : "before");
      this.pendingIconPos = this.current.pos;
      let s = this.qs(this.IDS.fieldPos);
      s && (s.value = this.current.pos);
      this._renderIconPreview();
      let r = e.top && e.top.wp && e.top.wp.media ? e.top.wp : e.wp && e.wp.media ? e.wp : null;
      if (!r || !r.media) return;

      if (this.mediaFrame) {
        this.mediaFrame.open();
        return;
      }

      let n = r.media.query({
        type: "image"
      });
      let l = this.FAVORITE_IDS.length ? r.media.query({
        include: this.FAVORITE_IDS,
        orderby: "post__in",
        type: "image"
      }) : null;
      let o = [new r.media.controller.Library({
        id: "sp-link-media-library",
        title: "Media Library",
        priority: 20,
        toolbar: "select",
        library: n,
        multiple: !1,
        content: "browse",
        filterable: "all",
        searchable: !0,
        displaySettings: !0,
        displayUserSettings: !0
      })];
      l && o.push(new r.media.controller.Library({
        id: "sp-link-ui-assets",
        title: "UI Assets",
        priority: 30,
        toolbar: "select",
        library: l,
        multiple: !1,
        content: "browse",
        filterable: !1,
        searchable: !0,
        displaySettings: !1,
        displayUserSettings: !1
      }));

      this.mediaFrame = new r.media({
        frame: "select",
        title: "Choose Icon or Image",
        button: {
          text: "Use icon / image"
        },
        multiple: !1,
        state: "sp-link-media-library",
        states: o
      });

      this.mediaFrame.el && this.mediaFrame.el.classList && this.mediaFrame.el.classList.add("sp-link-icon-media-frame");
      this.mediaFrame.on("open", () => document.body.classList.add("sp-link-icon-media-open")),
      this.mediaFrame.on("close", () => {
        document.body.classList.remove("sp-link-icon-media-open"), this.pendingIconPos = null;
      }),
      this.mediaFrame.on("select", () => {
        let t = this.mediaFrame.state().get("selection").first()?.toJSON();
        if (!t) return;
        let a = this.pendingIconPos || this.current.pos, i = t.url || "", s = /\.svg(?:\?.*)?$/i.test(i);
        if (s && this.SPRITE_URL) {
          let r = e.UIA_LINKPICKER && UIA_LINKPICKER.byId && t.id && UIA_LINKPICKER.byId[t.id] ? UIA_LINKPICKER.byId[t.id] : "";
          r ? this._setIcon(a, this.SPRITE_URL + "#icon-" + r, "sprite") : this._setIcon(a, i, "img");
        } else this._setIcon(a, i, "img");
        this.pendingIconPos = null;
        let l = this.qs(this.IDS.fieldStyle);
        this._renderIconPreview(), this._syncStylePicker(l), this.ACF_CTX && this._acfSaveFromCurrent(),
        this.mediaFrame.close();
      });

      this.mediaFrame.open();
    }
    _buildInnerHtml(e) {
      let t = this._editorIconHtml("before"), i = this._editorIconHtml("after");
      if (e) return `${t}${this._linkTextHtml(e)}${i}`;
      {
        let s = '<span data-link-caret="1" style="position:absolute;inset:0;display:inline-block;width:0;height:0;overflow:hidden;line-height:0;font-size:0;">&nbsp;</span>';
        return `${t || ""}${s}${i || ""}`;
      }
    }
    _buildAnchorHtml(e) {
      let t = e.href || "#", i = e.title || "", s = e.rel || "", r = e.target || "", n = (this.current.cls || "").trim(), l = this.qs(this.IDS.text), o = l ? l.value : "", c = this.visibleText(o), a = this._acfCtxIsDefault();
      if (!a && !c && !this._hasAnyIcon()) return alert("Add text or select an icon."),
        null;
      let h = `<a href="${this.escA(t)}"`;
      return i && (h += ` title="${this.escA(i)}"`), r && (h += ` target="${this.escA(r)}"`),
        s && (h += ` rel="${this.escA(s)}"`), n && (h += ` class="${this.escA(n)}"`), a || (h += ` data-icon-pos="${this.escA(this.current.pos)}"`),
        h += ">", h += a ? this.escT(c) : this._buildInnerHtml(c), h += "</a>";
    }
    _normalizeLinkContentInVisual(e, t) {
      if (!t) return;
      let i = this.qs(this.IDS.text), s = this.visibleText(i ? i.value : ""), r = this._acfCtxIsDefault();
      if (!r && !s && !this._hasAnyIcon()) return;
      let n = (this.current.cls || "").trim();
      r ? (t.innerHTML = this.escT(s), e.dom && e.dom.setAttrib ? (e.dom.setAttrib(t, "class", n || null),
        e.dom.removeAttrib && e.dom.removeAttrib(t, "data-icon-pos")) : (n ? t.setAttribute("class", n) : t.removeAttribute("class"),
          t.removeAttribute("data-icon-pos"))) : (t.innerHTML = this._buildInnerHtml(s), e.dom && e.dom.setAttrib ? (e.dom.setAttrib(t, "class", n || null),
            e.dom.setAttrib(t, "data-icon-pos", this.current.pos)) : (n ? t.setAttribute("class", n) : t.removeAttribute("class"),
              t.setAttribute("data-icon-pos", this.current.pos)), this.ACF_CTX && this._acfSaveFromCurrent());
    }
    _patchWpLinkOpenRefresh() {
      if (e.wpLink && (["setDefaultValues", "refresh"].forEach(t => {
        if ("function" == typeof e.wpLink[t]) {
          let i = e.wpLink[t];
          e.wpLink[t] = (...t) => {
            let s = i.apply(e.wpLink, t);
            return setTimeout(() => {
              this._ensureFields(), this._syncFromSelection();
            }, 0), s;
          };
        }
      }), "function" == typeof e.wpLink.getAttrs)) {
        let t = e.wpLink.getAttrs;
        e.wpLink.getAttrs = (...i) => {
          let s = t.apply(e.wpLink, i) || {};
          if (this._acfCtxIsDefault()) {
            return this.current.cls ? (s.class = this.current.cls, s.className = this.current.cls, s) : (delete s.class,
              delete s.className, s);
          }
          return this.current.cls ? (s.class = this.current.cls, s.className = this.current.cls) : (delete s.class,
            delete s.className), s;
        };
      }
    }
    _clickPrimaryHandler(event) {
      let primaryBtn = event.target.closest && event.target.closest(this.IDS.primary);
      if (!primaryBtn) return;
      let s = primaryBtn.id || "";
      if (s !== this.IDS.btnChoose && s !== this.IDS.btnClear) try {
        let r = "function" == typeof window.wpLink.isMCE && window.wpLink.isMCE();
        if (r) {
          setTimeout(() => {
            let editor = window.tinymce && window.tinymce.activeEditor;
            if (!editor) return;
            let targetLink = null;
            if (editor.selection && editor.selection.getNode) {
              targetLink = editor.dom ? editor.dom.getParent(editor.selection.getNode(), "a[href]") : null;
            }
            if (!targetLink && this.lastActiveLink && editor.getDoc().contains(this.lastActiveLink)) {
              targetLink = this.lastActiveLink;
            }
            if (!targetLink && this.existingEditorLinks) {
              let currentLinks = Array.from(editor.getDoc().querySelectorAll("a[href]"));
              let newLinks = currentLinks.filter(el => !this.existingEditorLinks.includes(el));
              if (newLinks.length === 1) {
                targetLink = newLinks[0];
              } else if (newLinks.length > 1) {
                let urlInput = document.getElementById("wp-link-url");
                let targetUrl = urlInput ? urlInput.value.trim() : "";
                targetLink = newLinks.find(el => (el.getAttribute("href") || "").trim() === targetUrl) || newLinks[0];
              }
            }
            if (!targetLink) {
              let urlInput = document.getElementById("wp-link-url");
              let targetUrl = urlInput ? urlInput.value.trim() : "";
              if (targetUrl) {
                let matchingLinks = Array.from(editor.getDoc().querySelectorAll("a[href]")).filter(el => (el.getAttribute("href") || "").trim() === targetUrl);
                if (matchingLinks.length === 1) {
                  targetLink = matchingLinks[0];
                }
              }
            }
            if (!targetLink) {
              let allLinks = editor.getDoc().querySelectorAll("a[href]");
              if (allLinks.length === 1) {
                targetLink = allLinks[0];
              }
            }
            if (!targetLink) return;

            let clsVal = (this.current.cls || "").trim();
            editor.dom && editor.dom.setAttrib ? (editor.dom.setAttrib(targetLink, "class", clsVal || null), editor.dom.setAttrib(targetLink, "data-icon-pos", this.current.pos)) : (clsVal ? targetLink.setAttribute("class", clsVal) : targetLink.removeAttribute("class"),
              targetLink.setAttribute("data-icon-pos", this.current.pos)), this._normalizeLinkContentInVisual(editor, targetLink);
          }, 0);
          return;
        }
        event.preventDefault();
        let n = "function" == typeof window.wpLink.getAttrs && window.wpLink.getAttrs() || {}, l = this._buildAnchorHtml(n);
        if (!l) return;
        "function" == typeof window.wpLink.replaceHtml && window.wpLink.replaceHtml(l), "function" == typeof window.wpLink.close && window.wpLink.close(),
          this.ACF_CTX && this._acfSaveFromCurrent();
      } catch (o) { }
    }
    _attachGlobalListeners() {
      this.on(document, "click", e => this._clickPrimaryHandler(e), !0), ["mouseup", "keyup", "input"].forEach(e => {
        this.on(document, e, t => {
          let i = this.qs(this.IDS.wrap);
          if (!i || !this.modalVisible()) return;
          if ((e === "input" || e === "keyup") && t.target && t.target.matches && t.target.matches(this.IDS.text)) {
            this._scheduleStylePickerSync(this.qs(this.IDS.fieldStyle), 450);
            return;
          }
          this._ensureFields();
        });
      });
      let e = this.qs(this.IDS.wrap);
      if (e && !this._observer) {
        let t = this.modalVisible();
        this._observer = new MutationObserver(() => {
          let e = this.modalVisible();
          if (e && !t) {
            setTimeout(() => this._onWpLinkOpen(), 0);
          } else if (!e && t) {
            this._onWpLinkClose();
          }
          t = e;
        }), this._observer.observe(e, {
          attributes: !0,
          attributeFilter: ["style", "class"]
        });
      }
    }
    _onWpLinkOpen() {
      if (this.ACF_CTX) {
        let e = this._acfGetExtras(this.ACF_CTX), t = e && (e.iconInput && e.iconInput.value || e.seedUrl);
        t || (this.resetCurrent(), this._renderIconPreview());
      }
      this._syncFromSelection();
    }
    _onWpLinkClose() {
      this.ACF_CTX = null;
      this.defaultLinkContext = !1;
      this.lastActiveLink = null;
      this.existingEditorLinks = [];
      this._hideSettingsDrawer();
    }
    _installPatches() {
      return !!e.wpLink && (!!this._installed || (this._installed = !0, this._patchWpLinkOpenRefresh(),
        this._attachGlobalListeners(), !0));
    }
    _waitWpLink() {
      this._installPatches() || setTimeout(() => this._waitWpLink(), 50);
    }
  }({
    choices: e.ACF_LINK_EXTRAS && Array.isArray(e.ACF_LINK_EXTRAS.choices) ? e.ACF_LINK_EXTRAS.choices : []
  });
}(window);

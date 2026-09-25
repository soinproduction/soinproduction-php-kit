import { fadeIn, fadeOut } from "@soinproduction/kit/src/functions/customFunctions";
import { loaderInstanse } from "@soinproduction/kit/src/functions/scripts/loaderInstanse";
const onDomReady = callback => {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', callback, {once: true});
    } else {
        callback();
    }
};

onDomReady(() => {
    "use strict";

    const modalManager = window.modalManager || null;

    const LOADER_OFF_DELAY = 400;
    const MODAL_AUTOCLOSE_DELAY = 3000;
    const MESSAGE_RESET_DELAY = 5000;
    const messageResetTimers = new WeakMap();
    const successMessageResets = new WeakMap();

    function getActionType(wrap) {
        const raw = String(wrap?.dataset?.actionType || "").trim().toLowerCase();
        if (raw === "redirect") return "redirect";
        if (raw === "modal") return "modal";
        if (raw === "message") return "message";
        return "default";
    }

    function getRedirectUrl(wrap) {
        return String(wrap?.dataset?.redirectUrl || "").trim();
    }

    function getSuccessModalId(wrap) {
        return String(wrap?.dataset?.successModal || "").trim();
    }

    function getErrorModalId(wrap) {
        return String(wrap?.dataset?.errorModal || "").trim();
    }

    function setLoader(wrap, state) {
        if (!wrap) return;
        loaderInstanse(wrap, !!state);
    }

    function closeAllModalsSafe() {
        if (!modalManager) return;
        modalManager.closeAllModals();
    }

    function openModalSafe(id) {
        if (!modalManager || !id) return;
        modalManager.openModal(`modal_${id}`);
    }

    function delayed(fn, ms) {
        return window.setTimeout(() => {
            try {
                fn();
            } catch (_) {}
        }, ms);
    }

    function findSubmitButtons(wrap) {
        if (!wrap) return [];
        const nodes = wrap.querySelectorAll('button[type="submit"], input[type="submit"]');
        return Array.prototype.slice.call(nodes);
    }

    function bindSubmitStartLoader(wrap) {
        const btns = findSubmitButtons(wrap);
        if (!btns.length) return;

        btns.forEach((btn) => {
            if (btn.dataset.loaderBind === "1") return;
            btn.dataset.loaderBind = "1";

            btn.addEventListener("click", () => {
                setLoader(wrap, true);
            });
        });

        const form = wrap.querySelector("form");
        if (form && form.dataset.loaderSubmitBind !== "1") {
            form.dataset.loaderSubmitBind = "1";
            form.addEventListener("submit", () => {
                setLoader(wrap, true);
            });
        }
    }

    function switchMessageStep(wrap, messageType) {
        if (getActionType(wrap) !== "message") return false;

        const box = wrap.closest("[data-cf7-message-wrapper]");
        if (!box) return false;

        const formStep = box.querySelector("[data-cf7-message-form]");
        const successStep = box.querySelector('[data-cf7-message="success"]');
        const errorStep = box.querySelector('[data-cf7-message="error"]');
        const activeStep = messageType === "success" ? successStep : errorStep;
        const inactiveStep = messageType === "success" ? errorStep : successStep;

        if (!activeStep) return false;

        const previousTimer = messageResetTimers.get(box);
        if (previousTimer) window.clearTimeout(previousTimer);

        if (formStep) fadeOut(formStep, 0);
        if (inactiveStep) fadeOut(inactiveStep, 0);
        fadeIn(activeStep, 500, "flex");

        const resetTimer = delayed(() => {
            fadeOut(activeStep, 0);
            if (formStep) fadeIn(formStep, 500, "flex");
            messageResetTimers.delete(box);
        }, MESSAGE_RESET_DELAY);

        messageResetTimers.set(box, resetTimer);
        return true;
    }

    function showSuccessMessage(wrap) {
        const box = wrap.closest('[data-cf7-message-wrapper]');
        const selector = box?.dataset.cf7MessageTarget;
        if (!selector) return false;
        let target;
        try { target = document.querySelector(selector); } catch { return false; }
        if (!target || target.contains(wrap) || wrap.contains(target)) return false;

        successMessageResets.get(wrap)?.();
        const message = box.querySelector('[data-cf7-message="success"]');
        if (!message || message.contains(target)) return false;
        const originalContent = document.createDocumentFragment();
        while (target.firstChild) originalContent.append(target.firstChild);
        const parent = message.parentNode;
        const next = message.nextSibling;
        const previousTimer = messageResetTimers.get(box);
        if (previousTimer) window.clearTimeout(previousTimer);
        messageResetTimers.delete(box);
        const error = box.querySelector('[data-cf7-message="error"]');
        const formStep = box.querySelector('[data-cf7-message-form]');
        if (error) fadeOut(error, 0);
        if (formStep) fadeIn(formStep, 0, 'block');
        target.append(message);
        fadeIn(message, 300, 'block');

        let timer;
        const reset = () => {
            window.clearTimeout(timer);
            fadeOut(message, 0);
            parent.insertBefore(message, next);
            target.replaceChildren(originalContent);
            successMessageResets.delete(wrap);
        };
        successMessageResets.set(wrap, reset);
        timer = delayed(reset, MESSAGE_RESET_DELAY);
        return true;
    }

    function handleInvalid(wrap) {
        delayed(() => setLoader(wrap, false), 200);
    }

    function handleMailFailed(wrap) {
        successMessageResets.get(wrap)?.();
        const action = getActionType(wrap);

        if (action === "message") {
            setLoader(wrap, false);
            switchMessageStep(wrap, "error");
            return;
        }

        if (action === "modal") {
            const errorId = getErrorModalId(wrap);
            if (!errorId) {
                delayed(() => setLoader(wrap, false), 200);
                return;
            }

            closeAllModalsSafe();
            setLoader(wrap, true);

            delayed(() => {
                setLoader(wrap, false);

                delayed(() => {
                    openModalSafe(errorId);
                    delayed(() => closeAllModalsSafe(), MODAL_AUTOCLOSE_DELAY);
                }, LOADER_OFF_DELAY);
            }, LOADER_OFF_DELAY);

            return;
        }

        delayed(() => setLoader(wrap, false), 200);
    }

    function handleMailSent(wrap) {
        if (showSuccessMessage(wrap)) {
            wrap.querySelector('form')?.reset();
            setLoader(wrap, false);
            return;
        }
        const action = getActionType(wrap);

        if (action === "message") {
            const form = wrap.querySelector("form");
            if (form) form.reset();

            setLoader(wrap, false);
            switchMessageStep(wrap, "success");
            return;
        }

        if (action === "redirect") {
            const url = getRedirectUrl(wrap);
            if (!url) {
                delayed(() => setLoader(wrap, false), 200);
                return;
            }

            setLoader(wrap, true);
            delayed(() => {
                window.location.href = url;
            }, 50);

            return;
        }

        if (action === "modal") {
            const successId = getSuccessModalId(wrap);
            if (!successId) {
                delayed(() => setLoader(wrap, false), 200);
                return;
            }

            closeAllModalsSafe();
            setLoader(wrap, true);

            delayed(() => {
                setLoader(wrap, false);

                delayed(() => {
                    openModalSafe(successId);
                    delayed(() => closeAllModalsSafe(), MODAL_AUTOCLOSE_DELAY);
                }, LOADER_OFF_DELAY);
            }, LOADER_OFF_DELAY);

            return;
        }

        delayed(() => setLoader(wrap, false), 200);
    }

    document.querySelectorAll(".wpcf7").forEach((wrap) => {
        if (wrap.dataset.loaderInit === "1") return;
        wrap.dataset.loaderInit = "1";

        if (!wrap.hasAttribute("data-loader")) {
            wrap.setAttribute("data-loader", "false");
        } else {
            wrap.setAttribute("data-loader", String(wrap.getAttribute("data-loader") || "false"));
        }

        bindSubmitStartLoader(wrap);

        wrap.addEventListener("wpcf7invalid", () => handleInvalid(wrap), false);
        wrap.addEventListener("wpcf7spam", () => handleMailFailed(wrap), false);
        wrap.addEventListener("wpcf7mailfailed", () => handleMailFailed(wrap), false);
        wrap.addEventListener("wpcf7mailsent", () => handleMailSent(wrap), false);
        wrap.addEventListener("wpcf7submit", (event) => {
            const status = String(event?.detail?.status || "").toLowerCase();
            if (status === "aborted" || status === "payment_required") {
                handleMailFailed(wrap);
            }
        }, false);
    });

    if (modalManager && typeof modalManager.on === "function") {
        modalManager.on("beforeClose", ({ modal }) => {
            if (!modal) return;
            modal.querySelectorAll(".wpcf7").forEach((wrap) => setLoader(wrap, false));
        });
    }
});

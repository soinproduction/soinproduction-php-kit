# UL Align Redirect

[Back to MCE plugins](../README.md)

## RU

### Что делает

Исправляет поведение align-команд TinyMCE для списков, чтобы выравнивание работало на самом списке и не ломало caret.

### Как работает

- `index.php` регистрирует TinyMCE-плагин и отдает `script.js` через `?sp_ul_align_redirect_js=1`.
- `script.js` перехватывает команды `JustifyLeft`, `JustifyCenter`, `JustifyRight`, `JustifyFull`.
- Если caret находится внутри `ul` или `ol`, плагин применяет `text-align` к корню списка.
- После списка вставляется/поддерживается paragraph, чтобы пользователь мог продолжить ввод без застревания caret.

### Особенность

Плагин обычно работает без отдельной toolbar-кнопки и нужен как фоновый behavior fix.

## EN

### What it does

Fixes TinyMCE alignment behavior for lists so alignment is applied to the list root and the caret remains usable.

### How it works

- `index.php` registers the TinyMCE plugin and serves `script.js` through `?sp_ul_align_redirect_js=1`.
- `script.js` intercepts `JustifyLeft`, `JustifyCenter`, `JustifyRight`, and `JustifyFull`.
- If the caret is inside a `ul` or `ol`, the plugin applies `text-align` to the list root.
- A paragraph after the list is inserted/maintained so typing can continue normally.

### Notes

This plugin usually runs without a visible toolbar button and acts as a background behavior fix.

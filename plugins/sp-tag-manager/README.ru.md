# Tag Manager

Единый pipeline для Google Tag Manager, Consent Mode v2, лёгкого first-party consent prompt и trusted snippets. Модуль управляет местом/временем output и загрузкой GTM, но не настраивает сам container в Google.

## Lifecycle output

| Hook | Priority | Что выводится |
| --- | ---: | --- |
| `wp_head` | `1` | Consent bootstrap, GTM loader/preconnect, custom head. |
| `wp_body_open` | `1` | Conditional noscript iframe и body-open snippet. |
| `wp_footer` | `100` | Cookie prompt/script и footer snippet. |

Тема обязана вызвать `wp_body_open()` сразу после `<body>`. Без него head loader работает, но body/noscript отсутствует.

## Eligibility

При выключенном `enabled` нет ничего. Также пропускаются feeds, robots, trackbacks и embeds. По умолчанию output выключен для logged-in users и admin/login.

```php
add_filter('sp_tag_manager_should_output', function (bool $output, array $cfg): bool {
    return is_page('privacy-preview') ? false : $output;
}, 10, 2);
```

Проверяйте logged out: suppression авторизованного администратора — самая частая причина “не работает”.

## Storage и defaults

Опция `sp_tag_manager_cfg` сохраняется с autoload=false.

| Ключ | Default | Проверка / эффект |
| --- | ---: | --- |
| `enabled` | `1` | Master switch. |
| `disable_for_logged` | `1` | Нет output для authenticated. |
| `disable_on_admin` | `1` | Нет output в admin/login. |
| `gtm_enabled` | `1` | Loader при valid ID. |
| `gtm_id` | пусто | Только `GTM-[A-Z0-9]+`. |
| `gtm_data_layer` | `dataLayer` | Valid JS identifier. |
| `gtm_strategy` | `after_interaction` | `immediate`, `after_delay`, `after_interaction`. |
| `gtm_delay_ms` | `2500` | 0–15000 мс. |
| `consent_mode_enabled` | `1` | Consent bootstrap перед GTM. |
| `consent_default` | `denied` | `granted` или `denied`. |
| `consent_wait_ms` | `500` | 0–5000 мс. |
| `consent_cookie_sync` | `1` | Применяет existing cookie. |
| `consent_cookie_key` | `sp_cookie_consent` | 2–80 букв/цифр/`_`/`-`. |
| grant/deny values | `granted`/`denied` | 1–80 safe chars. |
| `cookie_modal_enabled` | `1` | Built-in prompt. |
| modal text | English default | Sanitized post HTML. |
| labels/classes | Accept/Reject/empty | Text и sanitized class list. |
| custom snippets | пусто | Restricted HTML allowlist. |

## GTM strategies

### Immediate

Инициализирует data layer, push `{gtm.start,event:'gtm.js'}` и async вставляет `gtm.js` в head. Используйте, когда раннее измерение важнее performance/privacy tradeoff.

### After Delay

Создаёт `window.spLoadGtm`, затем грузит через `gtm_delay_ms`. Ноль запускает сразу. Ручной ранний вызов безопасен — duplicate блокируется.

### After Interaction

Ждёт первый `pointerdown`, `touchstart`, `mousemove`, `keydown` или `scroll`. Если interaction не было, через 10 секунд после `window.load` срабатывает fallback. Эти 10 секунд не зависят от `gtm_delay_ms`.

Во всех режимах есть preconnect к Google. Custom data layer добавляется параметром `l`.

## Consent Mode v2

Bootstrap создаёт array и `window.gtag`, затем до GTM вызывает consent default для `ad_storage`, `analytics_storage`, `ad_user_data`, `ad_personalization` и `wait_for_update`. Built-in controls меняют четыре состояния вместе; granular CMP может вызвать custom update.

```js
window.spTagConsentGrantAll();
window.spTagConsentDenyAll();
window.spTagConsentUpdate({analytics_storage:'granted', ad_storage:'denied'});

document.dispatchEvent(new CustomEvent('sp:consent:update', {
  detail: {state: 'granted'}
}));

document.dispatchEvent(new CustomEvent('sp:consent:update', {
  detail: {values: {analytics_storage: 'granted'}}
}));
```

Listener висит на `document`, не `window`, и принимает string, `detail.state` или `detail.values`. Cookie sync применяет только точное grant/deny value; неизвестное сохраняет default.

## Noscript

Iframe выводится только при enabled GTM, valid ID и отсутствии strict default-denied Consent Mode. При consent enabled + denied он намеренно исключён: noscript request не умеет дождаться JS update. Это ожидаемое поведение.

## Cookie prompt

Standalone `role=dialog` в footer, не theme modal. Он показывается при enabled, непустом text и отсутствии cookie. Выбор:

1. пишет value на год с `path=/; SameSite=Lax`;
2. вызывает grant/deny helper;
3. dispatch-ит `sp:consent:update` на `document`;
4. скрывает dialog.

Cookie не `Secure`/`HttpOnly`, потому что его читает JS. После смены key/value старый cookie не совпадёт. Built-in reopen preferences нет — отдельный privacy UI должен изменить cookie и отправить update.

Это техническая инфраструктура, не юридическая консультация. Copy, categories и proof of consent проверяются под юрисдикции проекта.

## Custom snippets

Allowlist разрешает ограниченные `script`, `noscript`, `iframe`, `img`, `link`, `meta`, `style`, `a`, `div`, `span` и конкретные attributes. Inline events и неизвестные элементы удаляются `wp_kses()`.

- Head: verification/meta/preconnect/bootstrap.
- Body open: noscript/vendor body-start.
- Footer: chat, delayed pixels, non-critical scripts.

Вставлять следует только trusted code. Если vendor syntax удаляется, лучше reviewed code integration, а не ослабление sanitizer.

## Security

Ajax `sp_tag_manager_save` требует nonce `sp_tag_manager_admin` и `manage_options`. ID, JS identifier, timing, cookies, classes, text и snippets очищаются сервером перед `update_option(..., false)`.

## Проверка

1. Сохранить valid `GTM-...`.
2. Открыть clean logged-out window.
3. Убедиться, что consent стоит раньше loader.
4. В delayed mode проверить отсутствие network request до trigger.
5. Отдельно Accept/Reject, cookie и dataLayer commands.
6. Reload: sync применяет choice, prompt скрыт.
7. Проверить no-JS/noscript policy.
8. Проверить GTM Preview/Tag Assistant и Network.

## Диагностика

- **Нет output:** master, logged-in suppression, request type, filter.
- **ID исчез:** принимается только GTM container ID, не GA measurement ID.
- **GTM не грузится:** interaction/`spLoadGtm()`, CSP/ad blocker, 10-second fallback.
- **Delay не влияет:** он только для `after_delay`.
- **Нет noscript:** ожидаемо при denied Consent; иначе проверить `wp_body_open()`.
- **Event ignored:** dispatch на `document`, правильный detail.
- **Prompt не возвращается:** удалить configured cookie.
- **Snippet меняется:** markup вне allowlist.
- **Double tracking:** удалить другие GTM integrations.
- **Старый output:** очистить page/CDN cache.

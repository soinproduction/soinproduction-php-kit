# TODO

- [ ] Extract the generic CF7 frontend message controller from the theme into `sp-cf7-messages`: support `message_target`, preserve/restore target DOM nodes, reset timers on repeated submissions and retain existing default form-message behavior. Load only one controller per page; currently the kit emits the attribute and the theme implements the behavior.
- [ ] Add browser integration coverage for Interactive Map: native ACF nested fields and repeated save/reorder, multiple maps with distinct tooltip values, keyboard focus, zoom gestures in Safari and Chromium, and viewport/shadow clipping.
- [ ] Document reusable Tailwind aliases/design tokens for kit frontend modules independently of individual themes.

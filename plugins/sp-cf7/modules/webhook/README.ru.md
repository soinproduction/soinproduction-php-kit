# SP CF7 Webhook

Добавляет каждой форме Contact Form 7 панель синхронной Webhook-доставки POST, PUT или PATCH с headers, mapping полей и опциональной metadata запроса.

Timeout составляет 15 секунд, очереди и retry нет. Transport/HTTP errors сохраняются как status text, но не делают отправку CF7 невалидной. Authentication headers хранятся как plain-text post meta; выключайте debug вне короткой диагностики и не передавайте лишние персональные данные.

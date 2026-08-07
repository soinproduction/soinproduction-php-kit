# SP Flowchimp

Связывает формы Contact Form 7 с Mailchimp audiences и синхронно выполняет member upsert до отправки notification email CF7.

Настройки хранятся в `sp_flowchimp_options`, включая API key в plain text. Значение `pending` по умолчанию включает double opt-in. Remote failures логируются, но не делают отправку формы невалидной; опция «Skip CF7 mail» может убрать fallback email даже при ошибке Mailchimp.

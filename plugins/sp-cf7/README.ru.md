# SP CF7

Единый набор переиспользуемых интеграций Contact Form 7. Подключается именем `sp-cf7` в списке `plugins` PHP Kit.

Каждый подмодуль имеет одинаковую структуру: `modules/<name>/index.php`, `README.en.md` и `README.ru.md`.

| Модуль | Назначение |
| --- | --- |
| `base` | Общее поведение CF7 и ACF-список форм. |
| `mail-viewer` | Приватный журнал подготовленных писем CF7. |
| `flowchimp` | Синхронизация с Mailchimp audience. |
| `webhook` | Исходящий HTTP webhook отдельной формы. |
| `redirects` | Redirect/modal metadata в разметке форм. |
| `ui-select` | Custom Select shortcode, form tag и mail tags. |
| `icon-generator` | Генератор UI Icon в редакторе CF7. |

По умолчанию загружаются все подмодули. Если проекту нужен меньший набор, массив можно изменить фильтром `sp_cf7_modules` до инициализации.

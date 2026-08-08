# SP CF7 Core

Общее базовое поведение Contact Form 7.

- Отключает автоматические параграфы в формах CF7.
- Заменяет литеральный token `[theme-url]` на URL `BUILD` темы.
- Выполняет WordPress shortcodes в итоговой разметке формы.
- Добавляет класс `main-form grid-cols`, если `html_class` не передан.
- Заполняет ACF-поле `form_select` доступными формами CF7.

Contact Form 7 и ACF являются внешними необязательными зависимостями. Без их API callbacks остаются неактивными.

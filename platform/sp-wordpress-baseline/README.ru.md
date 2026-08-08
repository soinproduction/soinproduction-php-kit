# SP WordPress Baseline

Применяет общую baseline-policy WordPress для тем SoinProduction: расширение protocol allow-list, отключение XML-RPC/editor/comments, очистку dashboard/menu, update policy, обработку стандартного контента и theme defaults.

Подключается именем `sp-wordpress-baseline`. Это широкий policy-модуль, а не небольшой helper — перед включением в существующий проект просмотрите `index.php`. Activation helpers с префиксом `sp_reset_` восстанавливают Home и удаляют неизменённый стандартный контент.

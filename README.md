# model_vk
Класс для работы с api вконтакта (vk.com)

## Позволяет
+ Публиковать записи на стене группы / пользователя.
+ Загружать фотографии.
+ Добавлять комментарии к записям на стене.

### Пример использования
$vk = new model_vk($my_token);

$vk->wall_post($my_group_id, $text);

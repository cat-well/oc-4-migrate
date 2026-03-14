<?php
/**
 * Liqpay for OpenCart 4
 * 
 * @package     OpenCartBot
 * @author      OpenCartBot
 * @copyright   (c) OpenCartBot
 * @license     Commercial License - This software is not open-source and cannot be redistributed, modified, or used without a valid license from the author.
 * @link        https://opencartbot.com
 * @support     support@opencartbot.com
 */

$_['heading_title'] = 'Liqpay';
$_['heading_title_payments'] = 'Платежі Liqpay';

$_['text_extension'] = 'Розширення';
$_['text_edit'] = 'Редагувати Liqpay';
$_['text_liqpay'] = '<a href="https://opencartbot.com/" target="_blank"><kbd>opencartbot</kbd></a>';
$_['text_pay'] = 'Пряма оплата';
$_['text_hold'] = 'Холд';
$_['text_all_zones'] = 'Всі геозони';
$_['text_list'] = 'Список транзакцій';
$_['text_no_results'] = 'Транзакції не знайдені.';
$_['text_capture_title'] = 'Списати кошти';
$_['text_capture_confirm'] = 'Ви впевнені, що хочете списати кошти?';
$_['text_capture_success'] = 'Кошти успішно списані!';
$_['text_refund_title'] = 'Повернути кошти';
$_['text_refund_confirm'] = 'Введіть суму для повернення:';
$_['text_refund_success'] = 'Кошти успішно повернені!';
$_['text_success'] = 'Успішно';
$_['text_pending'] = 'В очікуванні';
$_['text_verification'] = 'На перевірці';
$_['text_canceled'] = 'Скасовано';
$_['text_failed'] = 'Помилка';
$_['text_refunded'] = 'Повернено';
$_['text_captured'] = 'Списано';
$_['text_payments'] = 'Платежі';
$_['text_session_success'] = 'Успішно';
$_['text_developed'] = 'Модуль розроблено';
$_['text_copyright'] = '<p><a target="_blank" href="https://opencartbot.com/liqpay-opencart-4">Сторінка розширення</a></p>
<p><a target="_blank" href="https://opencartbot.com/support/">Технічна підтримка</a></p><br>
<p>Автор: <strong>opencartbot</strong></p>
<p>Веб-сайт: <a target="_blank" href="https://opencartbot.com/">opencartbot.com</a></p>';

$_['tab_general'] = 'Основні';
$_['tab_order_status'] = 'Статуси замовлень';
$_['tab_license'] = 'Ліцензія';

$_['entry_public_key'] = 'Публічний ключ';
$_['entry_private_key'] = 'Приватний ключ';
$_['entry_action_type'] = 'Тип оплати';
$_['entry_title'] = 'Назва способу оплати';
$_['entry_description'] = 'Призначення платежу';
$_['entry_total'] = 'Мінімальна сума';
$_['entry_order_status'] = 'Статус замовлення (Успішно)';
$_['entry_pending_status'] = 'Статус замовлення (В очікуванні)';
$_['entry_hold_status'] = 'Статус замовлення (Холд)';
$_['entry_failed_status'] = 'Статус замовлення (Помилка)';
$_['entry_refund_status'] = 'Статус замовлення (Повернення)';
$_['entry_geo_zone'] = 'Геозона';
$_['entry_status'] = 'Статус';
$_['entry_sort_order'] = 'Порядок сортування';
$_['entry_refund_amount'] = 'Сума повернення';
$_['entry_session'] = 'Сеанс SameSite';
$_['entry_license'] = 'Ліцензійний ключ';

$_['help_action_type'] = 'Виберіть тип платежу: Пряма оплата або Блокування для подальшого списання';
$_['help_total'] = 'Мінімальна сума замовлення для активації цього способу оплати';
$_['help_description'] = 'Текст призначення платежу. Використовуйте {order_id} для вставки номера замовлення';
$_['help_order_status'] = 'Статус замовлення після успішної оплати';
$_['help_pending_status'] = 'Статус замовлення для платежів в очікуванні';
$_['help_hold_status'] = 'Статус замовлення для заблокованих платежів в очікуванні списання';
$_['help_failed_status'] = 'Статус при невдалій оплаті замовлення';
$_['help_refund_status'] = 'Статус замовлення після ручного повернення платежів';
$_['help_session'] = 'Використовуйте <strong>None</strong> для правильної роботи способу оплати (щоб запобігти виходу клієнтів із системи після оплати)';

$_['column_transaction_id'] = 'ID транзакції';
$_['column_order_id'] = 'ID замовлення';
$_['column_liqpay_order_id'] = 'ID замовлення Liqpay';
$_['column_amount'] = 'Сума';
$_['column_status'] = 'Статус';
$_['column_action_type'] = 'Тип';
$_['column_date_added'] = 'Дата додавання';
$_['column_action'] = 'Дія';

$_['button_save'] = 'Зберегти';
$_['button_cancel'] = 'Скасувати';
$_['button_back'] = 'Назад';
$_['button_payments'] = 'Переглянути платежі';
$_['button_capture'] = 'Списати';
$_['button_refund'] = 'Повернути';

$_['error_permission'] = 'Попередження: У вас немає прав для зміни платіжного модуля Liqpay!';
$_['error_public_key'] = 'Публічний ключ обов\'язковий!';
$_['error_private_key'] = 'Приватний ключ обов\'язковий!';
$_['error_transaction'] = 'Транзакція не знайдена!';
$_['error_refund_amount'] = 'Будь ласка, введіть коректну суму для повернення!';
$_['error_transaction_not_found'] = 'Транзакція не знайдена!';
$_['error_not_hold_payment'] = 'Транзакція не є блокуванням коштів!';
$_['error_cannot_capture'] = 'Неможливо списати кошти з цієї транзакції!';
$_['error_cannot_refund'] = 'Неможливо повернути кошти з цієї транзакції!';
$_['error_refund_amount_exceeds'] = 'Сума повернення перевищує доступну суму!';
$_['error_capture_failed'] = 'Не вдалося списати кошти!';
$_['error_refund_failed'] = 'Не вдалося повернути кошти!';
$_['error_refund_status_update'] = 'Повернення виконано, але не вдалося змінити статус замовлення в магазині!';
$_['error_capture_status_update'] = 'Кошти стягнено, але не вдалося змінити статус замовлення в магазині!';
$_['error_license1'] = 'Помилка 1: Недійсний ліцензійний ключ. Введіть ключ вашого домену!';
$_['error_license2'] = 'Помилка 2: Недійсний ліцензійний ключ. Введіть ключ вашого домену!';
$_['error_license3'] = 'Помилка 3: Недійсний ліцензійний ключ. Введіть ключ вашого домену!';

$_['success_captured'] = 'Кошти успішно списані!';
$_['success_refunded'] = 'Кошти успішно повернені!';
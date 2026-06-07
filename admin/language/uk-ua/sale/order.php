<?php
// Partial UA translation for the Nova Poshta block in the admin order page.
// OpenCart 4 falls back to en-gb for keys not defined here, so the rest of
// sale/order continues to render in English until the full file is
// translated by the localisation team.

// ── Error messages ──
$_['error_np_not_order']            = 'Увага: це замовлення не використовує доставку Нова Пошта!';
$_['error_np_ttn_failed']           = 'Увага: не вдалося створити накладну в Новій Пошті!';
$_['error_np_ttn_not_found']        = 'Увага: до цього замовлення не прив\'язана накладна!';
$_['error_np_ttn_status_failed']    = 'Увага: не вдалося оновити статус накладної в Новій Пошті!';

// ── Order-info Nova Poshta block ──
$_['text_novaposhta']               = 'Нова Пошта';
$_['text_np_delivery_type']         = 'Тип доставки';
$_['text_np_city']                  = 'Місто';
$_['text_np_address']               = 'Адреса';
$_['text_np_zone']                  = 'Область';
$_['text_np_country']               = 'Країна';
$_['text_np_ttn']                   = 'Номер накладної';
$_['text_np_ttn_ref']               = 'Ref накладної';
$_['text_np_ttn_date']              = 'Створено';
$_['text_np_ttn_status_code']       = 'Код статусу';
$_['text_np_ttn_status']            = 'Статус';
$_['text_np_ttn_status_date']       = 'Статус оновлено';

$_['text_np_ttn_success']           = 'Накладну Нової Пошти успішно створено.';
$_['text_np_ttn_recreate_success']  = 'Накладну Нової Пошти успішно перестворено.';
$_['text_np_ttn_edit_success']      = 'Накладну успішно оновлено (номер не змінився).';
$_['text_np_ttn_delete_success']    = 'Накладну Нової Пошти скасовано та видалено із замовлення.';
$_['text_np_ttn_delete_missing_remote'] = 'Накладну не знайдено в Новій Пошті. Локальний зв\'язок видалено.';

$_['text_np_status_refresh_success']   = 'Статус Нової Пошти успішно оновлено.';
$_['text_np_status_refresh_unchanged'] = 'Статус Нової Пошти не змінився.';
$_['text_np_status_order_sync_success'] = 'Статус замовлення оновлено автоматично: %s.';
$_['text_np_status_order_sync_skipped'] = 'Синхронізацію статусу замовлення пропущено для запобігання відкату (%s -> %s).';

$_['text_np_delivery_branch']       = 'Відділення';
$_['text_np_delivery_courier']      = 'Кур\'єр';
$_['text_np_delivery_locker']       = 'Поштомат';

$_['text_np_confirm_delete']        = 'Дійсно скасувати накладну в Новій Пошті та видалити її з цього замовлення?';

// ── Order history log messages ──
$_['text_np_history_created']           = 'Створено накладну Нової Пошти: %s';
$_['text_np_history_recreated']         = 'Перестворено накладну Нової Пошти: %s';
$_['text_np_history_edited']            = 'Відредаговано накладну Нової Пошти %s: %s';
$_['text_np_history_deleted']           = 'Видалено накладну Нової Пошти: %s';
$_['text_np_history_status_refreshed']  = 'Оновлено статус Нової Пошти (накладна %s): [%s] %s';
$_['text_np_history_order_status_sync'] = 'Статус замовлення автоматично оновлено з Нової Пошти %s: %s -> %s';

// ── Edit modal — sections + intro ──
$_['text_np_edit_title']            = 'Редагування накладної Нової Пошти';
$_['text_np_edit_intro']            = 'Оновлює існуючу накладну на місці. Номер накладної залишається без змін — роздруківки та посилання на трекінг залишаються чинними. Якщо Нова Пошта повертає «Document is not editable» (накладну вже сканували у відділенні), скористайтеся кнопкою «Перестворити».';
$_['text_np_section_cargo']         = 'Параметри відправлення';
$_['text_np_section_dimensions']    = 'Габарити';
$_['text_np_section_payment']       = 'Платник та оплата';
$_['text_np_section_additional_services'] = 'Додаткові послуги';
$_['text_np_section_cod']           = 'Післяплата';
$_['text_np_section_address']       = 'Адреса отримувача';
$_['text_np_section_sender']        = 'Адреса відправника';
$_['entry_np_sender_city']          = 'Місто відправника';
$_['entry_np_sender_delivery_type'] = 'Тип відправлення';
$_['entry_np_sender_warehouse']     = 'Відділення/поштомат';
$_['text_np_sender_hint']           = 'Оберіть місто та відділення/поштомат, з якого фактично відправляємо це замовлення.';
$_['text_np_section_extra']         = 'Додатково';

// ── Edit modal — field labels ──
$_['entry_np_weight']               = 'Вага (кг)';
$_['entry_np_seats']                = 'Кількість місць';
$_['entry_np_cost']                 = 'Оголошена вартість (грн)';
$_['entry_np_cargo_type']           = 'Тип вантажу';
$_['entry_np_description']          = 'Опис відправлення';
$_['entry_np_payer_type']           = 'Платник за доставку';
$_['entry_np_payment_method']       = 'Форма оплати';
$_['entry_np_additional_service']   = 'Послуга';
$_['entry_np_volume_width']         = 'Ширина (см)';
$_['entry_np_volume_length']        = 'Довжина (см)';
$_['entry_np_volume_height']        = 'Висота (см)';
$_['entry_np_volume_general']       = 'Об\'єм (м³)';
$_['text_np_volume_general_hint']   = 'опціонально';
$_['entry_np_cod_enabled']          = 'Післяплата';
$_['entry_np_cod_total']            = 'Сума післяплати / контролю (грн)';
$_['entry_np_cod_payer']            = 'Платник післяплати';
$_['entry_np_recipient_phone']      = 'Телефон отримувача';
$_['entry_np_recipient_city']       = 'Місто отримувача';
$_['entry_np_recipient_address']    = 'Відділення / поштомат / адреса';
$_['entry_np_delivery_type']        = 'Тип доставки';
$_['entry_np_internal_number']      = 'Внутрішній номер відправлення';

// ── Edit modal — select option labels ──
$_['text_np_payer_recipient']       = 'Отримувач (клієнт сплачує НП при отриманні)';
$_['text_np_payer_sender']          = 'Відправник (доставку сплачуємо ми)';
$_['text_np_payer_third']           = 'Третя особа';
$_['text_np_payer_hint']            = 'Оберіть «Відправник», щоб надати клієнту безкоштовну доставку — Нова Пошта спише вартість із нашого рахунку відправника, а не з клієнта при отриманні.';
$_['text_np_payment_cash']          = 'Готівка';
$_['text_np_payment_noncash']       = 'Безготівка';
$_['text_np_additional_none']       = 'Жодної';
$_['text_np_additional_cod']        = 'Післяплата';
$_['text_np_additional_control']    = 'Контроль оплати';

// ── Buttons ──
$_['button_np_create_ttn']          = 'Створити TTN';
$_['button_np_recreate_ttn']        = 'Перестворити TTN';
$_['button_np_edit_ttn']            = 'Редагувати TTN';
$_['button_np_delete_ttn']          = 'Видалити TTN';
$_['button_np_print_ttn']           = 'Друкувати TTN';
$_['button_np_refresh_status']      = 'Оновити статус';
$_['button_np_save_edit']           = 'Зберегти зміни';
$_['button_cancel']                 = 'Скасувати';

// ── Checkbox ──
$_['text_checkbox_prepare_title']    = 'Підготовка чека Checkbox';
$_['text_checkbox_prepare_order']    = 'Підготовка чека для замовлення #%s';
$_['button_checkbox_prepare']        = 'Підготувати чек';
$_['button_checkbox_refresh_status'] = 'Оновити';
$_['button_checkbox_sync_visible']   = 'Синхронізувати видимі';
$_['text_checkbox_ettn_linked']     = 'Проект чека Checkbox для Нової Пошти вже існував і був прив\'язаний до цього замовлення.';
$_['text_checkbox_status_refreshed'] = 'Статус Checkbox оновлено: %s.';
$_['text_checkbox_sync_visible_done'] = 'Синхронізацію Checkbox завершено: %s оновлено, %s пропущено, %s з помилкою.';
$_['text_checkbox_history_ettn_linked'] = 'Прив\'язано існуючий проект чека Checkbox для Нової Пошти: %s (TTN %s)';
$_['text_checkbox_history_status_refreshed'] = 'Оновлено статус Checkbox: %s';
$_['error_checkbox_sync_empty']      = 'На цій сторінці немає рядків Checkbox для синхронізації.';

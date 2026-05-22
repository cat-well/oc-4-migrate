<?php
// Manline footer texts (UA)
//
// Lives in the default catalog/language/ path (not under extension/manline/)
// because OC4's startup/language.php only registers one extension path per
// language (man_language.extension = "ukrainian" for uk-ua). The path it
// registers — extension/ukrainian/catalog/language/ — does not overlap with
// the manline footer file there, so extension/manline/.../common/footer.php
// was never loaded. The default catalog/language/ path is always consulted
// first by Language::load(), so any keys placed here are picked up before
// (and independently of) the active language extension.
$_['text_let_friend']        = 'Давайте дружити';
$_['text_subscribe']         = 'Підписуйтесь на наш блог і дізнавайтеся першими про акції та оновлення';
$_['text_your_email']        = 'Ваш Email';
$_['text_subscribe_email']   = 'Підписка';
$_['text_thanks_for_subscribe'] = 'Дякуємо за підписку';
$_['text_subscribe_btn']     = 'Підписатися';

$_['text_need_consult']      = 'Потрібна консультація?';
$_['text_only_viber']        = 'тільки Viber';
$_['text_contact_info']      = 'Контактна інформація';
$_['text_contact_address']   = 'вул. Володимирська, 18';

$_['text_categories']        = 'Категорії';
$_['text_footer_menu_title'] = 'Меню';
$_['text_menu_1']            = 'Труси';
$_['text_menu_2']            = 'Плавки';
$_['text_menu_3']            = 'Шкарпетки';
$_['text_menu_4']            = 'Термобілизна';

// Footer menu links (left column)
$_['text_menu_5']            = 'Про нас';
$_['text_menu_6']            = 'Відгуки';
$_['text_menu_7']            = 'Доставка/оплата';
$_['text_menu_8']            = 'Відстежити замовлення';

// Footer menu links (right column)
$_['text_menu_9']            = 'Допомога';
$_['text_menu_10']           = 'Співпраця';
$_['text_menu_11']           = 'Шукаємо моделей';
$_['text_menu_12']           = 'Виробники';

$_['text_ask_manager']       = 'Задайте питання менеджеру';
$_['text_popup_1']           = "Введіть ім'я та номер телефону. Наш менеджер<br>зв'яжеться з Вами найближчим часом!";
$_['text_your_name']         = "Ваше ім'я";
$_['text_message']           = 'Повідомлення';
$_['text_submit_form']       = 'Відправити';

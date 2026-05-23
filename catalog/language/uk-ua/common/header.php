<?php
// Manline header texts (UA)
//
// Same reasoning as common/footer.php: keys live in the default
// catalog/language/ path (not under extension/manline/...) because
// OC4's startup/language.php only registers one extension language
// path per active language. For uk-ua that path is
// extension/ukrainian/catalog/language/, which doesn't overlap with
// extension/manline/.../common/header.php — so the manline file was
// never loaded and the Twig template fell back to its hard-coded
// Russian defaults on every UA page. Placing the keys here makes
// them load before (and independently of) the active language
// extension.

$_['text_top_desc'] = '<span>Магазин чоловічої білизни</span>, який можна рекомендувати друзям';

// Top navigation (mobile + desktop top bar)
$_['text_menu_1']   = 'Відстежити замовлення';
$_['text_menu_2']   = 'Обмін';
$_['text_menu_3']   = 'Допомога';
$_['text_menu_4']   = 'Контакти';

// Schedule tooltip block (clickable phone area)
$_['text_graph_1']  = 'Графік роботи';
$_['text_graph_2']  = 'Графік роботи магазину';
$_['text_graph_3']  = 'Пн - Пт з 9:00 до 18:00';
$_['text_graph_4']  = 'Сб і Нд - вихідний';

// Search input placeholder
$_['text_search']   = 'Пошук';

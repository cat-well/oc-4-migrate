-- Seed Ukrainian + Russian translations for country=Україна and its 27 zones.
--
-- Why: man_country_description and man_zone_description only had rows for
-- language_id=1 (en-gb). On UA / RU admin (config_language_id=3 / 2), the
-- address-edit modal's Country/Zone dropdowns were rendered empty, because
-- getCountries() / getZonesByCountryId() filter strictly by current
-- language_id.
--
-- Existing orders are NOT affected: shipping_country / shipping_zone are
-- stored as text snapshots on man_order (and again on man_order_novaposhta
-- for NP orders), and those snapshots are already in Cyrillic — they came
-- from Nova Poshta's API at order creation, independently of these
-- description tables.
--
-- After this seed runs:
--   * Admin (in UA or RU) opens "Адреса доставки → Edit" and gets a
--     populated Country dropdown (only Ukraine for now — fill other
--     countries later if needed).
--   * Selecting "Україна" populates the Zone dropdown with 27 oblasts
--     using NP-compatible Cyrillic names ("Київська", "Херсонська", …).
--   * The order's existing zone_id (e.g. 3487) auto-selects to its
--     Ukrainian name in the dropdown — matches what NP shows in its block.
--
-- Idempotent: re-running is safe. Uses INSERT … ON DUPLICATE KEY UPDATE.
--
-- Manual run on prod (after staging verification):
--   mysql -u<user> -p<pass> <db> < sql/2026-05-18_seed_ua_country_zones.sql

-- Country: Україна (country_id=220)
INSERT INTO `man_country_description` (`country_id`, `language_id`, `name`) VALUES
  (220, 3, 'Україна'),
  (220, 2, 'Украина')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

-- Zones of Ukraine: 27 rows × 2 languages = 54 rows total.
-- zone_id ↔ canonical en-gb name kept as inline comment for traceability.
INSERT INTO `man_zone_description` (`zone_id`, `language_id`, `name`) VALUES
  -- 3480 Cherkaska
  (3480, 3, 'Черкаська'),                       (3480, 2, 'Черкасская'),
  -- 3481 Chernihivska
  (3481, 3, 'Чернігівська'),                    (3481, 2, 'Черниговская'),
  -- 3482 Chernivetska
  (3482, 3, 'Чернівецька'),                     (3482, 2, 'Черновицкая'),
  -- 3483 Avtonomna Respublika Krym
  (3483, 3, 'Автономна Республіка Крим'),       (3483, 2, 'Автономная Республика Крым'),
  -- 3484 Dnipropetrovska
  (3484, 3, 'Дніпропетровська'),                (3484, 2, 'Днепропетровская'),
  -- 3485 Donetska
  (3485, 3, 'Донецька'),                        (3485, 2, 'Донецкая'),
  -- 3486 Ivano-Frankivska
  (3486, 3, 'Івано-Франківська'),               (3486, 2, 'Ивано-Франковская'),
  -- 3487 Khersonska
  (3487, 3, 'Херсонська'),                      (3487, 2, 'Херсонская'),
  -- 3488 Khmelnytska
  (3488, 3, 'Хмельницька'),                     (3488, 2, 'Хмельницкая'),
  -- 3489 Kirovohradska
  (3489, 3, 'Кіровоградська'),                  (3489, 2, 'Кировоградская'),
  -- 3490 Kyiv (city, special status)
  (3490, 3, 'Київ'),                            (3490, 2, 'Киев'),
  -- 3491 Kyivska (oblast)
  (3491, 3, 'Київська'),                        (3491, 2, 'Киевская'),
  -- 3492 Luhanska
  (3492, 3, 'Луганська'),                       (3492, 2, 'Луганская'),
  -- 3493 Lvivska
  (3493, 3, 'Львівська'),                       (3493, 2, 'Львовская'),
  -- 3494 Mykolaivska
  (3494, 3, 'Миколаївська'),                    (3494, 2, 'Николаевская'),
  -- 3495 Odeska
  (3495, 3, 'Одеська'),                         (3495, 2, 'Одесская'),
  -- 3496 Poltavska
  (3496, 3, 'Полтавська'),                      (3496, 2, 'Полтавская'),
  -- 3497 Rivnenska
  (3497, 3, 'Рівненська'),                      (3497, 2, 'Ровенская'),
  -- 3498 Sevastopol (city, special status)
  (3498, 3, 'Севастополь'),                     (3498, 2, 'Севастополь'),
  -- 3499 Sumska
  (3499, 3, 'Сумська'),                         (3499, 2, 'Сумская'),
  -- 3500 Ternopilska
  (3500, 3, 'Тернопільська'),                   (3500, 2, 'Тернопольская'),
  -- 3501 Vinnytska
  (3501, 3, 'Вінницька'),                       (3501, 2, 'Винницкая'),
  -- 3502 Volynska
  (3502, 3, 'Волинська'),                       (3502, 2, 'Волынская'),
  -- 3503 Zakarpatska
  (3503, 3, 'Закарпатська'),                    (3503, 2, 'Закарпатская'),
  -- 3504 Zaporizka
  (3504, 3, 'Запорізька'),                      (3504, 2, 'Запорожская'),
  -- 3505 Zhytomyrska
  (3505, 3, 'Житомирська'),                     (3505, 2, 'Житомирская'),
  -- 4224 Kharkivska
  (4224, 3, 'Харківська'),                      (4224, 2, 'Харьковская')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

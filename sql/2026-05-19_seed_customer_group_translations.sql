-- Customer-group fixes for the admin order edit modal.
--
-- Two unrelated symptoms with the same DB-level root cause:
--
-- 1) `config_customer_group_id` is missing from `man_setting` entirely.
--    api/customer.php (line ~48) falls back to that setting when the form
--    doesn't post a customer_group_id explicitly; with NULL → 0, the
--    subsequent getCustomerGroup(0) returns nothing and the response
--    carries {"error": {"customer_group": "Customer group not found"}}.
--    The admin form then renders the red `is-invalid` border on the
--    customer block, blocking order save.
--
-- 2) `man_customer_group_description` only has rows for language_id=1
--    (en-gb). For UA / RU admins the "Група покупців" dropdown inside
--    the customer edit modal is rendered empty, leaving the admin
--    unable to pick a group manually either.
--
-- This seed fixes both:
--   - inserts config_customer_group_id=1 in man_setting (idempotent
--     via WHERE NOT EXISTS)
--   - back-fills name + description for groups 1/2/3 in language_id
--     2 (ru-ru) and 3 (uk-ua) — idempotent via ON DUPLICATE KEY UPDATE
--
-- After running: admin order edit modal customer-group dropdown is
-- populated, api/customer no longer 422s on missing group, save works.
--
-- Apply on prod (atomic):
--   mysql -h ... < sql/2026-05-19_seed_customer_group_translations.sql

INSERT INTO `man_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
SELECT 0, 'config', 'config_customer_group_id', '1', 0
WHERE NOT EXISTS (
    SELECT 1 FROM `man_setting`
    WHERE `store_id` = 0 AND `key` = 'config_customer_group_id'
);

INSERT INTO `man_customer_group_description` (`customer_group_id`, `language_id`, `name`, `description`) VALUES
  -- Default
  (1, 2, 'По умолчанию',  'Группа покупателей по умолчанию'),
  (1, 3, 'За замовчуванням', 'Група покупців за замовчуванням'),
  -- Retail
  (2, 2, 'Розничный',     'Розничные покупатели'),
  (2, 3, 'Роздрібний',     'Роздрібні покупці'),
  -- Wholesale
  (3, 2, 'Оптовый',       'Оптовые покупатели'),
  (3, 3, 'Оптовий',        'Оптові покупці')
ON DUPLICATE KEY UPDATE
    `name` = VALUES(`name`),
    `description` = VALUES(`description`);

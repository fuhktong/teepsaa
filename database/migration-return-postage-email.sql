-- The "return approved" email says who pays to send the item back
-- (nationwide shipping, Part 4). {return_km} / {return_en} are filled by
-- refund_return_tokens() in config/refund-evidence.php: the buyer pays after
-- a change of mind, otherwise it goes cash on delivery and the shop pays.
--
-- Slotted in after "on your order page." when the wording is still the
-- default; appended to the end when staff have reworded it.
UPDATE email_templates
   SET body_en = REPLACE(body_en, 'on your order page.', 'on your order page.{return_en}')
 WHERE template_key = 'refund_approved' AND body_en NOT LIKE '%{return_en}%';
UPDATE email_templates
   SET body_en = CONCAT(body_en, '{return_en}')
 WHERE template_key = 'refund_approved' AND body_en NOT LIKE '%{return_en}%';

UPDATE email_templates
   SET body_km = REPLACE(body_km, 'ទំព័រការបញ្ជាទិញរបស់អ្នក។', 'ទំព័រការបញ្ជាទិញរបស់អ្នក។{return_km}')
 WHERE template_key = 'refund_approved' AND body_km NOT LIKE '%{return_km}%';
UPDATE email_templates
   SET body_km = CONCAT(body_km, '{return_km}')
 WHERE template_key = 'refund_approved' AND body_km NOT LIKE '%{return_km}%';

UPDATE email_templates
   SET tokens = '{name}, {order}, {return_km}, {return_en}'
 WHERE template_key = 'refund_approved';

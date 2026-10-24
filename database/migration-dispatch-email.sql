-- The "dispatched" email names how the parcel is coming and any tracking
-- number (nationwide shipping, Part 4). {shipping_km} / {shipping_en} are
-- filled by dispatch_notify_buyer() in config/order-photo.php and are blank
-- when the vendor gave nothing useful, so the email reads as before.
--
-- Slotted in after "has been dispatched." when the wording is still the
-- default; appended to the end when staff have reworded it.
UPDATE email_templates
   SET body_en = REPLACE(body_en, 'has been dispatched.', 'has been dispatched.{shipping_en}')
 WHERE template_key = 'order_dispatched' AND body_en NOT LIKE '%{shipping_en}%';
UPDATE email_templates
   SET body_en = CONCAT(body_en, '{shipping_en}')
 WHERE template_key = 'order_dispatched' AND body_en NOT LIKE '%{shipping_en}%';

UPDATE email_templates
   SET body_km = REPLACE(body_km, 'ត្រូវបានបញ្ជូន។', 'ត្រូវបានបញ្ជូន។{shipping_km}')
 WHERE template_key = 'order_dispatched' AND body_km NOT LIKE '%{shipping_km}%';
UPDATE email_templates
   SET body_km = CONCAT(body_km, '{shipping_km}')
 WHERE template_key = 'order_dispatched' AND body_km NOT LIKE '%{shipping_km}%';

UPDATE email_templates
   SET tokens = '{name}, {order}, {shipping_km}, {shipping_en}'
 WHERE template_key = 'order_dispatched';

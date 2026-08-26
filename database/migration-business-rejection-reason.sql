-- Rejection reason + resubmit flow.
--
-- Rejecting a business used to be a dead end: approved = -1 with nothing stored
-- to tell the vendor why, no way back into the review queue, and /submit/ still
-- blocked because the rejected row is not deleted. The reason is now stored on
-- the business and shown in the vendor's Business settings, where a "Resubmit
-- for review" button sets approved back to 0.
ALTER TABLE businesses ADD COLUMN rejection_reason TEXT NULL AFTER approved;

-- Carry the reason into the rejection email. Appended rather than rewritten so
-- any staff rewording of the template survives; the {reason_*} tokens render as
-- empty strings when an admin rejects without typing a reason.
UPDATE email_templates
   SET body_km = CONCAT(body_km, '{reason_km}')
 WHERE template_key = 'business_rejected' AND body_km NOT LIKE '%{reason_km}%';

UPDATE email_templates
   SET body_en = CONCAT(body_en, '{reason_en}')
 WHERE template_key = 'business_rejected' AND body_en NOT LIKE '%{reason_en}%';

UPDATE email_templates
   SET tokens = '{name}, {business}, {reason_km}, {reason_en}'
 WHERE template_key = 'business_rejected';

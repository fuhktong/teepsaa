-- Nationwide copy (nationwide shipping, Part 5): the database half of opening
-- the whole country (config/cities.php, 2026-10-25). The repo half — lang
-- lines, category intros, page titles, Google's shipping data — ships with
-- the code.
--
-- Swaps only the sentences that say Phnom Penh, so any other edit made at
-- /admin/content.php since is kept. Running it twice changes nothing.

UPDATE content_pages
   SET body_en = REPLACE(body_en, 'Deliveries are currently available within Phnom Penh.', 'We deliver anywhere in Cambodia: by Grab when the shop is nearby, by courier further away.'),
       body_km = REPLACE(body_km, 'បច្ចុប្បន្ន ការដឹកជញ្ជូនមាននៅក្នុងភ្នំពេញ។', 'យើងដឹកជញ្ជូនទៅគ្រប់ទីកន្លែងក្នុងប្រទេសកម្ពុជា៖ តាម Grab នៅពេលហាងនៅជិត និងតាមក្រុមហ៊ុនដឹកជញ្ជូននៅពេលឆ្ងាយ។'),
       updated_at = NOW()
 WHERE slug IN ('terms', 'shipping');

UPDATE faq_items
   SET answer_en = REPLACE(answer_en, 'Deliveries are currently available within Phnom Penh.', 'We deliver anywhere in Cambodia.'),
       answer_km = REPLACE(answer_km, 'បច្ចុប្បន្ន ការដឹកជញ្ជូនមាននៅក្នុងភ្នំពេញ។', 'យើងដឹកជញ្ជូនទៅគ្រប់ទីកន្លែងក្នុងប្រទេសកម្ពុជា។')
 WHERE id = 5;

UPDATE faq_items
   SET answer_en = REPLACE(answer_en, 'Most deliveries within Phnom Penh arrive', 'Most Grab deliveries arrive'),
       answer_km = REPLACE(answer_km, 'ការដឹកជញ្ជូនភាគច្រើននៅក្នុងភ្នំពេញមកដល់', 'ការដឹកជញ្ជូនតាម Grab ភាគច្រើនមកដល់')
 WHERE id = 6;

UPDATE email_templates
   SET body_en = REPLACE(body_en, 'Start exploring stores and products across Phnom Penh!', 'Start exploring stores and products across Cambodia!'),
       body_km = REPLACE(body_km, 'ស្វែងរកហាង និងផលិតផលនៅទូទាំងទីក្រុងភ្នំពេញឥឡូវនេះ!', 'ស្វែងរកហាង និងផលិតផលនៅទូទាំងប្រទេសកម្ពុជាឥឡូវនេះ!')
 WHERE template_key = 'welcome_buyer';

-- Khmer names for the Clothing category tree.
-- Matches on the English name, so shared names (Jeans, Shorts, Activewear…)
-- under Men's and Women's are updated in one statement. Safe to rerun.
-- Run in phpMyAdmin (SQL tab) on the live DB.

-- Root
UPDATE categories SET name_km = 'សម្លៀកបំពាក់' WHERE name = 'Clothing';

-- Parents
UPDATE categories SET name_km = 'បុរស' WHERE name = 'Men\'s';
UPDATE categories SET name_km = 'នារី' WHERE name = 'Women\'s';
UPDATE categories SET name_km = 'កុមារ' WHERE name = 'Kids\'';
UPDATE categories SET name_km = 'គ្រឿងបន្សំ' WHERE name = 'Accessories';
UPDATE categories SET name_km = 'ស្បែកជើង' WHERE name = 'Footwear';
UPDATE categories SET name_km = 'សម្លៀកបំពាក់ប្រពៃណី' WHERE name = 'Traditional & Cultural Wear';

-- Men's
UPDATE categories SET name_km = 'អាវ និងអាវយឺត' WHERE name = 'Tops & T-Shirts';
UPDATE categories SET name_km = 'អាវសឺមី' WHERE name = 'Shirts & Dress Shirts';
UPDATE categories SET name_km = 'អាវហ៊ូដ និងអាវរងា' WHERE name = 'Hoodies & Sweatshirts';
UPDATE categories SET name_km = 'អាវក្រៅ' WHERE name = 'Jackets & Coats';
UPDATE categories SET name_km = 'ខោជើងវែង' WHERE name = 'Trousers & Chinos';
UPDATE categories SET name_km = 'ខោខូវប៊យ' WHERE name = 'Jeans';
UPDATE categories SET name_km = 'ខោខ្លី' WHERE name = 'Shorts';
UPDATE categories SET name_km = 'ឈុតវេស្តុង' WHERE name = 'Suits & Blazers';
UPDATE categories SET name_km = 'សម្លៀកបំពាក់កីឡា' WHERE name = 'Activewear';
UPDATE categories SET name_km = 'ខោអាវក្នុង និងស្រោមជើង' WHERE name = 'Underwear & Socks';
UPDATE categories SET name_km = 'ឈុតគេង' WHERE name = 'Sleepwear';

-- Women's
UPDATE categories SET name_km = 'អាវនារី' WHERE name = 'Tops & Blouses';
UPDATE categories SET name_km = 'អាវយឺត' WHERE name = 'T-Shirts';
UPDATE categories SET name_km = 'រ៉ូប' WHERE name = 'Dresses';
UPDATE categories SET name_km = 'សំពត់' WHERE name = 'Skirts';
UPDATE categories SET name_km = 'ខោអាវក្នុងនារី' WHERE name = 'Underwear & Lingerie';

-- Kids'
UPDATE categories SET name_km = 'សម្លៀកបំពាក់ក្មេងប្រុស' WHERE name = 'Boys\' Clothing';
UPDATE categories SET name_km = 'សម្លៀកបំពាក់ក្មេងស្រី' WHERE name = 'Girls\' Clothing';
UPDATE categories SET name_km = 'ទារក និងក្មេងតូច' WHERE name = 'Baby & Toddler';

-- Accessories
UPDATE categories SET name_km = 'មួក' WHERE name = 'Hats & Caps';
UPDATE categories SET name_km = 'ក្រមា និងកន្សែងបង់ក' WHERE name = 'Scarves & Wraps';
UPDATE categories SET name_km = 'ខ្សែក្រវ៉ាត់' WHERE name = 'Belts';
UPDATE categories SET name_km = 'កាបូប' WHERE name = 'Bags & Purses';
UPDATE categories SET name_km = 'កាបូបលុយ' WHERE name = 'Wallets';
UPDATE categories SET name_km = 'វ៉ែនតាខ្មៅ' WHERE name = 'Sunglasses';
UPDATE categories SET name_km = 'គ្រឿងអលង្ការ' WHERE name = 'Jewellery';
UPDATE categories SET name_km = 'នាឡិកាដៃ' WHERE name = 'Watches';

-- Footwear
UPDATE categories SET name_km = 'ស្បែកជើងបុរស' WHERE name = 'Men\'s Shoes';
UPDATE categories SET name_km = 'ស្បែកជើងនារី' WHERE name = 'Women\'s Shoes';
UPDATE categories SET name_km = 'ស្បែកជើងកុមារ' WHERE name = 'Kids\' Shoes';
UPDATE categories SET name_km = 'ស្បែកជើងសង្រែក និងផ្ទាត់' WHERE name = 'Sandals & Flip Flops';
UPDATE categories SET name_km = 'ស្បែកជើងកីឡា' WHERE name = 'Sneakers';
UPDATE categories SET name_km = 'ស្បែកជើងកវែង' WHERE name = 'Boots';

-- Traditional & Cultural Wear
UPDATE categories SET name_km = 'ប្រពៃណីខ្មែរ' WHERE name = 'Khmer Traditional';
UPDATE categories SET name_km = 'សម្លៀកបំពាក់ពិធី' WHERE name = 'Formal & Ceremony';

-- How a parcel left the shop, and the photos that prove it (nationwide
-- shipping, Part 4).
--
-- shipping_method  grab | courier | tuktuk | other. NULL on orders dispatched
--                  before this, which were all Grab.
-- courier          the courier company's name, when shipping_method = courier.
-- tracking_number  a courier's tracking or waybill number. A Grab link stays in
--                  tracking_url.
-- dispatch_photo   the vendor's photo of the packed parcel. Stored outside the
--                  web root (teepsaa-private/order-photos) because the label
--                  shows the buyer's name, phone and address; served through
--                  api/order-photo.php.
-- return_photo     the buyer's photo of a parcel sent back, same storage.
ALTER TABLE orders
    ADD COLUMN shipping_method VARCHAR(20)  NULL DEFAULT NULL AFTER tracking_url,
    ADD COLUMN courier         VARCHAR(60)  NULL DEFAULT NULL AFTER shipping_method,
    ADD COLUMN tracking_number VARCHAR(80)  NULL DEFAULT NULL AFTER courier,
    ADD COLUMN dispatch_photo  VARCHAR(100) NULL DEFAULT NULL AFTER tracking_number,
    ADD COLUMN return_photo    VARCHAR(100) NULL DEFAULT NULL AFTER return_tracking_url;

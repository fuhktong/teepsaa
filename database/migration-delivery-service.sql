-- Which delivery the buyer paid for: Grab (same day, nearby) or a courier
-- (1–3 days, by weight). Chosen per shop at checkout when both are on offer;
-- see price_delivery() in config/delivery-calc.php. NULL on orders placed
-- before the choice existed, and on shops with no map pin.
--
-- The vendor's dispatch form starts on this method. shipping_method is still
-- what the vendor actually used.

ALTER TABLE orders
    ADD COLUMN delivery_service VARCHAR(20) NULL DEFAULT NULL AFTER delivery_weight_g;

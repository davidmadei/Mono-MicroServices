-- reset_demo.sql
--
-- Puts the database back to the state a fresh install has: the three shoes at
-- their original prices, the original stock numbers, and an empty Orders table.
--
-- USE THIS BEFORE A LIVE CLICK-THROUGH DEMO, NOT BEFORE A LOAD TEST.
--
--   reset_demo.sql       stock 25 / 40 / 15   -- realistic numbers to show students
--   reset_inventory.sql  stock 100,000,000    -- survives a 15-minute load test
--
-- The original numbers drain in about twenty seconds under 500 users, and
-- because burn_cpu() runs BEFORE the stock check, CPU load keeps looking normal
-- while the database half of the experiment silently stops. So: demo numbers for
-- demos, huge numbers for load tests.

USE Products;

-- Empty the order history.
DELETE FROM Orders;

-- Restore products and prices. DECIMAL(10,2) so 179.99 is not rounded to 180.
ALTER TABLE Footwear MODIFY Cost DECIMAL(10,2);

DELETE FROM Footwear WHERE ItemID IN (1, 2, 3);
INSERT INTO Footwear (ItemID, Name, Description, Cost) VALUES
  (1, 'Nike Air Max 270',      'The Max Air 270 unit delivers unrivaled, all-day comfort.', 160.00),
  (2, 'Adidas Ultraboost 4.0', 'Ultraboost DNA carries the genetic information of one of our most popular performance runners, but it is born for the street', 179.99),
  (3, 'Reebok Nano X2',        'The Nano X2 invites you to be exactly who you are, wherever you are. It is one part performance and one part lifestyle.', 135.00);

-- The original course stock levels.
INSERT INTO Inventory (ItemID, Count) VALUES
  (1, 25),
  (2, 40),
  (3, 15) AS new
ON DUPLICATE KEY UPDATE Count = new.Count;

-- Confirm
SELECT ItemID, Name, Cost FROM Footwear ORDER BY ItemID;
SELECT ItemID, Count FROM Inventory ORDER BY ItemID;
SELECT COUNT(*) AS orders_remaining FROM Orders;

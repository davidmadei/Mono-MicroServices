-- Run this against the Products database BEFORE EVERY load test.
--
-- Why: DatabaseSetup.sql seeds 100,000,000 per item, but a click-through demo
-- (reset_demo.sql) puts it back to 25 + 40 + 15 = 80 units total. At 500
-- concurrent users a load test drains that in roughly the first 20 seconds.
--
-- What makes it subtle: burn_cpu() is called BEFORE the stock check in both
-- products.php and buyProducts.php, so CPU load continues unaffected and the
-- autoscaling demo still appears to work. But for the remaining ~880 seconds:
--   * no UPDATE Inventory ever executes
--   * no LOCK TABLE ... WRITE is ever taken
--   * Orders receives ~80 rows total across a 15-minute run
-- The entire database-contention dimension of the experiment silently
-- disappears -- including the table-lock bottleneck that explains why a second
-- BuyProducts instance does not double throughput.

USE Products;

UPDATE Inventory SET Count = 100000000 WHERE ItemID = 1;
UPDATE Inventory SET Count = 100000000 WHERE ItemID = 2;
UPDATE Inventory SET Count = 100000000 WHERE ItemID = 3;

-- Optional: clear orders so each run's row count is a clean measurement of
-- completed purchases.
-- TRUNCATE TABLE Orders;

SELECT ItemID, Count FROM Inventory;

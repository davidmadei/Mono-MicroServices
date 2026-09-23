-- DatabaseSetup.sql
--
-- Creates the Products database, its three tables, and the seed data.
-- Works in DBeaver (use Execute SCRIPT, not Execute statement) and from the
-- command line:  mysql -h <your-rds-endpoint> -u <user> -p < DatabaseSetup.sql
-- Safe to re-run.
--
-- Changes from the original course version:
--
--   * IF NOT EXISTS everywhere, so it does not matter whether you set
--     "Initial database name: Products" when you created the RDS instance.
--     Both DBeaver and the mysql client stop on the first error; the original's
--     bare CREATE DATABASE failed on an existing database and left no tables.
--   * No trailing EXIT; -- DBeaver reports it as a syntax error.
--   * Orders.ItemID is NOT a primary key. The same ItemID appears in many
--     orders; with a primary key every purchase after the first fails
--     silently, because the app returns HTTP 200 regardless.
--   * Cost is DECIMAL(10,2). Bare DECIMAL means zero decimal places, which
--     rounds 179.99 to 180.
--   * Inventory is seeded at 100,000,000 rather than 25/40/15. The original
--     numbers drain in about twenty seconds under a 500-user load test, and
--     because burn_cpu() runs BEFORE the stock check, CPU load keeps looking
--     normal while the database side of the experiment stops.
--     For a live click-through demo with the original numbers, run
--     reset_demo.sql. Before every load test, run reset_inventory.sql.

CREATE DATABASE IF NOT EXISTS Products;
USE Products;

CREATE TABLE IF NOT EXISTS Orders (
  ItemID        bigint unsigned,
  CustomerEmail varchar(1024),
  Quantity      int unsigned
);

CREATE TABLE IF NOT EXISTS Footwear (
  ItemID      bigint unsigned,
  Name        varchar(1024),
  Description text,
  -- DECIMAL(10,2): bare DECIMAL is DECIMAL(10,0) and rounds 179.99 to 180.
  Cost        decimal(10,2)
);

CREATE TABLE IF NOT EXISTS Inventory (
  ItemID bigint unsigned PRIMARY KEY,
  Count  bigint unsigned
);

-- Idempotent seeds: re-running will not duplicate rows.
DELETE FROM Footwear WHERE ItemID IN (1, 2, 3);
INSERT INTO Footwear (ItemID, Name, Description, Cost) VALUES
  (1, 'Nike Air Max 270',      'The Max Air 270 unit delivers unrivaled, all-day comfort.', 160.00),
  (2, 'Adidas Ultraboost 4.0', 'Ultraboost DNA carries the genetic information of one of our most popular performance runners, but it is born for the street', 179.99),
  (3, 'Reebok Nano X2',        'The Nano X2 invites you to be exactly who you are, wherever you are. It is one part performance and one part lifestyle.', 135.00);

-- `AS new` row alias rather than the VALUES() function: MySQL 8.0.20+ deprecates
-- VALUES() in ON DUPLICATE KEY UPDATE and warns about it.
INSERT INTO Inventory (ItemID, Count) VALUES
  (1, 100000000),
  (2, 100000000),
  (3, 100000000) AS new
ON DUPLICATE KEY UPDATE Count = new.Count;

-- Verify
SELECT * FROM Footwear;
SELECT ItemID, Count FROM Inventory;

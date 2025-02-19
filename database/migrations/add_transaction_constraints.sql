-- First, identify duplicates
CREATE TEMPORARY TABLE temp_duplicates AS
SELECT order_id, transaction_type, MIN(id) as keep_id
FROM transactions
GROUP BY order_id, transaction_type
HAVING COUNT(*) > 1;

-- Delete duplicate records keeping only the first one
DELETE t FROM transactions t
JOIN temp_duplicates td
WHERE t.order_id = td.order_id 
AND t.transaction_type = td.transaction_type
AND t.id != td.keep_id;

-- Drop temporary table
DROP TEMPORARY TABLE IF EXISTS temp_duplicates;

-- Add unique constraint to prevent duplicate transactions
ALTER TABLE transactions
ADD CONSTRAINT unique_order_transaction 
UNIQUE (order_id, transaction_type);

-- Add updated_at column if not exists
ALTER TABLE transactions
ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP 
DEFAULT CURRENT_TIMESTAMP 
ON UPDATE CURRENT_TIMESTAMP;

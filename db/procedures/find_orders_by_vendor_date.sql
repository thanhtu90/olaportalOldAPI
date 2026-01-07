-- Helper queries to find orders by vendor and date criteria
-- Use these to determine the right parameters for deletion procedures

-- 1. Find all vendors and their order counts
SELECT 
    a.id as vendors_id,
    a.name as vendor_name,
    COUNT(o.id) as total_orders,
    MIN(o.orderDate) as earliest_order,
    MAX(o.orderDate) as latest_order,
    MIN(o.lastMod) as earliest_lastmod,
    MAX(o.lastMod) as latest_lastmod
FROM accounts a
LEFT JOIN orders o ON a.id = o.vendors_id
GROUP BY a.id, a.name
HAVING total_orders > 0
ORDER BY total_orders DESC;

-- 2. Find orders for specific vendor with recent lastmod
-- Replace 35 with your vendors_id
SELECT 
    id,
    uuid,
    orderReference,
    total,
    orderDate,
    lastMod,
    FROM_UNIXTIME(lastMod) as lastMod_readable
FROM orders 
WHERE vendors_id = 35 
ORDER BY lastMod DESC 
LIMIT 20;

-- 3. Find orders by date range (convert dates to timestamps)
-- Example: Find orders modified after specific date
SELECT 
    id,
    uuid,
    orderReference,
    total,
    orderDate,
    lastMod,
    FROM_UNIXTIME(lastMod) as lastMod_readable
FROM orders 
WHERE vendors_id = 35 
    AND lastMod >= UNIX_TIMESTAMP('2025-08-02 15:00:00')
ORDER BY lastMod DESC;

-- 4. Count orders that would be affected by deletion
-- Replace parameters with your values
SET @vendors_id = 35;
SET @lastmod_from = 1754172998; -- or use UNIX_TIMESTAMP('2025-08-02 15:03:18')

SELECT 
    @vendors_id as vendors_id,
    @lastmod_from as lastmod_from,
    FROM_UNIXTIME(@lastmod_from) as lastmod_from_readable,
    COUNT(o.id) as orders_to_delete,
    SUM(CASE WHEN op.id IS NOT NULL THEN 1 ELSE 0 END) as payments_to_delete,
    SUM(CASE WHEN oi.id IS NOT NULL THEN 1 ELSE 0 END) as items_to_delete,
    MIN(o.orderDate) as earliest_order_date,
    MAX(o.orderDate) as latest_order_date,
    SUM(o.total) as total_amount
FROM orders o
LEFT JOIN ordersPayments op ON op.orderReference = o.id
LEFT JOIN orderItems oi ON oi.orders_id = o.id
WHERE o.vendors_id = @vendors_id AND o.lastMod >= @lastmod_from;

-- 5. Timestamp conversion helpers
SELECT 
    '2025-08-02 15:03:18' as readable_date,
    UNIX_TIMESTAMP('2025-08-02 15:03:18') as timestamp;

SELECT 
    1754172998 as timestamp,
    FROM_UNIXTIME(1754172998) as readable_date;

-- 6. Find recent modifications for all vendors
SELECT 
    o.vendors_id,
    a.name as vendor_name,
    COUNT(*) as recent_orders,
    MAX(o.lastMod) as latest_lastmod,
    FROM_UNIXTIME(MAX(o.lastMod)) as latest_lastmod_readable
FROM orders o
INNER JOIN accounts a ON o.vendors_id = a.id
WHERE o.lastMod >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 24 HOUR))
GROUP BY o.vendors_id, a.name
ORDER BY latest_lastmod DESC;
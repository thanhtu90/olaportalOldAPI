-- Fixed query using subquery in IN clause
WITH vendor_terminal AS (
    SELECT serial
    FROM `terminals`
    WHERE `vendors_id` = 159
)
SELECT *
FROM `json`
WHERE serial IN (SELECT serial FROM vendor_terminal)
  AND lastmod >= 1759251600;

-- Alternative: Using JOIN (often more efficient)
WITH vendor_terminal AS (
    SELECT serial
    FROM `terminals`
    WHERE `vendors_id` = 159
)
SELECT j.*
FROM `json` j
INNER JOIN vendor_terminal vt ON j.serial = vt.serial
WHERE j.lastmod >= 1759251600;

-- Or even simpler: Direct JOIN without CTE (most efficient)
SELECT j.*
FROM `json` j
INNER JOIN `terminals` t ON j.serial = t.serial
WHERE t.vendors_id = 159
  AND j.lastmod >= 1759251600;















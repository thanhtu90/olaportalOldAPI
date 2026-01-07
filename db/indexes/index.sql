-- 1. CRITICAL - Fix the 369K row full table scan on ordersPayments
CREATE INDEX `idx_orderspayments_olapay_approval_id` 
ON `ordersPayments`(`olapayApprovalId`);

-- 2. HIGH IMPACT - Fix sorting and filtering on unique_olapay_transactions
CREATE INDEX `idx_uot_serial_lastmod_status_type` 
ON `unique_olapay_transactions`(`serial`, `lastmod`, `status`, `trans_type`);

-- 3. NICE TO HAVE - Optimize terminals lookup
CREATE INDEX `idx_terminals_serial_id` 
ON `terminals`(`serial`, `id`);


============================================================
analyze query before index and result:

EXPLAIN
SELECT u.lastmod, u.content
FROM unique_olapay_transactions u
WHERE u.serial = 'WPYB002345000033'
  AND u.lastmod > 1760770800
  AND u.lastmod < 1760857200
  AND u.status NOT IN ('', 'FAIL', 'REFUNDED')
  AND u.trans_type NOT IN ('Return Cash', '', 'Auth')
  AND NOT EXISTS (
    SELECT 1
    FROM ordersPayments op
    JOIN terminals t ON t.id = op.terminals_id
    WHERE op.olapayApprovalId = u.trans_id
        AND op.olapayApprovalId IS NOT NULL
        AND op.olapayApprovalId != ''
        AND t.serial = u.serial
  )
ORDER BY u.lastmod DESC;



1	SIMPLE	u	NULL	ref	unique_transaction,idx_serial,idx_lastmod,idx_uot_trans_type,idx_uot_status,idx_uot_lastmod_amount	unique_transaction	402	const	1627	0.01	Using where; Using temporary; Using filesort	
1	SIMPLE	op	NULL	ALL	NULL	NULL	NULL	NULL	369462	100.00	Using where; Not exists	
1	SIMPLE	t	NULL	eq_ref	PRIMARY	PRIMARY	4	api2.op.terminals_id	1	100.00	Using where	


============================================================



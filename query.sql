WITH olapay_approvals AS (
    SELECT DISTINCT
        JSON_VALUE(
            JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
            '$[0].olapayApprovalId'
        ) as olapayApprovalId,
        j.lastmod as json_lastmod
    FROM json j
    WHERE j.lastmod >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    AND JSON_VALUE(
        JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
        '$[0].olapayApprovalId'
    ) IS NOT NULL
),
mismatched_transactions AS (
    SELECT 
        jo.id,
        jo.lastmod,
        JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_id')) as trans_id,
        JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.amount')) as amount,
        JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_type')) as trans_type,
        jo.serial,
        oa.olapayApprovalId,
        oa.json_lastmod
    FROM jsonOlaPay jo
    LEFT JOIN olapay_approvals oa 
        ON CAST(JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_id')) AS CHAR) = oa.olapayApprovalId
    WHERE jo.lastmod >= UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
        AND oa.olapayApprovalId IS NULL
)
SELECT 
    mt.*,
    terminals.serial as terminal_serial,
    accounts.companyname as business_name,
    accounts.id as business_id
FROM mismatched_transactions mt
JOIN terminals ON terminals.id = mt.serial
JOIN accounts ON terminals.vendors_id = accounts.id
ORDER BY mt.lastmod DESC; 
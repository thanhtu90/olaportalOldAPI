WITH olapay_approvals AS (
    SELECT DISTINCT
        JSON_VALUE(
            JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
            '$[0].olapayApprovalId'
        ) as olapayApprovalId
    FROM json j
    WHERE j.lastmod >= '2025-01-06 18:00:00'
    AND JSON_VALUE(
        JSON_UNQUOTE(JSON_EXTRACT(j.content, '$.payments')),
        '$[0].olapayApprovalId'
    ) IS NOT NULL
)
SELECT 
    accounts.id,
    accounts.companyname AS business,
    jo.id as jsonOlaPay_id,
    FROM_UNIXTIME(jo.lastmod) as transaction_time,
    JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_id')) as trans_id,
    JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.amount')) as amount,
    JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_type')) as trans_type,
    terminals.id
FROM jsonOlaPay jo
LEFT JOIN olapay_approvals oa 
    ON CAST(JSON_UNQUOTE(JSON_EXTRACT(jo.content, '$.trans_id')) AS CHAR) != oa.olapayApprovalId
JOIN terminals ON terminals.serial = jo.serial
JOIN accounts ON terminals.vendors_id = accounts.id
WHERE oa.olapayApprovalId IS NULL
    AND jo.lastmod >= 1736139398
ORDER BY jo.lastmod DESC;
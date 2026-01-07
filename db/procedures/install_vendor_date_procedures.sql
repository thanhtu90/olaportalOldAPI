-- Installation script for vendor date deletion procedures
-- Run this file to install all vendor date deletion procedures at once

USE olaportal;

-- Install basic vendor date delete procedure
SOURCE delete_orders_by_vendor_date.sql;

-- Install preview procedure  
SOURCE preview_orders_by_vendor_date.sql;

-- Install safe delete procedure with confirmation
SOURCE delete_orders_by_vendor_date_safe.sql;

-- Verify procedures were created
SELECT 
    ROUTINE_NAME as procedure_name,
    ROUTINE_TYPE as type,
    CREATED as created_date,
    ROUTINE_COMMENT as description
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = DATABASE() 
    AND ROUTINE_NAME IN (
        'delete_orders_by_vendor_date', 
        'preview_orders_by_vendor_date', 
        'delete_orders_by_vendor_date_safe'
    )
ORDER BY ROUTINE_NAME;

SELECT 'All vendor date deletion procedures installed successfully!' as status;
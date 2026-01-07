-- Installation script for order deletion procedures
-- Run this file to install all three procedures at once

USE olaportal;

-- Install basic delete procedure
SOURCE delete_order_cascade.sql;

-- Install detailed delete procedure  
SOURCE delete_order_cascade_detailed.sql;

-- Install preview procedure
SOURCE preview_order_delete.sql;

-- Verify procedures were created
SELECT 
    ROUTINE_NAME as procedure_name,
    ROUTINE_TYPE as type,
    CREATED as created_date
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = DATABASE() 
    AND ROUTINE_NAME IN ('delete_order_cascade', 'delete_order_cascade_detailed', 'preview_order_delete')
ORDER BY ROUTINE_NAME;

SELECT 'All order deletion procedures installed successfully!' as status;
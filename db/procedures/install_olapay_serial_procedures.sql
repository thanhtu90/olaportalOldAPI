-- =============================================================================
-- Install OlaPay Serial Deletion Procedures
-- Run this script to install all OlaPay serial-based deletion procedures
-- =============================================================================

-- Install preview procedure
SOURCE db/procedures/preview_olapay_by_serial.sql;

-- Install delete procedure
SOURCE db/procedures/delete_olapay_by_serial.sql;

-- Verify installation
SELECT 'OlaPay serial procedures installed successfully' as message;

-- List installed procedures
SELECT 
    ROUTINE_NAME as procedure_name,
    CREATED as created_at
FROM INFORMATION_SCHEMA.ROUTINES 
WHERE ROUTINE_SCHEMA = DATABASE() 
    AND ROUTINE_NAME IN ('preview_olapay_by_serial', 'delete_olapay_by_serial')
ORDER BY ROUTINE_NAME;





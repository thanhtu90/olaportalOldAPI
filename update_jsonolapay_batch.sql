DELIMITER //

CREATE PROCEDURE process_jsonolapay_batch(
    IN p_serial VARCHAR(255),
    IN p_starting_lastmod BIGINT,
    IN p_limit INT
)
BEGIN
    DECLARE id_list TEXT;
    
    -- Query jsonOlapay table to get IDs based on input parameters
    SELECT GROUP_CONCAT(id SEPARATOR ',') INTO id_list
    FROM jsonOlapay
    WHERE serial = p_serial
    AND lastmod >= p_starting_lastmod
    ORDER BY lastmod ASC
    LIMIT p_limit;
    
    -- Check if any records were found
    IF id_list IS NOT NULL AND LENGTH(id_list) > 0 THEN
        -- Call the update procedure with the concatenated ID list
        CALL update_jsonolapay_lastmod(id_list);
        
        -- Return success message with the number of IDs processed
        SELECT CONCAT('Successfully processed ', 
                      (LENGTH(id_list) - LENGTH(REPLACE(id_list, ',', ''))) + 1, 
                      ' records with IDs: ', id_list) AS result;
    ELSE
        -- Return message when no records found
        SELECT CONCAT('No matching records found for serial: ', 
                     p_serial, 
                     ' with lastmod >= ', 
                     p_starting_lastmod) AS result;
    END IF;
END //

DELIMITER ; 
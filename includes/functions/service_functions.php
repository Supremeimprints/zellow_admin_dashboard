<?php
function getAvailableTechnicians($db, $service_type) {
    $query = "SELECT t.*, 
                     COUNT(ta.assignment_id) as current_assignments
              FROM technicians t
              LEFT JOIN technician_assignments ta 
                ON t.technician_id = ta.technician_id 
                AND ta.status = 'in_progress'
              WHERE t.status = 'active' 
              AND t.specialization = ?
              GROUP BY t.technician_id
              HAVING current_assignments < t.max_assignments
              ORDER BY current_assignments ASC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$service_type]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function assignServiceTechnician($db, $service_request_id, $technician_id) {
    try {
        $db->beginTransaction();
        
        // Create assignment
        $query = "INSERT INTO technician_assignments (
            service_request_id,
            technician_id,
            status
        ) VALUES (?, ?, 'pending')";
        
        $stmt = $db->prepare($query);
        $stmt->execute([$service_request_id, $technician_id]);
        
        // Update service request status
        $updateQuery = "UPDATE service_requests 
                       SET status = 'assigned', 
                           technician_id = ? 
                       WHERE id = ?";
        
        $stmt = $db->prepare($updateQuery);
        $stmt->execute([$technician_id, $service_request_id]);
        
        $db->commit();
        return true;
        
    } catch (Exception $e) {
        $db->rollBack();
        error_log("Error assigning technician: " . $e->getMessage());
        return false;
    }
}

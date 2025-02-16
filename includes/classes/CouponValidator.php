<?php
class CouponValidator {
    private $db;
    
    public function __construct($db) {
        $this->db = $db;
        // Auto-update expired coupons status
        $this->updateExpiredCoupons();
    }
    
    /**
     * Automatically update status of expired coupons
     */
    private function updateExpiredCoupons() {
        try {
            $stmt = $this->db->prepare("
                UPDATE coupons 
                SET status = 'inactive' 
                WHERE status = 'active' 
                AND (
                    (end_date IS NOT NULL AND end_date < CURRENT_DATE)
                    OR 
                    (expiration_date < CURRENT_DATE)
                )
            ");
            $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating expired coupons: " . $e->getMessage());
        }
    }
    
    /**
     * Validate coupon and check all conditions
     */
    public function validateCoupon($couponCode, $userId = null, $orderTotal = 0) {
        try {
            // Check basic coupon validity
            $stmt = $this->db->prepare("
                SELECT * FROM coupons 
                WHERE code = ? 
                AND status = 'active'
                AND (start_date IS NULL OR start_date <= CURRENT_DATE)
                AND (
                    (end_date IS NULL AND expiration_date >= CURRENT_DATE)
                    OR 
                    (end_date IS NOT NULL AND end_date >= CURRENT_DATE)
                )
            ");
            $stmt->execute([$couponCode]);
            $coupon = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$coupon) {
                return ['valid' => false, 'message' => 'Invalid or expired coupon'];
            }
            
            // Validate minimum order amount
            if ($orderTotal < $coupon['min_order_amount']) {
                return [
                    'valid' => false, 
                    'message' => "Order total must be at least Ksh." . number_format($coupon['min_order_amount'], 2)
                ];
            }
            
            // Check total usage limit
            if ($coupon['usage_limit_total'] > 0) {
                $usageCount = $this->getTotalUsageCount($coupon['coupon_id']);
                if ($usageCount >= $coupon['usage_limit_total']) {
                    // Auto-update status if limit reached
                    $this->deactivateCoupon($coupon['coupon_id']);
                    return ['valid' => false, 'message' => 'Coupon has reached maximum usage limit'];
                }
            }
            
            // Check per-user usage limit
            if ($userId && $coupon['usage_limit_per_user'] > 0) {
                $userUsageCount = $this->getUserUsageCount($coupon['coupon_id'], $userId);
                if ($userUsageCount >= $coupon['usage_limit_per_user']) {
                    return ['valid' => false, 'message' => 'You have reached the usage limit for this coupon'];
                }
            }
            
            return [
                'valid' => true,
                'message' => 'Coupon is valid',
                'discount_type' => $coupon['discount_type'],
                'discount_value' => $coupon['discount_type'] === 'percentage' ? 
                    $coupon['discount_percentage'] : $coupon['discount_value'],
                'coupon_id' => $coupon['coupon_id']
            ];
            
        } catch (PDOException $e) {
            error_log("Error validating coupon: " . $e->getMessage());
            return ['valid' => false, 'message' => 'Error validating coupon'];
        }
    }
    
    /**
     * Get total usage count for a coupon
     */
    private function getTotalUsageCount($couponId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM coupon_usage 
            WHERE coupon_id = ?
        ");
        $stmt->execute([$couponId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Get usage count for a specific user
     */
    private function getUserUsageCount($couponId, $userId) {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM coupon_usage 
            WHERE coupon_id = ? AND user_id = ?
        ");
        $stmt->execute([$couponId, $userId]);
        return $stmt->fetchColumn();
    }
    
    /**
     * Deactivate a coupon
     */
    private function deactivateCoupon($couponId) {
        try {
            $stmt = $this->db->prepare("
                UPDATE coupons 
                SET status = 'inactive' 
                WHERE coupon_id = ?
            ");
            return $stmt->execute([$couponId]);
        } catch (PDOException $e) {
            error_log("Error deactivating coupon: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Record coupon usage
     */
    public function recordCouponUsage($couponId, $userId, $orderId) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO coupon_usage (coupon_id, user_id, order_id, used_at)
                VALUES (?, ?, ?, CURRENT_TIMESTAMP)
            ");
            
            if ($stmt->execute([$couponId, $userId, $orderId])) {
                // Check if usage limit is reached after recording
                $coupon = $this->db->prepare("SELECT usage_limit_total FROM coupons WHERE coupon_id = ?");
                $coupon->execute([$couponId]);
                $limit = $coupon->fetchColumn();
                
                if ($limit > 0) {
                    $usageCount = $this->getTotalUsageCount($couponId);
                    if ($usageCount >= $limit) {
                        $this->deactivateCoupon($couponId);
                    }
                }
                return true;
            }
            return false;
        } catch (PDOException $e) {
            error_log("Error recording coupon usage: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get coupon details
     */
    public function getCouponDetails($couponId) {
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       COUNT(cu.usage_id) as total_uses,
                       (
                           CASE 
                               WHEN c.usage_limit_total > 0 
                               THEN c.usage_limit_total - COUNT(cu.usage_id)
                               ELSE NULL 
                           END
                       ) as remaining_uses
                FROM coupons c
                LEFT JOIN coupon_usage cu ON c.coupon_id = cu.coupon_id
                WHERE c.coupon_id = ?
                GROUP BY c.coupon_id
            ");
            $stmt->execute([$couponId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting coupon details: " . $e->getMessage());
            return false;
        }
    }
}

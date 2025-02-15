CREATE TABLE campaign_metrics (
    metric_id INT PRIMARY KEY AUTO_INCREMENT,
    campaign_id INT NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    spend DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (campaign_id) REFERENCES marketing_campaigns(campaign_id)
);

-- Add some sample data
INSERT INTO campaign_metrics (campaign_id, impressions, clicks, conversions, spend, created_at)
VALUES 
(1, 1200, 85, 12, 150.00, NOW() - INTERVAL 1 DAY),
(1, 1500, 95, 15, 175.00, NOW() - INTERVAL 2 DAY),
(1, 1100, 75, 10, 125.00, NOW() - INTERVAL 3 DAY);

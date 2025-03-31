ALTER TABLE feedback
ADD COLUMN product_id INT NULL,
ADD COLUMN admin_reply TEXT NULL,
ADD COLUMN replied_by INT NULL,
ADD COLUMN replied_at TIMESTAMP NULL,
ADD FOREIGN KEY (product_id) REFERENCES products(product_id),
ADD FOREIGN KEY (replied_by) REFERENCES users(id);

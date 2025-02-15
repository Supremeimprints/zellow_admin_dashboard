-- Update foreign key references in related tables
ALTER TABLE products 
MODIFY COLUMN supplier_id INT,
ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id);

ALTER TABLE purchase_orders 
MODIFY COLUMN supplier_id INT,
ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id);

ALTER TABLE invoices 
MODIFY COLUMN supplier_id INT,
ADD FOREIGN KEY (supplier_id) REFERENCES suppliers(supplier_id);

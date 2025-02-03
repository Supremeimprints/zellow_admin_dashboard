# Zellow Enterprises Management System

## Enterprise Resource Planning (ERP) Solution

### Senior Year Project - Computer Science 2023/2024

## Project Overview

An integrated enterprise resource planning system developed for Zellow Enterprises to streamline their logistics, inventory, and administrative operations. This comprehensive Admin Dashboard manages the entire business workflow from inventory tracking to employee management and customer service.

## Technical Stack

- **Backend:** PHP 8.1, MySQL 8.0
- **Frontend:** HTML5, CSS3, JavaScript, Bootstrap 5.3
- **Server:** Apache 2.4
- **Development Environment:** XAMPP
- **Version Control:** Git
- **Database Design:** MySQL Workbench

## Core Features

- **Multi-level Authentication System**

  - Role-based access control (Admin, Managers, Staff)
  - Secure password hashing using PHP's native password_hash()
  - Session management and security

- **Inventory Management**

  - Real-time stock tracking
  - Low stock alerts
  - Automated reorder points
  - Product categorization

- **Employee Management**

  - Staff profiles and roles
  - Performance tracking
  - Attendance monitoring
  - Task assignment system

- **Vehicle Fleet Management**

  - Driver assignment
  - Vehicle maintenance tracking
  - Route optimization
  - Real-time status updates

- **Order Processing System**

  - Order lifecycle management
  - Automated status updates
  - Invoice generation
  - Payment tracking

- **Customer Relationship Management**
  - Customer profiles
  - Service history
  - Feedback management
  - Communication logs

## Database Architecture

- Normalized to 3NF
- Implements foreign key constraints
- Optimized queries with proper indexing
- Stored procedures for complex operations
- Transaction management for data integrity

## Security Implementation

- SQL injection prevention
- XSS protection
- CSRF tokens
- Input validation
- Secure session handling

## Future Enhancements

- API integration for third-party services
- Mobile application development
- Real-time analytics dashboard
- Machine learning for inventory prediction
- Integration with accounting software

## Installation

1. Clone repository to XAMPP's htdocs directory
2. Import database schema from `database/schema.sql`
3. Configure database connection in `config/database.php`
4. Run `composer install` for dependencies
5. Access via `localhost/zellow_admin`

## Project Structure

```
zellow_admin/
├── actions/
├── admins/
├── ajax/
├── assets/
├── authentication/
│   ├── login.php
│   ├── logout.php
│   └── register.php
├── config/
│   ├── database.php
│   └── config.production.php
├── css/
│   ├── bootstrap.min.css
│   └── style.css
├── database/
│   └── schema.sql
├── dispatch/
├── includes/
│   ├── config.php
│   ├── connection.php
│   ├── footer.php
│   ├── header.php
│   └── nav/
│       └── navbar.php
├── js/
│   ├── bootstrap.bundle.min.js
│   └── script.js
├── .htaccess
├── 404.php
├── add_admin.php
├── add_product.php
├── add_services.php
├── add_supplier.php
├── admins.php
├── categories.php
├── composer.json
├── composer.lock
├── create_driver.php
├── create_order.php
├── customers.php
├── dashboard.php
├── delete_admin.php
├── delete_product.php
├── dispatch.php
├── dispatch_order.php
├── edit_admin.php
├── edit_category.php
├── edit_driver.php
├── edit_product.php
├── edit_services.php
├── index.php
├── inventory.php
├── notifications.php
├── orders.php
├── products.php
├── reports.php
├── settings.php
└── README.md
```

## Contributing

If you would like to contribute to this project, please fork the repository and submit a pull request. We welcome all contributions!

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.

## Contact

For any questions or support, please contact [yourname@example.com](mailto:yourname@example.com).

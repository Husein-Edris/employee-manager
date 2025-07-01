# RT Buchhaltung - Employee Management System

A comprehensive WordPress-based employee management system built for Austrian accounting and HR compliance.

## Project Overview

This project provides a complete employee management solution featuring:

- **Client Registration System** - Secure client onboarding with company details
- **Employee Registration** - Comprehensive employee data collection with Austrian SVNR validation
- **Employee Dashboard** - Full CRUD interface for managing employee records
- **Security & Compliance** - Austrian social security number validation and data protection
- **Professional Admin Interface** - Complete administrative oversight and reporting

## System Architecture

### Technology Stack
- **WordPress** 6.4+ - Content Management System
- **Gravity Forms** - Advanced form processing and validation
- **Advanced Custom Fields (ACF)** - Structured data management
- **RT Employee Manager Plugin** - Custom employee management system
- **MySQL** - Database management
- **PHP 7.4+** - Server-side scripting

### Key Components

#### RT Employee Manager Plugin
Custom WordPress plugin providing:
- Employee and client post types
- Austrian SVNR validation system
- Security and access control
- Dashboard and reporting features
- Gravity Forms integration

#### Forms System
- **Employee Registration Form** (ID: 1) - Comprehensive employee data collection
- **Client Registration Form** (ID: 3) - Company and contact information
- **User Registration Integration** - Automated account creation

#### Security Features
- Role-based access control (Administrator, Kunden)
- Rate limiting and spam protection
- Data encryption for sensitive information
- Comprehensive audit logging
- IP tracking and security monitoring

## Installation & Setup

### Prerequisites
```bash
- WordPress 5.0+
- PHP 7.4+
- MySQL 5.7+
- Gravity Forms Plugin
- Advanced Custom Fields Plugin
- Gravity Forms Advanced Post Creation Add-on
```

### Installation Steps

1. **Clone Repository**
   ```bash
   git clone [repository-url]
   cd rt-buchhaltung
   ```

2. **Database Setup**
   - Import database from `app/sql/local.sql`
   - Configure database connection in `wp-config.php`

3. **Plugin Activation**
   - Navigate to WordPress admin > Plugins
   - Activate "RT Employee Manager"
   - Configure settings in Employee Manager > Settings

4. **Form Configuration**
   - Import Gravity Forms from exported JSON
   - Configure Advanced Post Creation feeds
   - Set up User Registration feeds

5. **ACF Configuration**
   - Field groups are auto-created by plugin
   - Optionally import custom ACF configuration

### Configuration

#### Plugin Settings
Navigate to **Employee Manager > Settings**:
- Set Employee Form ID (default: 1)
- Set Client Form ID (default: 3)
- Configure email notifications
- Enable security logging
- Set employee limits per client

#### User Roles
- **Administrator**: Full system access
- **Kunden**: Limited access to own employees only

#### Shortcodes
```php
[employee_dashboard]     // Employee management interface
[employee_form]          // Employee registration form
```

## Features

### Employee Management
- **Registration**: Comprehensive employee data collection
- **SVNR Validation**: Austrian social security number compliance
- **Status Management**: Active, Inactive, Suspended, Terminated
- **Real-time Updates**: Live status changes and notifications
- **Search & Filter**: Advanced employee search capabilities

### Client Portal
- **Secure Access**: Role-based client login system
- **Employee Dashboard**: Professional management interface
- **Statistics**: Real-time employee counts and status overview
- **Company Profile**: Editable company information

### Austrian Compliance
- **SVNR Validation**: Full Austrian social security number validation
- **Data Protection**: GDPR-compliant data handling
- **Audit Trail**: Comprehensive activity logging
- **Security**: Advanced security measures and monitoring

### Administrative Features
- **Dashboard**: System overview with statistics
- **User Management**: Extended user profiles with company data
- **Settings**: Comprehensive configuration options
- **Logs**: Security and activity monitoring
- **Reports**: Employee statistics and activity reports

## Security

### Access Control
- Role-based permissions system
- User capability filtering
- Post ownership verification
- Admin area restrictions

### Data Protection
- Input sanitization and validation
- SQL injection prevention
- XSS protection
- Data encryption for sensitive fields

### Monitoring
- Rate limiting (5 submissions/hour/IP)
- Login/logout tracking
- Activity logging with IP addresses
- Suspicious activity detection

## Database Schema

### Custom Tables
- `wp_rt_employee_logs` - Activity and security logging

### Post Types
- `angestellte` - Employee records
- `kunde` - Client company records

### User Meta Fields
- Company information (name, UID, address)
- Account settings (limits, status)
- Contact details

## Development

### File Structure
```
app/public/wp-content/plugins/rt-employee-manager/
├── rt-employee-manager.php              # Main plugin file
├── includes/
│   ├── class-custom-post-types.php      # Post type registration
│   ├── class-gravity-forms-integration.php  # Form processing
│   ├── class-acf-integration.php        # ACF field management
│   ├── class-employee-dashboard.php     # Dashboard functionality
│   ├── class-user-fields.php            # User profile extensions
│   ├── class-admin-settings.php         # Admin interface
│   └── class-security.php               # Security features
├── assets/
│   ├── js/
│   │   ├── svnr-formatting.js          # SVNR validation
│   │   └── dashboard.js                 # Dashboard interactions
│   └── css/
│       └── dashboard.css                # Frontend styling
└── README.md                            # Plugin documentation
```

### Hooks & Filters
```php
// Actions
do_action('rt_employee_created', $employee_id);
do_action('rt_employee_updated', $employee_id);
do_action('rt_employee_deleted', $employee_id);

// Filters
apply_filters('rt_employee_manager_user_capabilities', $capabilities);
apply_filters('rt_employee_manager_svnr_validation', $is_valid, $svnr);
```

## Troubleshooting

### Common Issues

1. **Forms not creating posts**
   - Verify Advanced Post Creation feed configuration
   - Check field mappings in Gravity Forms
   - Review error logs in Employee Manager > Logs

2. **SVNR validation errors**
   - Ensure field ID 53 exists in employee form
   - Check JavaScript console for errors
   - Verify validation is enabled in settings

3. **Dashboard access denied**
   - Check user roles and capabilities
   - Verify user has 'kunden' role or admin access
   - Check employer_id assignments

### Debug Mode
Enable logging in plugin settings to troubleshoot:
1. Employee Manager > Settings
2. Enable "Debug Logging"
3. Monitor logs in Employee Manager > Logs

## Contributing

### Development Setup
1. Clone repository
2. Set up local WordPress environment
3. Install required plugins
4. Configure database and forms

### Code Standards
- Follow WordPress coding standards
- Use proper sanitization and validation
- Include comprehensive error handling
- Document all functions and classes

## Support

For technical support or questions:
- Check plugin documentation
- Review error logs
- Contact development team

## License

This project is proprietary software. All rights reserved.

## Credits

**Developed by:** Edris Husein  
**Client:** RT Buchhaltung  
**Year:** 2025

Special thanks to the WordPress, Gravity Forms, and ACF communities for their excellent tools and documentation.
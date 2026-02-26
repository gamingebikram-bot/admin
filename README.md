# Mod APK Manager

A complete web-based management system for Mod APK distribution with admin and user panels.

## Features

### Admin Panel
- **Dashboard**: Overview with statistics and recent activity
- **Add Mod Name**: Create new mod entries
- **Manage Mods**: Edit, delete, and manage mod status
- **Upload Mod APK**: Upload APK files for mods
- **Mod APK List**: View all mods with APK status
- **Add License Key**: Add single or bulk license keys
- **License Key List**: Manage all license keys with filtering
- **Available Keys**: View available keys grouped by mod
- **Manage Users**: View and manage user accounts
- **Add Balance**: Add balance to user accounts
- **Transaction**: View all transaction history
- **Referral Code**: Generate and manage referral codes
- **Settings**: Account settings and password management

### User Panel
- **Dashboard**: User overview with balance and recent activity
- **Manage Keys**: Browse and purchase available license keys
- **Generate**: Future feature for custom key generation
- **Balance**: View current balance and transaction history
- **Transaction**: Detailed transaction history
- **Applications**: View purchased mods and download APKs
- **Settings**: Profile management and referral information

## Installation

1. **Database Setup**:
   - Create a MySQL database named `mod_apk_manager`
   - Update database credentials in `config/database.php`

2. **File Uploads**:
   - Create `uploads/apks/` directory for APK file storage
   - Set proper permissions (755 or 777)

3. **Web Server**:
   - Place files in your web server directory
   - Ensure PHP 7.4+ is installed
   - Enable PDO MySQL extension

4. **Default Login**:
   - Admin: `admin` / `admin123`
   - Users can register through the registration page

## Database Structure

The system automatically creates the following tables:
- `users` - User accounts and admin users
- `mods` - Mod applications
- `license_keys` - License keys for mods
- `mod_apks` - Uploaded APK files
- `transactions` - All financial transactions
- `referral_codes` - Referral system codes

## Key Features

### Security
- Password hashing using PHP's `password_hash()`
- Session-based authentication
- SQL injection prevention with prepared statements
- File upload validation

### User Experience
- Responsive Bootstrap 5 design
- Modern gradient UI
- Real-time balance updates
- Copy-to-clipboard functionality
- Drag-and-drop file uploads

### Admin Features
- Complete CRUD operations for all entities
- Bulk operations for license keys
- Transaction filtering and search
- User balance management
- Referral code generation

### User Features
- License key purchasing system
- APK download functionality
- Transaction history
- Profile management
- Referral system

## File Structure

```
├── config/
│   └── database.php          # Database configuration
├── includes/
│   ├── auth.php             # Authentication functions
│   └── functions.php        # Helper functions
├── uploads/
│   └── apks/                # APK file storage
├── admin_dashboard.php      # Admin dashboard
├── add_mod.php             # Add mod page
├── manage_mods.php         # Manage mods page
├── upload_mod.php          # Upload APK page
├── mod_list.php            # Mod list page
├── add_license.php         # Add license keys
├── licence_key_list.php    # License key list
├── available_keys.php      # Available keys view
├── manage_users.php        # User management
├── add_balance.php         # Add user balance
├── transactions.php        # Transaction history
├── referral_codes.php      # Referral management
├── settings.php            # Admin settings
├── user_dashboard.php      # User dashboard
├── user_manage_keys.php    # User key management
├── user_generate.php       # User generate page
├── user_balance.php        # User balance page
├── user_transactions.php   # User transactions
├── user_applications.php   # User applications
├── user_settings.php       # User settings
├── login.php               # Login page
├── register.php            # Registration page
├── logout.php              # Logout handler
└── index.php               # Main entry point
```

## Usage

1. **Admin Setup**:
   - Login with default admin credentials
   - Add mod names and descriptions
   - Upload APK files for mods
   - Generate license keys
   - Manage users and transactions

2. **User Registration**:
   - Users can register with referral codes
   - Welcome bonus of ₹100 for new users
   - Referral system for earning rewards

3. **Key Management**:
   - Users can browse available keys
   - Purchase keys using account balance
   - Download APK files for purchased mods
   - View transaction history

## Customization

- Modify database credentials in `config/database.php`
- Update UI colors and styling in CSS sections
- Add new features by extending the existing structure
- Customize email templates for notifications

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Web server (Apache/Nginx)
- PDO MySQL extension
- File upload support

## License

This project is open source and available under the MIT License.
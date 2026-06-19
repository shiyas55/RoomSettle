# RoomyShare - Roommate Expense Management System

RoomyShare is a complete, secure, and mobile-friendly roommate expense splitting and settlement manager. Built with pure **PHP (MySQLi)**, **MySQL**, **HTML5**, **CSS3**, **JavaScript**, and **Bootstrap 5**, it is optimized to run out-of-the-box on local environments (XAMPP/MAMP) and shared web hosting (like **InfinityFree**) inside the `htdocs` directory.

---

## 📱 Mobile App UI/UX
The application features a mobile-first responsive layout matching high-end fintech app designs:
- **Sticky Bottom Navigation (Mobile-Only)**: Instantly access Dashboard, Expenses, Settlements, and Reports. Includes a prominent, floating orange-gradient `+` button in the center to quickly log transactions.
- **Premium Bank Card Widget**: Displays roommate net balances inside a sleek, dark-gradient virtual card complete with simulated chip, contactless waves, and provider branding.
- **Dynamic Adaptations**: Automatically hides desktop sidebar collapse containers on smaller screens, keeping the mobile interface clean and focused.

---

## 🌟 Key Features

1. **Secure Access Roles**: Secure admin and member login systems using PHP Sessions, secure cookies, and hashed passwords (`password_hash` & `password_verify`).
2. **Double Splitting Schemes**:
   - **Equal Split**: Distribute expense shares equally. Employs a **penny-rounding loop** (distributes the remaining cents from division one-by-one to the first roommates) so the database shares sum up exactly to the cent.
   - **Custom Split**: Roommates owe specific custom amounts, verified by client and server-side validators.
3. **Greedy Settlements Algorithm**: Computes roommate net balances ($\text{Paid} - \text{Share} + \text{Sent} - \text{Received}$) and uses a greedy reconciliation matching loop to settle all debts with the absolute minimum number of peer-to-peer transfers.
4. **Deposit Management**: Audit and track cash advances or key security deposits.
5. **Settlement Approvals**: Logs peer payments in a `pending` state; payments must be approved by the recipient roommate or an admin before updating the balance board.
6. **Monthly Financial Reports**: Detailed breakdowns of spending by category (with visual progress bars) and roommate ledger matrices. Includes a **one-click CSV exporter**.
7. **Public Balance Board**: Read-only board accessible to guests without logging in, allowing roommates to check their balances quickly on the go.
8. **Receipt Uploads**: Secure file upload system validating file size (max 2MB), extension (JPG, JPEG, PNG, PDF), and MIME type, storing renamed receipt items in an execution-blocked `uploads/` folder.

---

## 🛠️ Security Hardening
- **CSRF Protection**: All POST forms include a cryptographically secure token checked on the server side using timing-attack-safe comparison (`hash_equals()`).
- **SQLi Prevention**: All queries use **MySQLi Prepared Statements** (`bind_param`).
- **XSS Prevention**: Clean sanitization of all user-entered strings using `htmlspecialchars` during render loops.
- **Apache Security (.htaccess)**: Blocks directory index listings, protects SQL and script files, and prevents script executions inside the uploads directory.

---

## 🚀 Setup & Installation

### Local Installation (XAMPP / MAMP / WampServer)
1. Clone this repository or download the source files into your server's root directory (e.g., `C:/xampp/htdocs/RoomSettle/`).
2. Start the **Apache** and **MySQL** services in your control panel.
3. Open your database administrator tool (e.g., phpMyAdmin):
   - Create a database named `roommate_db`.
   - Select the database and import **`database.sql`**.
4. Access the application in your browser: `http://localhost/RoomSettle/`
   - *Note: `config/database.php` automatically detects local environments and logs in with default credentials (`localhost`, `root`, blank password, database `roommate_db`).*
5. Log in with the pre-seeded roommates (all share the password **`password123`**):
   - **Administrator**: `alice@example.com`
   - **Members**: `bob@example.com`, `charlie@example.com`, `diana@example.com`

---

### Production Setup (InfinityFree Hosting)
1. **Create MySQL Database**:
   - Log in to your InfinityFree control panel.
   - Go to **MySQL Databases** and create a new database.
   - Note the **MySQL Hostname**, **MySQL Username**, **Database Name**, and **Password**.
2. **Import Database Schema**:
   - Open **phpMyAdmin** in the client area and select your database.
   - Go to the **Import** tab, upload **`database.sql`**, and run it.
3. **Configure Database Connection**:
   - Open `config/database.php` in a text editor.
   - Replace the default placeholders with your actual hosting database details:
     ```php
     $host = "sqlXXX.infinityfree.com";
     $username = "if0_XXXXXXXX";
     $password = "DATABASE_PASSWORD";
     $database = "if0_XXXXXXXX_roommate";
     ```
4. **Upload Files**:
   - Open an FTP client (like FileZilla) and connect to your hosting account.
   - Navigate to the **`htdocs/`** directory.
   - Upload all files and folders from this project directly inside the `htdocs` folder.
5. **Final Clean Up**:
   - For security, **delete** `create_admin.php` and `database.sql` from your FTP server once you have finished the setup.

---

## 📁 Project Structure
```
htdocs/
├── assets/
│   ├── css/style.css       # Custom stylesheets (typography, bank-card, mobile bottom nav)
│   └── js/script.js        # Form validation, split sum checks, and UI interactions
├── config/
│   └── database.php        # Database configurations with localhost auto-detection
├── includes/
│   ├── auth.php            # CSRF checking, secure sessions, and access role guards
│   ├── functions.php       # Greedy settlements math, penny-rounding splits, formatting
│   ├── header.php          # Meta declarations, Bootstrap imports, and mobile header navbar
│   └── footer.php          # Scripts importer and bottom mobile-navigation bar
├── uploads/                # Receipts folder (secured against file executions)
├── .htaccess               # Directory listings block and folder access restrictor
├── database.sql            # Table structures and seeded roommate logs
├── index.php               # Root entrypoint
├── login.php               # Authentication portal
├── logout.php              # Session destroyer
├── create_admin.php        # Setup wizard to create custom admins
├── dashboard.php           # Charts, summaries, and activity board
├── members.php             # Roommate profile CRUD & active status manager
├── expenses.php            # Detailed filters log and modal detailed breakdown
├── add_expense.php         # Receipt uploads & custom/equal split registration form
├── deposits.php            # Security deposit log
├── settlements.php         # Greedy calculations view & settlement log trigger
├── payments.php            # Settlement transfers confirmations ledger
├── reports.php             # Monthly breakdowns & CSV data exporter
├── public_balance.php      # Public read-only balance board (no auth required)
└── README.md               # Documentation guide
```

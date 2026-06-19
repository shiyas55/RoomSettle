<?php
// login.php
// Secure user authentication portal

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Redirect if already logged in
if (is_logged_in()) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. CSRF Validation
    $token = $_POST['csrf_token'] ?? '';
    if (!validate_csrf_token($token)) {
        die("CSRF validation failed. Invalid request token.");
    }
    
    // 2. Input Sanitization
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = "Please fill in all fields.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // 3. Database Prepared Statement Query
        $stmt = $conn->prepare("SELECT id, name, email, password_hash, role, status, avatar FROM members WHERE email = ? LIMIT 1");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res && $row = $res->fetch_assoc()) {
            // Verify status
            if ($row['status'] !== 'active') {
                $error = "This roommate account is currently deactivated.";
            }
            // Verify Password hash
            elseif (password_verify($password, $row['password_hash'])) {
                // Set secure sessions
                $_SESSION['user_id'] = (int)$row['id'];
                $_SESSION['user_name'] = $row['name'];
                $_SESSION['user_email'] = $row['email'];
                $_SESSION['user_role'] = $row['role'];
                $_SESSION['user_avatar'] = $row['avatar'];
                
                // Regenerate session ID for security
                session_regenerate_id(true);
                
                // Redirect
                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid password. Please try again.";
            }
        } else {
            $error = "No account found with this email address.";
        }
        $stmt->close();
    }
}

// Generate new CSRF token for the form
$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - RoomyShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh; background: linear-gradient(135deg, var(--slate-100), var(--primary-glow));">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-5 col-lg-4">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-slate-900"><i class="fa-solid fa-wallet text-primary me-2"></i>RoomyShare</h2>
                    <p class="text-slate-500">Roommate Expense Management System</p>
                </div>
                
                <div class="card card-custom p-4">
                    <h4 class="fw-bold text-slate-800 mb-3"><i class="fa-solid fa-right-to-bracket text-primary me-2"></i>Sign In</h4>
                    <hr class="text-slate-200 mt-0">
                    
                    <?php if (!empty($error)): ?>
                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                            <i class="fa-solid fa-circle-exclamation me-1"></i> <?php echo $error; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <form action="login.php" method="POST" autocomplete="off">
                        <!-- Hidden CSRF token field -->
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        
                        <div class="mb-3">
                            <label for="email" class="form-label form-label-custom">Email Address</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-slate-400"><i class="fa-regular fa-envelope"></i></span>
                                <input type="email" class="form-control form-control-custom border-start-0 ps-0" id="email" name="email" required placeholder="e.g., alice@example.com" value="<?php echo isset($_POST['email']) ? sanitize($_POST['email']) : ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label form-label-custom">Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-end-0 text-slate-400"><i class="fa-solid fa-lock"></i></span>
                                <input type="password" class="form-control form-control-custom border-start-0 ps-0" id="password" name="password" required placeholder="Enter password">
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2.5 mt-2">
                            <i class="fa-solid fa-right-to-bracket me-2"></i> Login to Dashboard
                        </button>
                    </form>
                    
                    <div class="text-center mt-4">
                        <p class="text-slate-400 mb-0 small">Want a quick glance at the balances?</p>
                        <a href="public_balance.php" class="text-decoration-none text-primary small fw-semibold">
                            <i class="fa-solid fa-board-list me-1"></i> View Public Balance Board
                        </a>
                    </div>
                </div>
                
                <div class="text-center mt-3">
                    <a href="create_admin.php" class="text-slate-500 text-decoration-none small">
                        <i class="fa-solid fa-gear me-1"></i> Setup Admin Account
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

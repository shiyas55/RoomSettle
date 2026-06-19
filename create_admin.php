<?php
// create_admin.php
// Setup utility to initialize the first admin account

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

$message = "";
$message_type = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Basic fields validation
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($name) || empty($email) || empty($password)) {
        $message = "Please fill in all required fields (Name, Email, and Password).";
        $message_type = "danger";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = "Please enter a valid email address.";
        $message_type = "danger";
    } elseif (strlen($password) < 6) {
        $message = "Password must be at least 6 characters long.";
        $message_type = "danger";
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT id FROM members WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $message = "This email address is already registered.";
            $message_type = "danger";
        } else {
            // Hash password and insert admin
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $role = 'admin';
            $status = 'active';
            
            $insert_stmt = $conn->prepare("INSERT INTO members (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param("ssssss", $name, $email, $phone, $password_hash, $role, $status);
            
            if ($insert_stmt->execute()) {
                $message = "Administrator account created successfully! You can now log in. <strong>IMPORTANT: Please delete create_admin.php from your server for security reasons.</strong>";
                $message_type = "success";
            } else {
                $message = "Failed to create administrator account. Error: " . $conn->error;
                $message_type = "danger";
            }
            $insert_stmt->close();
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Administrator - RoomyShare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light d-flex align-items-center" style="min-height: 100vh;">

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-5">
                <div class="text-center mb-4">
                    <h2 class="fw-bold text-slate-900"><i class="fa-solid fa-wallet text-primary me-2"></i>RoomyShare</h2>
                    <p class="text-slate-500">Initial Administrator Setup Utility</p>
                </div>
                
                <div class="card card-custom p-4">
                    <h4 class="fw-bold text-slate-800 mb-3"><i class="fa-solid fa-user-shield text-primary me-2"></i>Create Admin</h4>
                    <hr class="text-slate-200 mt-0">
                    
                    <?php if (!empty($message)): ?>
                        <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                            <?php echo $message; ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>
                    
                    <div class="alert alert-warning border-0" role="alert" style="background-color: var(--warning-glow); color: var(--warning);">
                        <i class="fa-solid fa-triangle-exclamation me-2"></i>
                        <strong>Security Notice:</strong> Delete this file (<code>create_admin.php</code>) immediately after creating your administrator account.
                    </div>
                    
                    <form action="create_admin.php" method="POST" autocomplete="off">
                        <div class="mb-3">
                            <label for="name" class="form-label form-label-custom">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="name" name="name" required placeholder="e.g., Alice Johnson">
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label form-label-custom">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-custom" id="email" name="email" required placeholder="e.g., admin@example.com">
                        </div>

                        <div class="mb-3">
                            <label for="phone" class="form-label form-label-custom">Phone Number</label>
                            <input type="text" class="form-control form-control-custom" id="phone" name="phone" placeholder="e.g., 123-456-7890">
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label form-label-custom">Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="At least 6 characters">
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 py-2.5 mt-2">
                            <i class="fa-solid fa-user-plus me-2"></i> Create Administrator Account
                        </button>
                    </form>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none text-slate-500 hover-primary small">
                            <i class="fa-solid fa-arrow-left me-1"></i> Back to Login
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

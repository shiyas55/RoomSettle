<?php
// includes/header.php
// Common Header Navigation Template

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roommate Expense Manager</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom Design Stylesheet -->
    <link href="assets/css/style.css?v=1.5" rel="stylesheet">
</head>
<body>

    <!-- Responsive Premium Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-custom">
        <div class="container">
            <a class="navbar-brand" href="<?php echo is_logged_in() ? 'dashboard.php' : 'public_balance.php'; ?>">
                <i class="fa-solid fa-wallet me-2"></i>
                <span>RoomyShare</span>
            </a>
            
            <div class="collapse navbar-collapse" id="mainNavbar">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <?php if (is_logged_in()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>" href="dashboard.php">
                                <i class="fa-solid fa-gauge"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'expenses.php' || $current_page == 'add_expense.php') ? 'active' : ''; ?>" href="expenses.php">
                                <i class="fa-solid fa-receipt"></i> Expenses
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'deposits.php') ? 'active' : ''; ?>" href="deposits.php">
                                <i class="fa-solid fa-piggy-bank"></i> Deposits
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'settlements.php') ? 'active' : ''; ?>" href="settlements.php">
                                <i class="fa-solid fa-scale-balanced"></i> Settlements
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'payments.php') ? 'active' : ''; ?>" href="payments.php">
                                <i class="fa-solid fa-hand-holding-dollar"></i> Payments
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>" href="reports.php">
                                <i class="fa-solid fa-chart-line"></i> Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo ($current_page == 'members.php') ? 'active' : ''; ?>" href="members.php">
                                <i class="fa-solid fa-users"></i> Roommates
                            </a>
                        </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a class="nav-link <?php echo ($current_page == 'public_balance.php') ? 'active' : ''; ?>" href="public_balance.php">
                            <i class="fa-solid fa-board-list"></i> Balance Board
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="d-flex align-items-center">
                <?php if (is_logged_in()): ?>
                    <div class="dropdown">
                        <a class="d-flex align-items-center text-decoration-none dropdown-toggle text-dark" href="#" role="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <?php if (!empty($_SESSION['user_avatar'])): ?>
                                <img src="<?php echo sanitize($_SESSION['user_avatar']); ?>" class="avatar-circle avatar-sm me-2" alt="Avatar">
                            <?php else: ?>
                                <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($_SESSION['user_name']); ?>;">
                                    <?php echo get_avatar_initials($_SESSION['user_name']); ?>
                                </div>
                            <?php endif; ?>
                            <span class="d-none d-sm-inline fw-semibold text-slate-800 me-1"><?php echo sanitize($_SESSION['user_name']); ?></span>
                        </a>
                        
                        <ul class="dropdown-menu dropdown-menu-end border-0 shadow-lg mt-2 p-2" aria-labelledby="userMenuDropdown" style="border-radius: var(--radius-md); z-index: 1060;">
                            <li class="px-3 py-2 text-center border-bottom mb-2">
                                <span class="d-block fw-bold text-slate-900"><?php echo sanitize($_SESSION['user_name']); ?></span>
                                <span class="badge <?php echo ($_SESSION['user_role'] === 'admin') ? 'bg-primary' : 'bg-secondary'; ?> mt-1">
                                    <?php echo strtoupper($_SESSION['user_role']); ?>
                                </span>
                            </li>
                            <li>
                                <a class="dropdown-item py-2 px-3 rounded text-danger" href="logout.php">
                                    <i class="fa-solid fa-right-from-bracket me-2"></i> Logout
                                </a>
                            </li>
                        </ul>
                    </div>
                <?php else: ?>
                    <?php if ($current_page !== 'login.php'): ?>
                        <a href="login.php" class="btn btn-primary px-4">
                            <i class="fa-solid fa-right-to-bracket me-2"></i> Login
                        </a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <!-- Main Content Wrapper -->
    <main class="container py-4">
        <!-- Display alert notifications -->
        <?php echo display_flash_messages(); ?>

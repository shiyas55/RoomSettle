<?php
// includes/footer.php
// Common Footer Template
?>
    </main>

    <!-- Footer Area -->
    <footer class="mt-auto">
        <div class="container">
            <div class="row align-items-center justify-content-between">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <p class="mb-0 text-slate-400">
                        &copy; <?php echo date("Y"); ?> <strong class="text-light">RoomyShare</strong>. All Rights Reserved.
                    </p>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <p class="mb-0 text-slate-400">
                        Built for secure and transparent living. <i class="fa-solid fa-heart text-danger ms-1"></i>
                    </p>
                </div>
            </div>
        </div>
    <?php if (is_logged_in()): ?>
        <!-- Mobile Sticky Bottom Navigation -->
        <div class="mobile-bottom-nav d-md-none">
            <div class="nav-container">
                <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i>
                    <span>Home</span>
                </a>
                <a href="expenses.php" class="nav-item <?php echo ($current_page == 'expenses.php' || $current_page == 'add_expense.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-receipt"></i>
                    <span>Expenses</span>
                </a>
                <div class="add-btn-wrapper">
                    <a href="add_expense.php" class="add-btn-circle">
                        <i class="fa-solid fa-plus"></i>
                    </a>
                </div>
                <a href="settlements.php" class="nav-item <?php echo ($current_page == 'settlements.php' || $current_page == 'payments.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-scale-balanced"></i>
                    <span>Settle</span>
                </a>
                <a href="reports.php" class="nav-item <?php echo ($current_page == 'reports.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie"></i>
                    <span>Reports</span>
                </a>
            </div>
        </div>
    <?php endif; ?>

    <!-- Bootstrap 5 JS Bundle (Includes Popper) -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Chart.js (Loaded via CDN) -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Custom Application Interactivity -->
    <script src="assets/js/script.js"></script>
</body>
</html>

<?php
// dashboard.php
// Roommate Expense Management System Main Dashboard

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Secure access
require_login();

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$is_admin_user = is_admin();

// Fetch all roommate financial records
$balances = get_roommate_balances($conn);
$my_balance = $balances[$user_id] ?? [
    'total_paid' => 0.0,
    'total_share' => 0.0,
    'payments_sent' => 0.0,
    'payments_received' => 0.0,
    'net_balance' => 0.0
];

// Calculate Greedy Settlements
$settlements = calculate_settlements($balances);

// Fetch system totals
$totals = get_system_totals($conn);

// Fetch pending payments for admin approval (if admin) or user-related pending payments
$pending_payments = [];
if ($is_admin_user) {
    $stmt = $conn->prepare("SELECT p.id, p.amount, p.date, m_from.name AS sender, m_to.name AS receiver FROM payments p JOIN members m_from ON p.from_member_id = m_from.id JOIN members m_to ON p.to_member_id = m_to.id WHERE p.status = 'pending' ORDER BY p.date DESC");
} else {
    $stmt = $conn->prepare("SELECT p.id, p.amount, p.date, m_from.name AS sender, m_to.name AS receiver FROM payments p JOIN members m_from ON p.from_member_id = m_from.id JOIN members m_to ON p.to_member_id = m_to.id WHERE p.status = 'pending' AND (p.from_member_id = ? OR p.to_member_id = ?) ORDER BY p.date DESC");
    $stmt->bind_param("ii", $user_id, $user_id);
}
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $pending_payments[] = $row;
}
$stmt->close();

// Fetch category spending for Doughnut Chart
$categories = [];
$category_amounts = [];
$cat_res = $conn->query("SELECT category, SUM(amount) AS total FROM expenses GROUP BY category");
while ($row = $cat_res->fetch_assoc()) {
    $categories[] = ucfirst($row['category']);
    $category_amounts[] = (float)$row['total'];
}

// Prepare roommate contribution data for Bar Chart
$roommate_names = [];
$roommate_contributions = [];
$roommate_shares = [];
foreach ($balances as $b) {
    $roommate_names[] = $b['name'];
    $roommate_contributions[] = $b['total_paid'];
    $roommate_shares[] = $b['total_share'];
}

// Fetch 5 most recent expenses
$recent_expenses = [];
$exp_res = $conn->query("SELECT e.id, e.title, e.category, e.amount, e.date, m.name AS payer FROM expenses e JOIN members m ON e.paid_by = m.id ORDER BY e.date DESC, e.id DESC LIMIT 5");
while ($row = $exp_res->fetch_assoc()) {
    $recent_expenses[] = $row;
}

// Header layout inclusion
require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8 mb-3 mb-md-0">
        <h2 class="fw-bold text-slate-900 mb-1">Welcome back, <?php echo sanitize($user_name); ?>!</h2>
        <p class="text-slate-500 mb-0">Here's a summary of the flat's expenses and your active balances.</p>
    </div>
    <div class="col-md-4 text-md-end">
        <div class="d-flex flex-wrap gap-2 justify-content-md-end">
            <a href="add_expense.php" class="btn btn-primary">
                <i class="fa-solid fa-plus me-1"></i> Add Expense
            </a>
            <a href="settlements.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-scale-balanced me-1"></i> Settle Up
            </a>
        </div>
    </div>
</div>

<!-- Admin Pending Payments Banner -->
<?php if (!empty($pending_payments)): ?>
    <div class="card border-0 shadow-sm mb-4" style="background-color: var(--warning-glow); border-left: 5px solid var(--warning) !important;">
        <div class="card-body py-3">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="d-flex align-items-center gap-3">
                    <span class="fs-4 text-warning"><i class="fa-solid fa-bell-exclamation"></i></span>
                    <div>
                        <h6 class="fw-bold mb-0 text-slate-800">Pending Settlement Approvals</h6>
                        <p class="mb-0 text-slate-600 small">There are payment notifications that need to be approved. Check the Payments ledger.</p>
                    </div>
                </div>
                <a href="payments.php" class="btn btn-sm btn-warning fw-semibold px-3">
                    <i class="fa-solid fa-circle-check me-1"></i> Review Payments
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- System KPI Cards Grid -->
<div class="row mb-4 g-3">
    <!-- Premium Bank Card Widget -->
    <div class="col-lg-5">
        <?php
        $net = $my_balance['net_balance'];
        $label_text = $net >= 0 ? 'To Receive (Net Balance)' : 'To Pay (Net Balance)';
        ?>
        <div class="card-bank h-100 d-flex flex-column justify-content-between">
            <div class="d-flex justify-content-between align-items-start">
                <i class="fa-solid fa-microchip card-chip"></i>
                <i class="fa-solid fa-wifi text-white-50 mt-1" style="font-size: 1.1rem; transform: rotate(90deg);"></i>
            </div>
            <div>
                <div class="card-balance-label"><?php echo $label_text; ?></div>
                <div class="card-balance">
                    <?php echo ($net < 0 ? '-' : '') . format_currency(abs($net)); ?>
                </div>
            </div>
            <div class="card-number">
                <span>••••  ••••  ••••  <?php echo sprintf("%04d", $user_id); ?></span>
                <div class="card-provider">
                    <span class="circle-1"></span>
                    <span class="circle-2"></span>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Other Stats Widgets -->
    <div class="col-lg-7">
        <div class="row g-3 h-100">
            <!-- Stat 1: Total Group Expenses -->
            <div class="col-sm-4">
                <div class="widget-stat h-100 d-flex flex-column justify-content-center">
                    <div class="stat-icon bg-primary text-white">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                    <div class="stat-number" style="font-size: 1.55rem;"><?php echo format_currency($totals['expenses']); ?></div>
                    <div class="stat-label" style="font-size: 0.75rem;">Total Shared</div>
                </div>
            </div>
            <!-- Stat 2: My Contributions -->
            <div class="col-sm-4">
                <div class="widget-stat h-100 d-flex flex-column justify-content-center">
                    <div class="stat-icon bg-success text-white">
                        <i class="fa-solid fa-hand-holding-hand"></i>
                    </div>
                    <div class="stat-number" style="font-size: 1.55rem;"><?php echo format_currency($my_balance['total_paid']); ?></div>
                    <div class="stat-label" style="font-size: 0.75rem;">My Contributions</div>
                </div>
            </div>
            <!-- Stat 3: My Share -->
            <div class="col-sm-4">
                <div class="widget-stat h-100 d-flex flex-column justify-content-center">
                    <div class="stat-icon bg-info text-white">
                        <i class="fa-solid fa-user-tag"></i>
                    </div>
                    <div class="stat-number" style="font-size: 1.55rem;"><?php echo format_currency($my_balance['total_share']); ?></div>
                    <div class="stat-label" style="font-size: 0.75rem;">My Share Owed</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Visualizations and Calculations Panel -->
<div class="row mb-4">
    <!-- Chart Column: Category Breakdown -->
    <div class="col-lg-5 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-chart-pie me-2 text-primary"></i>Expenses by Category</span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height: 280px;">
                <?php if (empty($categories)): ?>
                    <p class="text-slate-400 small my-5"><i class="fa-solid fa-chart-line me-1"></i> No expense data available.</p>
                <?php else: ?>
                    <div style="position: relative; height:240px; width:100%">
                        <canvas id="categoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chart Column: Roommate Contribution Comparison -->
    <div class="col-lg-7 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-chart-column me-2 text-primary"></i>Contributions vs Share</span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height: 280px;">
                <?php if (empty($roommate_names)): ?>
                    <p class="text-slate-400 small my-5"><i class="fa-solid fa-chart-line me-1"></i> No roommate data available.</p>
                <?php else: ?>
                    <div style="position: relative; height:240px; width:100%">
                        <canvas id="roommateChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Left Column: Recent Activity Feed -->
    <div class="col-lg-7 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Recent Shared Expenses</span>
                <a href="expenses.php" class="text-decoration-none small text-primary fw-semibold">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recent_expenses)): ?>
                    <div class="text-center py-5">
                        <span class="text-slate-400"><i class="fa-regular fa-receipt d-block fs-3 mb-2"></i>No expenses added yet.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>Category</th>
                                    <th>Paid By</th>
                                    <th>Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_expenses as $exp): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold text-slate-800"><?php echo sanitize($exp['title']); ?></div>
                                            <small class="text-slate-400"><?php echo date("M d, Y", strtotime($exp['date'])); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge badge-custom badge-<?php echo $exp['category']; ?>">
                                                <i class="fa-solid <?php echo get_category_icon($exp['category']); ?> me-1"></i>
                                                <?php echo ucfirst($exp['category']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo sanitize($exp['payer']); ?></td>
                                        <td class="fw-bold text-slate-900"><?php echo format_currency($exp['amount']); ?></td>
                                    </tr>
                                <?php endindex: ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Right Column: Quick Settlement Steps -->
    <div class="col-lg-5 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-scale-balanced me-2 text-primary"></i>Settlement Details</span>
                <a href="settlements.php" class="text-decoration-none small text-primary fw-semibold">Record Payment</a>
            </div>
            <div class="card-body">
                <?php if (empty($settlements)): ?>
                    <div class="text-center py-5">
                        <span class="text-success fs-3 d-block mb-2"><i class="fa-solid fa-circle-check"></i></span>
                        <h6 class="fw-bold text-slate-800">Everyone is fully settled!</h6>
                        <p class="text-slate-500 mb-0 small">No transactions are currently required.</p>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 small mb-3">The following payments resolve all roommate shares with the minimum transfers:</p>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($settlements as $settle): ?>
                            <li class="list-group-item px-0 py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-2">
                                    <span class="fw-bold text-danger"><?php echo sanitize($settle['from_name']); ?></span>
                                    <span class="text-slate-400 small"><i class="fa-solid fa-arrow-right"></i> pays</span>
                                    <span class="fw-bold text-success"><?php echo sanitize($settle['to_name']); ?></span>
                                </div>
                                <span class="badge bg-slate-100 text-slate-800 fs-6 fw-bold px-3 py-2 border rounded">
                                    <?php echo format_currency($settle['amount']); ?>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Output JSON data safely for Chart.js rendering -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    // 1. Doughnut Chart: Expenses by Category
    const categoryCtx = document.getElementById('categoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categories); ?>,
                datasets: [{
                    data: <?php echo json_encode($category_amounts); ?>,
                    backgroundColor: [
                        '#4361ee', // Rent (indigo)
                        '#f59e0b', // Electricity (yellow)
                        '#0ea5e9', // Water (sky blue)
                        '#64748b', // Wifi (slate)
                        '#10b981', // Food (green)
                        '#ef4444', // Maintenance (red)
                        '#a855f7'  // Other (purple)
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            font: { family: 'Plus Jakarta Sans', size: 11 },
                            boxWidth: 12
                        }
                    }
                },
                cutout: '65%'
            }
        });
    }

    // 2. Bar Chart: Roommate Contributions vs Shares
    const roommateCtx = document.getElementById('roommateChart');
    if (roommateCtx) {
        new Chart(roommateCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode($roommate_names); ?>,
                datasets: [
                    {
                        label: 'Paid (Contribution)',
                        data: <?php echo json_encode($roommate_contributions); ?>,
                        backgroundColor: '#10b981',
                        borderRadius: 6
                    },
                    {
                        label: 'Share (Owed)',
                        data: <?php echo json_encode($roommate_shares); ?>,
                        backgroundColor: '#4361ee',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { font: { family: 'Plus Jakarta Sans', size: 11 }, boxWidth: 12 }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: { color: '#f1f5f9' },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } }
                    },
                    x: {
                        grid: { display: false },
                        ticks: { font: { family: 'Plus Jakarta Sans', size: 10 } }
                    }
                }
            }
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>

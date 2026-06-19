<?php
// reports.php
// Monthly financial analytics, charts, and CSV data exporter

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];

// Get Month/Year Filter (defaults to current month)
$selected_month = $_GET['month'] ?? date('Y-m'); // format YYYY-MM
$year = substr($selected_month, 0, 4);
$month = substr($selected_month, 5, 2);

// Check if CSV export is requested
$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';

if ($export_csv) {
    // Clear buffer to prevent headers output issue
    ob_end_clean();
    
    // Set headers for download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="RoomyShare_Report_' . $selected_month . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    // Write headers
    fputcsv($output, ['Roommate Expense Report - Month: ' . $selected_month]);
    fputcsv($output, []); // blank line
    fputcsv($output, ['Date', 'Title', 'Category', 'Paid By', 'Split Type', 'Total Amount ($)', 'Notes']);
    
    // Fetch matching expenses
    $stmt = $conn->prepare("SELECT e.date, e.title, e.category, e.amount, e.split_type, e.notes, m.name AS payer_name FROM expenses e JOIN members m ON e.paid_by = m.id WHERE DATE_FORMAT(e.date, '%Y-%m') = ? ORDER BY e.date ASC");
    $stmt->bind_param("s", $selected_month);
    $stmt->execute();
    $res = $stmt->get_result();
    
    $total_monthly = 0;
    while ($row = $res->fetch_assoc()) {
        fputcsv($output, [
            $row['date'],
            $row['title'],
            ucfirst($row['category']),
            $row['payer_name'],
            ucfirst($row['split_type']),
            number_format($row['amount'], 2),
            $row['notes']
        ]);
        $total_monthly += (float)$row['amount'];
    }
    $stmt->close();
    
    fputcsv($output, []); // blank line
    fputcsv($output, ['', '', '', '', 'Total Monthly Sum:', number_format($total_monthly, 2), '']);
    
    fclose($output);
    exit;
}

// -------------------------------------------------------------------------
// Aggregations for layout
// -------------------------------------------------------------------------

// 1. Total monthly expense amount
$total_monthly_expenses = 0.0;
$stmt = $conn->prepare("SELECT SUM(amount) AS total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ?");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
    $total_monthly_expenses = (float)$row['total'];
}
$stmt->close();

// 2. Spending by category list
$category_breakdown = [];
$categories_labels = [];
$categories_values = [];
$top_category_name = "N/A";
$top_category_amt = 0.0;

$stmt = $conn->prepare("SELECT category, SUM(amount) AS total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ? GROUP BY category ORDER BY total DESC");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    $percentage = $total_monthly_expenses > 0 ? (($row['total'] / $total_monthly_expenses) * 100) : 0;
    $category_breakdown[] = [
        'category' => $row['category'],
        'total' => (float)$row['total'],
        'percentage' => $percentage
    ];
    $categories_labels[] = ucfirst($row['category']);
    $categories_values[] = (float)$row['total'];
}
$stmt->close();

if (!empty($category_breakdown)) {
    $top_category_name = ucfirst($category_breakdown[0]['category']);
    $top_category_amt = $category_breakdown[0]['total'];
}

// 3. Roommate contribution & share breakdown for the selected month
$monthly_roommates = [];

// Init list of active roommates
$res = $conn->query("SELECT id, name FROM members WHERE status = 'active' ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $monthly_roommates[$row['id']] = [
        'name' => $row['name'],
        'paid' => 0.0,
        'share' => 0.0
    ];
}

// Sum contributions paid by roommate
$stmt = $conn->prepare("SELECT paid_by, SUM(amount) AS total FROM expenses WHERE DATE_FORMAT(date, '%Y-%m') = ? GROUP BY paid_by");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (isset($monthly_roommates[$row['paid_by']])) {
        $monthly_roommates[$row['paid_by']]['paid'] = (float)$row['total'];
    }
}
$stmt->close();

// Sum shares owed by roommate
$stmt = $conn->prepare("SELECT es.member_id, SUM(es.amount) AS total FROM expense_splits es JOIN expenses e ON es.expense_id = e.id WHERE DATE_FORMAT(e.date, '%Y-%m') = ? GROUP BY es.member_id");
$stmt->bind_param("s", $selected_month);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
    if (isset($monthly_roommates[$row['member_id']])) {
        $monthly_roommates[$row['member_id']]['share'] = (float)$row['total'];
    }
}
$stmt->close();

// Find Top Contributor (Payer of the Month)
$top_payer_name = "N/A";
$top_payer_amt = 0.0;
foreach ($monthly_roommates as $m) {
    if ($m['paid'] > $top_payer_amt) {
        $top_payer_amt = $m['paid'];
        $top_payer_name = $m['name'];
    }
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-7 mb-3 mb-md-0">
        <h2 class="fw-bold text-slate-900 mb-1">Monthly Financial Report</h2>
        <p class="text-slate-500 mb-0">Review category spend distributions, roommate ledgers, and download spreadsheets.</p>
    </div>
    <div class="col-md-5">
        <form action="reports.php" method="GET" class="d-flex gap-2 justify-content-md-end flex-wrap">
            <input type="month" class="form-control form-control-custom w-auto" name="month" value="<?php echo sanitize($selected_month); ?>">
            <button type="submit" class="btn btn-outline-primary"><i class="fa-solid fa-sync"></i></button>
            <a href="reports.php?month=<?php echo $selected_month; ?>&export=csv" class="btn btn-primary">
                <i class="fa-solid fa-file-csv me-1"></i> Export CSV
            </a>
        </form>
    </div>
</div>

<!-- Report Summary cards -->
<div class="dashboard-grid mb-4">
    <!-- Stat 1: Total monthly spend -->
    <div class="widget-stat">
        <div class="stat-icon bg-primary text-white">
            <i class="fa-solid fa-calendar-check"></i>
        </div>
        <div class="stat-number"><?php echo format_currency($total_monthly_expenses); ?></div>
        <div class="stat-label">Month Total Spent</div>
    </div>
    
    <!-- Stat 2: Top category -->
    <div class="widget-stat">
        <div class="stat-icon bg-warning text-white">
            <i class="fa-solid fa-layer-group"></i>
        </div>
        <div class="stat-number" style="font-size: 1.45rem; line-height: 2.1rem;"><?php echo sanitize($top_category_name); ?></div>
        <div class="stat-label">Top Category (<?php echo format_currency($top_category_amt); ?>)</div>
    </div>

    <!-- Stat 3: Top Contributor -->
    <div class="widget-stat">
        <div class="stat-icon bg-success text-white">
            <i class="fa-solid fa-award"></i>
        </div>
        <div class="stat-number" style="font-size: 1.45rem; line-height: 2.1rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo sanitize($top_payer_name); ?></div>
        <div class="stat-label">Top Payer (<?php echo format_currency($top_payer_amt); ?>)</div>
    </div>
</div>

<div class="row">
    <!-- Category Chart -->
    <div class="col-lg-5 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-chart-pie text-primary me-2"></i>Spending Distribution</span>
            </div>
            <div class="card-body d-flex flex-column align-items-center justify-content-center" style="min-height: 280px;">
                <?php if (empty($categories_labels)): ?>
                    <p class="text-slate-400 small my-5"><i class="fa-solid fa-chart-line me-1"></i> No transactions this month.</p>
                <?php else: ?>
                    <div style="position: relative; height: 230px; width: 100%">
                        <canvas id="monthlyCategoryChart"></canvas>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Category Breakdown Table -->
    <div class="col-lg-7 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-list text-primary me-2"></i>Category Breakdown</span>
            </div>
            <div class="card-body p-0">
                <?php if (empty($category_breakdown)): ?>
                    <div class="text-center py-5">
                        <span class="text-slate-400"><i class="fa-solid fa-folder-open d-block fs-2 mb-2"></i>No category totals for this month.</span>
                    </div>
                <?php else: ?>
                    <div class="table-responsive table-responsive-custom border-0">
                        <table class="table table-custom">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Amount</th>
                                    <th>Percentage</th>
                                    <th>Visual Bar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($category_breakdown as $c): ?>
                                    <tr>
                                        <td class="fw-bold text-slate-800">
                                            <i class="fa-solid <?php echo get_category_icon($c['category']); ?> me-2"></i>
                                            <?php echo ucfirst($c['category']); ?>
                                        </td>
                                        <td class="fw-semibold text-slate-900"><?php echo format_currency($c['total']); ?></td>
                                        <td><?php echo number_format($c['percentage'], 1); ?>%</td>
                                        <td style="width: 30%;">
                                            <div class="progress" style="height: 8px; border-radius: 10px;">
                                                <div class="progress-bar bg-primary" role="progressbar" style="width: <?php echo $c['percentage']; ?>%" aria-valuenow="<?php echo $c['percentage']; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Roommate Share Grid for the Month -->
<div class="card card-custom p-0 mb-4">
    <div class="card-header-custom">
        <span class="fw-bold"><i class="fa-solid fa-users text-primary me-2"></i>Roommate Share Breakdown (<?php echo sanitize($selected_month); ?>)</span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive table-responsive-custom border-0">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Roommate</th>
                        <th>Month Contribution</th>
                        <th>Month Share Owed</th>
                        <th>Month Net Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($monthly_roommates as $m): ?>
                        <?php 
                        $m_net = $m['paid'] - $m['share'];
                        $m_class = $m_net >= 0.01 ? 'text-success fw-bold' : ($m_net < -0.01 ? 'text-danger fw-bold' : 'text-slate-400');
                        $m_label = $m_net >= 0.01 ? '+' . format_currency($m_net) : ($m_net < -0.01 ? '-' . format_currency(abs($m_net)) : format_currency(0));
                        ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center">
                                    <div class="avatar-circle avatar-sm style bg-slate-200 me-2" style="background-color: <?php echo get_avatar_bg($m['name']); ?>;">
                                        <?php echo get_avatar_initials($m['name']); ?>
                                    </div>
                                    <span class="fw-semibold text-slate-800"><?php echo sanitize($m['name']); ?></span>
                                </div>
                            </td>
                            <td><?php echo format_currency($m['paid']); ?></td>
                            <td><?php echo format_currency($m['share']); ?></td>
                            <td class="<?php echo $m_class; ?>"><?php echo $m_label; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const categoryCtx = document.getElementById('monthlyCategoryChart');
    if (categoryCtx) {
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($categories_labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($categories_values); ?>,
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
});
</script>

<?php
require_once 'includes/footer.php';
?>

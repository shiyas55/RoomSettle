<?php
// public_balance.php
// Public read-only balance board (no login required)

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Fetch roommate balances
$balances = get_roommate_balances($conn);

// Calculate Optimized Settlements
$settlements = calculate_settlements($balances);

// Fetch system totals
$totals = get_system_totals($conn);

// Since header.php expects auth.php sessions to be started, start_session_safe() is already run in includes/auth.php
require_once 'includes/header.php';
?>

<!-- Public Header Welcome Banner -->
<div class="public-header mb-4">
    <div class="container">
        <h1>RoomyShare Balance Board</h1>
        <p class="mb-0 opacity-75 text-light">Real-time roommate balances and settlement recommendations.</p>
    </div>
</div>

<div class="row mb-4">
    <!-- Stat 1: Total Group Expenses -->
    <div class="col-md-4 mb-3">
        <div class="widget-stat">
            <div class="stat-icon bg-primary text-white">
                <i class="fa-solid fa-money-bill-wave"></i>
            </div>
            <div class="stat-number"><?php echo format_currency($totals['expenses']); ?></div>
            <div class="stat-label">Total Shared Expenses</div>
        </div>
    </div>
    
    <!-- Stat 2: Active Roommates -->
    <div class="col-md-4 mb-3">
        <div class="widget-stat">
            <div class="stat-icon bg-info text-white">
                <i class="fa-solid fa-users"></i>
            </div>
            <div class="stat-number"><?php echo $totals['roommates']; ?></div>
            <div class="stat-label">Active Roommates</div>
        </div>
    </div>

    <!-- Stat 3: Total Reserve Deposits -->
    <div class="col-md-4 mb-3">
        <div class="widget-stat">
            <div class="stat-icon bg-success text-white">
                <i class="fa-solid fa-vault"></i>
            </div>
            <div class="stat-number"><?php echo format_currency($totals['deposits']); ?></div>
            <div class="stat-label">Total Reserve Fund</div>
        </div>
    </div>
</div>

<!-- Ledger and Settlement plan -->
<div class="row">
    <!-- Left Column: Balances -->
    <div class="col-lg-7 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Active Ledger Board</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive table-responsive-custom border-0">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Roommate</th>
                                <th>Total Paid</th>
                                <th>Total Share</th>
                                <th>Net Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balances as $b): ?>
                                <?php 
                                $net = $b['net_balance'];
                                $net_class = $net >= 0.01 ? 'text-success fw-bold' : ($net < -0.01 ? 'text-danger fw-bold' : 'text-slate-400');
                                $net_label = $net >= 0.01 ? '+' . format_currency($net) : ($net < -0.01 ? '-' . format_currency(abs($net)) : format_currency(0));
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($b['name']); ?>;">
                                                <?php echo get_avatar_initials($b['name']); ?>
                                            </div>
                                            <span class="fw-semibold text-slate-800"><?php echo sanitize($b['name']); ?></span>
                                        </div>
                                    </td>
                                    <td><?php echo format_currency($b['total_paid']); ?></td>
                                    <td><?php echo format_currency($b['total_share']); ?></td>
                                    <td class="<?php echo $net_class; ?>"><?php echo $net_label; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Settle directions -->
    <div class="col-lg-5 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Active Settlement Plan</span>
            </div>
            <div class="card-body">
                <?php if (empty($settlements)): ?>
                    <div class="text-center py-5">
                        <span class="text-success fs-1 d-block mb-3"><i class="fa-solid fa-circle-check"></i></span>
                        <h5 class="fw-bold text-slate-800">Perfectly Balanced!</h5>
                        <p class="text-slate-500 mb-0 small">No peer settlements are required. All roommates have paid their exact share.</p>
                    </div>
                <?php else: ?>
                    <p class="text-slate-500 small mb-3">Reconciliation transactions plan to resolve outstanding balances:</p>
                    <div class="list-group list-group-flush">
                        <?php foreach ($settlements as $s): ?>
                            <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                    <span class="fw-bold text-danger"><?php echo sanitize($s['from_name']); ?></span>
                                    <span class="text-slate-400 small"><i class="fa-solid fa-arrow-right"></i> pays</span>
                                    <span class="fw-bold text-success"><?php echo sanitize($s['to_name']); ?></span>
                                </div>
                                <span class="badge bg-slate-100 text-slate-800 fs-6 fw-bold px-3 py-1.5 border rounded">
                                    <?php echo format_currency($s['amount']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
                <div class="mt-4 pt-3 border-top text-center">
                    <p class="text-slate-400 small mb-2">Want to log payments or record new expenses?</p>
                    <a href="login.php" class="btn btn-sm btn-outline-primary px-4 py-2">
                        <i class="fa-solid fa-right-to-bracket me-1"></i> Log In to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>

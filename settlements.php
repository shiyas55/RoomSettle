<?php
// settlements.php
// Balance Sheet and Debt Settlement Calculator

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// Fetch balances
$balances = get_roommate_balances($conn);

// Calculate Optimized Settlements
$settlements = calculate_settlements($balances);

// Fetch all active roommates for selection dropdowns
$roommates = [];
$r_res = $conn->query("SELECT id, name FROM members WHERE status = 'active' ORDER BY name ASC");
while ($row = $r_res->fetch_assoc()) {
    $roommates[] = $row;
}

// Handle Recording Payment from modal (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_post();
    
    $from_member_id = (int)($_POST['from_member_id'] ?? 0);
    $to_member_id = (int)($_POST['to_member_id'] ?? 0);
    $amount = (float)($_POST['amount'] ?? 0);
    $date = $_POST['date'] ?? date("Y-m-d");
    $notes = trim($_POST['notes'] ?? '');
    
    if ($from_member_id <= 0 || $to_member_id <= 0 || $amount <= 0 || empty($date)) {
        set_flash_message('error', 'Please fill in all required fields (Payer, Recipient, Amount, and Date).');
    } elseif ($from_member_id === $to_member_id) {
        set_flash_message('error', 'Payer and Recipient cannot be the same roommate.');
    } else {
        // Record payment as pending
        $status = 'pending';
        $stmt = $conn->prepare("INSERT INTO payments (from_member_id, to_member_id, amount, date, notes, status) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("iidsss", $from_member_id, $to_member_id, $amount, $date, $notes, $status);
        
        if ($stmt->execute()) {
            set_flash_message('success', 'Settlement payment recorded successfully. <strong>Status is Pending</strong> until approved by the recipient or an administrator.');
            header("Location: payments.php");
            exit;
        } else {
            set_flash_message('error', 'Failed to record payment: ' . $conn->error);
        }
        $stmt->close();
    }
    header("Location: settlements.php");
    exit;
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-md-8 mb-3 mb-md-0">
        <h2 class="fw-bold text-slate-900 mb-1">Settlements & Balance Board</h2>
        <p class="text-slate-500 mb-0">See who stands where and clear balances with roommate peer transfers.</p>
    </div>
    <div class="col-md-4 text-md-end">
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#recordPaymentModal" id="manual-settle-btn">
            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Record Settlement
        </button>
    </div>
</div>

<!-- Grid: Balances List and Settlement Instructions -->
<div class="row">
    <!-- Left Column: Individual Balances -->
    <div class="col-lg-7 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i>Roommate Ledgers</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive table-responsive-custom border-0">
                    <table class="table table-custom">
                        <thead>
                            <tr>
                                <th>Roommate</th>
                                <th>Paid</th>
                                <th>Share</th>
                                <th>Settled</th>
                                <th>Net Position</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($balances as $b): ?>
                                <?php 
                                $net = $b['net_balance'];
                                $net_class = $net >= 0.01 ? 'text-success fw-bold' : ($net < -0.01 ? 'text-danger fw-bold' : 'text-slate-400');
                                $net_label = $net >= 0.01 ? '+' . format_currency($net) : ($net < -0.01 ? '-' . format_currency(abs($net)) : format_currency(0));
                                $settled_diff = $b['payments_sent'] - $b['payments_received'];
                                ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($b['name']); ?>;">
                                                <?php echo get_avatar_initials($b['name']); ?>
                                            </div>
                                            <div>
                                                <span class="fw-semibold text-slate-800 d-block"><?php echo sanitize($b['name']); ?></span>
                                                <span class="text-slate-400 small" style="font-size: 0.7rem;"><?php echo sanitize($b['email']); ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo format_currency($b['total_paid']); ?></td>
                                    <td><?php echo format_currency($b['total_share']); ?></td>
                                    <td class="small">
                                        <span class="text-success" title="Settlements Sent"><i class="fa-solid fa-arrow-up-right me-0.5"></i><?php echo number_format($b['payments_sent'], 0); ?></span><br>
                                        <span class="text-danger" title="Settlements Received"><i class="fa-solid fa-arrow-down-left me-0.5"></i><?php echo number_format($b['payments_received'], 0); ?></span>
                                    </td>
                                    <td class="<?php echo $net_class; ?>"><?php echo $net_label; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Column: Settlement Directions -->
    <div class="col-lg-5 mb-4">
        <div class="card card-custom h-100">
            <div class="card-header-custom">
                <span class="fw-bold"><i class="fa-solid fa-scale-balanced text-primary me-2"></i>Active Settlement Plan</span>
            </div>
            <div class="card-body d-flex flex-column justify-content-between">
                <div>
                    <?php if (empty($settlements)): ?>
                        <div class="text-center py-5">
                            <span class="text-success fs-1 d-block mb-3"><i class="fa-solid fa-circle-check"></i></span>
                            <h5 class="fw-bold text-slate-800">Perfectly Balanced!</h5>
                            <p class="text-slate-500 mb-0">All roommate shares are settled. No peer payments are required right now.</p>
                        </div>
                    <?php else: ?>
                        <p class="text-slate-500 small mb-3">The greedy reconciliation algorithm resolved all roommate positions into the following suggested peer-to-peer bank transfers:</p>
                        <div class="list-group list-group-flush mb-4">
                            <?php foreach ($settlements as $idx => $s): ?>
                                <div class="list-group-item px-0 py-3 border-bottom d-flex align-items-center justify-content-between flex-wrap gap-2">
                                    <div>
                                        <div class="d-flex align-items-center gap-1.5 flex-wrap">
                                            <span class="fw-bold text-danger"><?php echo sanitize($s['from_name']); ?></span>
                                            <span class="text-slate-400 small"><i class="fa-solid fa-arrow-right"></i> pays</span>
                                            <span class="fw-bold text-success"><?php echo sanitize($s['to_name']); ?></span>
                                        </div>
                                        <small class="text-slate-400 d-block mt-0.5">Amount: <strong><?php echo format_currency($s['amount']); ?></strong></small>
                                    </div>
                                    
                                    <button class="btn btn-sm btn-primary px-3 py-1.5 settle-trigger-btn"
                                            data-fromid="<?php echo $s['from_id']; ?>"
                                            data-toid="<?php echo $s['to_id']; ?>"
                                            data-amount="<?php echo $s['amount']; ?>"
                                            data-bs-toggle="modal"
                                            data-bs-target="#recordPaymentModal">
                                        <i class="fa-solid fa-receipt me-1"></i> Settle Up
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="alert bg-light border-0 py-2.5 px-3 mt-3 mb-0" role="alert">
                    <small class="text-slate-600"><i class="fa-solid fa-circle-info text-primary me-1"></i> Recorded settlements are saved in <strong>Pending</strong> state and must be approved by the recipient roommate before they adjust the ledger.</small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Record Settlement Payment -->
<div class="modal fade" id="recordPaymentModal" tabindex="-1" aria-labelledby="recordPaymentLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="recordPaymentLabel"><i class="fa-solid fa-hand-holding-dollar me-2 text-primary"></i>Record Settlement Transfer</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="settlements.php" method="POST">
                <?php csrf_field(); ?>
                <div class="modal-body p-4">
                    <!-- From Roommate -->
                    <div class="mb-3">
                        <label for="from_member_id" class="form-label form-label-custom">Paying Roommate (Debtor) <span class="text-danger">*</span></label>
                        <select class="form-select form-control-custom" id="from_member_id" name="from_member_id" required>
                            <option value="" disabled selected>Select Roommate</option>
                            <?php foreach ($roommates as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo sanitize($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- To Roommate -->
                    <div class="mb-3">
                        <label for="to_member_id" class="form-label form-label-custom">Receiving Roommate (Creditor) <span class="text-danger">*</span></label>
                        <select class="form-select form-control-custom" id="to_member_id" name="to_member_id" required>
                            <option value="" disabled selected>Select Roommate</option>
                            <?php foreach ($roommates as $m): ?>
                                <option value="<?php echo $m['id']; ?>"><?php echo sanitize($m['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <!-- Amount -->
                        <div class="col-6 mb-3">
                            <label for="amount" class="form-label form-label-custom">Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-slate-500">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-custom border-start-0" id="amount" name="amount" required placeholder="0.00">
                            </div>
                        </div>

                        <!-- Date -->
                        <div class="col-6 mb-3">
                            <label for="date" class="form-label form-label-custom">Transfer Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label for="notes" class="form-label form-label-custom">Notes / Transaction Reference</label>
                        <textarea class="form-control form-control-custom" id="notes" name="notes" rows="2" placeholder="e.g. Venmo transfer, bank transaction ID, or cash paid"></textarea>
                    </div>
                </div>
                <div class="modal-footer border-top bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary px-4">Log Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- JS Helper to prefill modal fields dynamically on button clicks -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const settleTriggerBtns = document.querySelectorAll(".settle-trigger-btn");
    settleTriggerBtns.forEach(btn => {
        btn.addEventListener("click", function() {
            document.getElementById("from_member_id").value = this.dataset.fromid;
            document.getElementById("to_member_id").value = this.dataset.toid;
            document.getElementById("amount").value = this.dataset.amount;
        });
    });

    const manualSettleBtn = document.getElementById("manual-settle-btn");
    if (manualSettleBtn) {
        manualSettleBtn.addEventListener("click", function() {
            // clear prefilled parameters when clicking manual payment record
            document.getElementById("from_member_id").value = "";
            document.getElementById("to_member_id").value = "";
            document.getElementById("amount").value = "";
        });
    }
});
</script>

<?php
require_once 'includes/footer.php';
?>

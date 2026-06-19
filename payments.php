<?php
// payments.php
// Settlement Payments Ledger and Approvals

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// 1. Process Status Adjustments (GET Actions)
if (isset($_GET['action']) && isset($_GET['id'])) {
    $payment_id = (int)$_GET['id'];
    $act = $_GET['action']; // 'approve' or 'reject'
    
    // Fetch payment to check authorization
    $stmt = $conn->prepare("SELECT from_member_id, to_member_id, status FROM payments WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $payment_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $row = $res->fetch_assoc()) {
        $to_member_id = (int)$row['to_member_id'];
        $status = $row['status'];
        
        if ($status !== 'pending') {
            set_flash_message('error', 'This payment transaction has already been resolved.');
        } 
        // Authorization: Only the recipient of the payment or an administrator can approve/reject
        elseif ($is_admin || $to_member_id === $user_id) {
            $new_status = ($act === 'approve') ? 'approved' : 'rejected';
            
            $upd_stmt = $conn->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $upd_stmt->bind_param("si", $new_status, $payment_id);
            if ($upd_stmt->execute()) {
                set_flash_message('success', 'Payment status updated to ' . ucfirst($new_status) . ' successfully.');
            } else {
                set_flash_message('error', 'Failed to update payment status.');
            }
            $upd_stmt->close();
        } else {
            set_flash_message('error', 'Permission denied. Only the recipient roommate or an administrator can approve this payment.');
        }
    } else {
        set_flash_message('error', 'Payment record not found.');
    }
    $stmt->close();
    header("Location: payments.php");
    exit;
}

// 2. Process Delete Action (GET)
if (isset($_GET['delete'])) {
    if (!$is_admin) {
        set_flash_message('error', 'Unauthorized. Only administrators can delete payment logs.');
    } else {
        $delete_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM payments WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            set_flash_message('success', 'Payment log deleted successfully.');
        } else {
            set_flash_message('error', 'Failed to delete payment log.');
        }
        $stmt->close();
    }
    header("Location: payments.php");
    exit;
}

// Fetch Filter
$status_filter = $_GET['status'] ?? ''; // '', 'pending', 'approved', 'rejected'

$where_clause = "";
if (!empty($status_filter)) {
    $where_clause = "WHERE p.status = '$status_filter'";
}

// 3. Fetch Payments
$payments_list = [];
$sql = "SELECT p.id, p.from_member_id, p.to_member_id, p.amount, p.date, p.notes, p.status, m_from.name AS sender_name, m_to.name AS receiver_name FROM payments p JOIN members m_from ON p.from_member_id = m_from.id JOIN members m_to ON p.to_member_id = m_to.id $where_clause ORDER BY p.date DESC, p.id DESC";
$res = $conn->query($sql);
while ($row = $res->fetch_assoc()) {
    $payments_list[] = $row;
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-8 mb-3 mb-sm-0">
        <h2 class="fw-bold text-slate-900 mb-1">Settlement Payments Log</h2>
        <p class="text-slate-500 mb-0">Review past roommate transfers, approve pending receipts, and audit settlements.</p>
    </div>
    <div class="col-sm-4 text-sm-end">
        <a href="settlements.php" class="btn btn-primary">
            <i class="fa-solid fa-scale-balanced me-1"></i> Balance Board
        </a>
    </div>
</div>

<!-- Filters Card -->
<div class="card card-custom p-3 mb-4">
    <div class="d-flex gap-2 align-items-center flex-wrap">
        <span class="text-slate-700 small fw-bold"><i class="fa-solid fa-filter text-primary me-1"></i> Filter Status:</span>
        <a href="payments.php" class="btn btn-sm <?php echo $status_filter === '' ? 'btn-primary' : 'btn-outline-secondary'; ?> px-3">All</a>
        <a href="payments.php?status=pending" class="btn btn-sm <?php echo $status_filter === 'pending' ? 'btn-warning text-white' : 'btn-outline-secondary'; ?> px-3">Pending</a>
        <a href="payments.php?status=approved" class="btn btn-sm <?php echo $status_filter === 'approved' ? 'btn-success' : 'btn-outline-secondary'; ?> px-3">Approved</a>
        <a href="payments.php?status=rejected" class="btn btn-sm <?php echo $status_filter === 'rejected' ? 'btn-danger' : 'btn-outline-secondary'; ?> px-3">Rejected</a>
    </div>
</div>

<!-- Payments Ledger -->
<div class="card card-custom p-0">
    <div class="card-header-custom">
        <span class="fw-bold"><i class="fa-solid fa-hand-holding-dollar text-primary me-2"></i>Payments Log</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($payments_list)): ?>
            <div class="text-center py-5">
                <span class="text-slate-400"><i class="fa-solid fa-hand-holding-dollar d-block fs-1 mb-2"></i>No payments registered matching criteria.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive table-responsive-custom border-0">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sender (Payer)</th>
                            <th>Recipient</th>
                            <th>Notes</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments_list as $pay): ?>
                            <?php 
                            $status = $pay['status'];
                            $badge_class = 'bg-secondary';
                            if ($status === 'pending') $badge_class = 'bg-warning text-white';
                            elseif ($status === 'approved') $badge_class = 'bg-success';
                            elseif ($status === 'rejected') $badge_class = 'bg-danger';
                            
                            $can_approve = ($status === 'pending' && ($is_admin || (int)$pay['to_member_id'] === $user_id));
                            ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($pay['date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($pay['sender_name']); ?>;">
                                            <?php echo get_avatar_initials($pay['sender_name']); ?>
                                        </div>
                                        <span class="fw-semibold text-slate-800"><?php echo sanitize($pay['sender_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($pay['receiver_name']); ?>;">
                                            <?php echo get_avatar_initials($pay['receiver_name']); ?>
                                        </div>
                                        <span class="fw-semibold text-slate-800"><?php echo sanitize($pay['receiver_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo !empty($pay['notes']) ? sanitize($pay['notes']) : '<span class="text-slate-400 small">N/A</span>'; ?></td>
                                <td class="fw-bold text-slate-900 fs-6"><?php echo format_currency($pay['amount']); ?></td>
                                <td>
                                    <span class="badge rounded-pill <?php echo $badge_class; ?> px-3 py-1.5">
                                        <?php echo strtoupper($status); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-2">
                                        <?php if ($can_approve): ?>
                                            <a href="payments.php?action=approve&id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-success px-3 py-1" title="Approve Payment">
                                                <i class="fa-solid fa-check me-0.5"></i> Approve
                                            </a>
                                            <a href="payments.php?action=reject&id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-outline-danger px-3 py-1" title="Reject Payment" onclick="return confirm('Are you sure you want to REJECT this payment record?')">
                                                <i class="fa-solid fa-xmark me-0.5"></i> Reject
                                            </a>
                                        <?php endif; ?>
                                        
                                        <?php if ($is_admin): ?>
                                            <a href="payments.php?delete=<?php echo $pay['id']; ?>" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Log" onclick="return confirmDelete('Are you sure you want to delete this payment record? This deletes the history permanently.')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        <?php endif; ?>
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

<?php
require_once 'includes/footer.php';
?>

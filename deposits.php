<?php
// deposits.php
// Security Deposits & Reserve Fund Log

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// 1. Process Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$is_admin) {
        set_flash_message('error', 'Unauthorized. Only administrators can modify deposit records.');
        header("Location: deposits.php");
        exit;
    }
    
    check_csrf_post();
    
    $action = $_POST['action'] ?? '';
    
    // A. RECORD DEPOSIT
    if ($action === 'add') {
        $member_id = (int)($_POST['member_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? date("Y-m-d");
        $notes = trim($_POST['notes'] ?? '');
        
        if ($member_id <= 0 || $amount <= 0 || empty($date)) {
            set_flash_message('error', 'Please fill in all required fields (Roommate, Amount, and Date).');
        } else {
            $stmt = $conn->prepare("INSERT INTO deposits (member_id, amount, date, notes, created_by) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("idssi", $member_id, $amount, $date, $notes, $user_id);
            if ($stmt->execute()) {
                set_flash_message('success', 'Deposit registered successfully.');
            } else {
                set_flash_message('error', 'Failed to register deposit: ' . $conn->error);
            }
            $stmt->close();
        }
    }
    
    // B. EDIT DEPOSIT
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $member_id = (int)($_POST['member_id'] ?? 0);
        $amount = (float)($_POST['amount'] ?? 0);
        $date = $_POST['date'] ?? date("Y-m-d");
        $notes = trim($_POST['notes'] ?? '');
        
        if ($id <= 0 || $member_id <= 0 || $amount <= 0 || empty($date)) {
            set_flash_message('error', 'Please fill in all required fields (Roommate, Amount, and Date).');
        } else {
            $stmt = $conn->prepare("UPDATE deposits SET member_id = ?, amount = ?, date = ?, notes = ? WHERE id = ?");
            $stmt->bind_param("idssi", $member_id, $amount, $date, $notes, $id);
            if ($stmt->execute()) {
                set_flash_message('success', 'Deposit record updated successfully.');
            } else {
                set_flash_message('error', 'Failed to update deposit record: ' . $conn->error);
            }
            $stmt->close();
        }
    }
    
    header("Location: deposits.php");
    exit;
}

// 2. Process Delete Action (GET)
if (isset($_GET['delete'])) {
    if (!$is_admin) {
        set_flash_message('error', 'Unauthorized. Only administrators can delete deposit records.');
    } else {
        $delete_id = (int)$_GET['delete'];
        $stmt = $conn->prepare("DELETE FROM deposits WHERE id = ?");
        $stmt->bind_param("i", $delete_id);
        if ($stmt->execute()) {
            set_flash_message('success', 'Deposit record deleted successfully.');
        } else {
            set_flash_message('error', 'Failed to delete deposit record.');
        }
        $stmt->close();
    }
    header("Location: deposits.php");
    exit;
}

// 3. Fetch Deposits list
$deposits_list = [];
$total_deposits_sum = 0.0;
$res = $conn->query("SELECT d.id, d.member_id, d.amount, d.date, d.notes, m_target.name AS roommate_name, m_creator.name AS creator_name FROM deposits d JOIN members m_target ON d.member_id = m_target.id JOIN members m_creator ON d.created_by = m_creator.id ORDER BY d.date DESC, d.id DESC");
while ($row = $res->fetch_assoc()) {
    $deposits_list[] = $row;
    $total_deposits_sum += (float)$row['amount'];
}

// Fetch active roommates for dropdown selects
$roommates = [];
$r_res = $conn->query("SELECT id, name FROM members WHERE status = 'active' ORDER BY name ASC");
while ($row = $r_res->fetch_assoc()) {
    $roommates[] = $row;
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-8 mb-3 mb-sm-0">
        <h2 class="fw-bold text-slate-900 mb-1">Deposit Fund Ledger</h2>
        <p class="text-slate-500 mb-0">Track roommate house advances or security deposit pools.</p>
    </div>
    <div class="col-sm-4 text-sm-end">
        <?php if ($is_admin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepositModal">
                <i class="fa-solid fa-piggy-bank me-1"></i> Record Deposit
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Deposit Totals Dashboard Widget -->
<div class="card card-custom p-4 mb-4" style="background: linear-gradient(135deg, var(--primary), var(--secondary)); border: none;">
    <div class="card-body py-2 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div class="text-white">
            <h5 class="fw-bold mb-1 opacity-75">Total Reserve Fund Pool</h5>
            <h1 class="fw-extrabold mb-0" style="font-size: 2.5rem; letter-spacing: -0.03em;"><?php echo format_currency($total_deposits_sum); ?></h1>
        </div>
        <div class="fs-1 text-white opacity-25">
            <i class="fa-solid fa-vault"></i>
        </div>
    </div>
</div>

<!-- Deposits Log Table -->
<div class="card card-custom p-0">
    <div class="card-header-custom">
        <span class="fw-bold"><i class="fa-solid fa-list-check text-primary me-2"></i>Deposits History</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($deposits_list)): ?>
            <div class="text-center py-5">
                <span class="text-slate-400"><i class="fa-solid fa-piggy-bank d-block fs-1 mb-2"></i>No deposits recorded yet.</span>
            </div>
        <?php else: ?>
            <div class="table-responsive table-responsive-custom">
                <table class="table table-custom">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Roommate</th>
                            <th>Notes</th>
                            <th>Logged By</th>
                            <th>Amount</th>
                            <?php if ($is_admin): ?>
                                <th class="text-end">Actions</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposits_list as $dep): ?>
                            <tr>
                                <td><?php echo date("M d, Y", strtotime($dep['date'])); ?></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-circle avatar-sm me-2" style="background-color: <?php echo get_avatar_bg($dep['roommate_name']); ?>;">
                                            <?php echo get_avatar_initials($dep['roommate_name']); ?>
                                        </div>
                                        <span class="fw-semibold text-slate-800"><?php echo sanitize($dep['roommate_name']); ?></span>
                                    </div>
                                </td>
                                <td><?php echo !empty($dep['notes']) ? sanitize($dep['notes']) : '<span class="text-slate-400 small">N/A</span>'; ?></td>
                                <td><?php echo sanitize($dep['creator_name']); ?></td>
                                <td class="fw-bold text-success fs-6"><?php echo format_currency($dep['amount']); ?></td>
                                <?php if ($is_admin): ?>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <button class="btn btn-sm btn-outline-primary py-1 px-2 edit-deposit-btn"
                                                    data-id="<?php echo $dep['id']; ?>"
                                                    data-memberid="<?php echo $dep['member_id']; ?>"
                                                    data-amount="<?php echo $dep['amount']; ?>"
                                                    data-date="<?php echo $dep['date']; ?>"
                                                    data-notes="<?php echo sanitize($dep['notes']); ?>"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#editDepositModal"
                                                    title="Edit Deposit">
                                                <i class="fa-solid fa-pencil"></i>
                                            </button>
                                            <a href="deposits.php?delete=<?php echo $dep['id']; ?>" 
                                               class="btn btn-sm btn-outline-danger py-1 px-2" 
                                               title="Delete Deposit"
                                               onclick="return confirmDelete('Are you sure you want to delete this deposit record? This will adjust their total deposit balance.')">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($is_admin): ?>
    <!-- Modal: Add Deposit -->
    <div class="modal fade" id="addDepositModal" tabindex="-1" aria-labelledby="addDepositLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="addDepositLabel"><i class="fa-solid fa-piggy-bank me-2 text-primary"></i>Record Roommate Deposit</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="deposits.php" method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="member_id" class="form-label form-label-custom">Roommate <span class="text-danger">*</span></label>
                            <select class="form-select form-control-custom" id="member_id" name="member_id" required>
                                <option value="" disabled selected>Select Roommate</option>
                                <?php foreach ($roommates as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo sanitize($m['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="amount" class="form-label form-label-custom">Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-slate-500">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-custom border-start-0" id="amount" name="amount" required placeholder="0.00">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="date" class="form-label form-label-custom">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="date" name="date" required value="<?php echo date('Y-m-d'); ?>">
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label form-label-custom">Notes / Details</label>
                            <textarea class="form-control form-control-custom" id="notes" name="notes" rows="2" placeholder="e.g. Deposit for room key or monthly advance pool"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Deposit</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Deposit -->
    <div class="modal fade" id="editDepositModal" tabindex="-1" aria-labelledby="editDepositLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="editDepositLabel"><i class="fa-solid fa-pencil me-2 text-primary"></i>Modify Deposit Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="deposits.php" method="POST">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="edit_member_id" class="form-label form-label-custom">Roommate <span class="text-danger">*</span></label>
                            <select class="form-select form-control-custom" id="edit_member_id" name="member_id" required>
                                <?php foreach ($roommates as $m): ?>
                                    <option value="<?php echo $m['id']; ?>"><?php echo sanitize($m['name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_amount" class="form-label form-label-custom">Amount (₹) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text bg-white text-slate-500">₹</span>
                                <input type="number" step="0.01" min="0.01" class="form-control form-control-custom border-start-0" id="edit_amount" name="amount" required>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="edit_date" class="form-label form-label-custom">Payment Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-custom" id="edit_date" name="date" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_notes" class="form-label form-label-custom">Notes / Details</label>
                            <textarea class="form-control form-control-custom" id="edit_notes" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- JS Helper to populate Edit Deposit Modal -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const editDepBtns = document.querySelectorAll(".edit-deposit-btn");
        editDepBtns.forEach(btn => {
            btn.addEventListener("click", function() {
                document.getElementById("edit_id").value = this.dataset.id;
                document.getElementById("edit_member_id").value = this.dataset.memberid;
                document.getElementById("edit_amount").value = this.dataset.amount;
                document.getElementById("edit_date").value = this.dataset.date;
                document.getElementById("edit_notes").value = this.dataset.notes;
            });
        });
    });
    </script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>

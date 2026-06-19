<?php
// expenses.php
// Expense Listing, Filters, and Management

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

// 1. Process Delete Action (GET)
if (isset($_GET['delete'])) {
    $delete_id = (int)$_GET['delete'];
    
    // Check permission: Admin or Creator
    $stmt = $conn->prepare("SELECT created_by, attachment FROM expenses WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $delete_id);
    $stmt->execute();
    $res = $stmt->get_result();
    
    if ($res && $row = $res->fetch_assoc()) {
        if ($is_admin || (int)$row['created_by'] === $user_id) {
            // Delete attachment if exists
            $filepath = $row['attachment'];
            if (!empty($filepath) && file_exists($filepath)) {
                @unlink($filepath);
            }
            
            // Delete expense (splits are deleted automatically due to CASCADE in DB)
            $del_stmt = $conn->prepare("DELETE FROM expenses WHERE id = ?");
            $del_stmt->bind_param("i", $delete_id);
            if ($del_stmt->execute()) {
                set_flash_message('success', 'Expense deleted successfully.');
            } else {
                set_flash_message('error', 'Failed to delete expense.');
            }
            $del_stmt->close();
        } else {
            set_flash_message('error', 'Permission denied. You can only delete expenses you registered.');
        }
    } else {
        set_flash_message('error', 'Expense record not found.');
    }
    $stmt->close();
    header("Location: expenses.php");
    exit;
}

// 2. Fetch Filters
$search = trim($_GET['search'] ?? '');
$category_filter = $_GET['category'] ?? '';
$payer_filter = isset($_GET['paid_by']) ? (int)$_GET['paid_by'] : 0;
$month_filter = $_GET['month'] ?? ''; // YYYY-MM

// Fetch roommate lists for dropdown
$payers_list = [];
$p_res = $conn->query("SELECT id, name FROM members ORDER BY name ASC");
while ($row = $p_res->fetch_assoc()) {
    $payers_list[] = $row;
}

// 3. Build Filtered Query
$query_parts = [];
$params = [];
$types = "";

if (!empty($search)) {
    $query_parts[] = "(e.title LIKE ? OR e.notes LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $types .= "ss";
}

if (!empty($category_filter)) {
    $query_parts[] = "e.category = ?";
    $params[] = $category_filter;
    $types .= "s";
}

if ($payer_filter > 0) {
    $query_parts[] = "e.paid_by = ?";
    $params[] = $payer_filter;
    $types .= "i";
}

if (!empty($month_filter)) {
    $query_parts[] = "DATE_FORMAT(e.date, '%Y-%m') = ?";
    $params[] = $month_filter;
    $types .= "s";
}

$where_clause = "";
if (!empty($query_parts)) {
    $where_clause = "WHERE " . implode(" AND ", $query_parts);
}

// Main query
$sql = "SELECT e.id, e.title, e.category, e.amount, e.paid_by, e.date, e.notes, e.attachment, e.split_type, e.created_by, m_paid.name AS payer_name, m_created.name AS creator_name FROM expenses e JOIN members m_paid ON e.paid_by = m_paid.id JOIN members m_created ON e.created_by = m_created.id $where_clause ORDER BY e.date DESC, e.id DESC";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$res = $stmt->get_result();

$expenses_list = [];
$expense_ids = [];
while ($row = $res->fetch_assoc()) {
    $expenses_list[] = $row;
    $expense_ids[] = (int)$row['id'];
}
$stmt->close();

// 4. Eager-load splits map to avoid N+1 queries
$splits_map = [];
if (!empty($expense_ids)) {
    $ids_placeholder = implode(",", $expense_ids);
    $splits_query = "SELECT es.expense_id, es.member_id, es.amount, m.name AS roommate_name FROM expense_splits es JOIN members m ON es.member_id = m.id WHERE es.expense_id IN ($ids_placeholder)";
    $s_res = $conn->query($splits_query);
    while ($s_row = $s_res->fetch_assoc()) {
        $splits_map[(int)$s_row['expense_id']][] = [
            'member_id' => (int)$s_row['member_id'],
            'roommate_name' => $s_row['roommate_name'],
            'amount' => (float)$s_row['amount']
        ];
    }
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-8 mb-3 mb-sm-0">
        <h2 class="fw-bold text-slate-900 mb-1">Expenses Log</h2>
        <p class="text-slate-500 mb-0">Record bills, rent, food, and utilities shared among roommates.</p>
    </div>
    <div class="col-sm-4 text-sm-end">
        <a href="add_expense.php" class="btn btn-primary">
            <i class="fa-solid fa-plus me-1"></i> Record New Expense
        </a>
    </div>
</div>

<!-- Search & Filter Card -->
<div class="card card-custom p-4 mb-4">
    <form action="expenses.php" method="GET" autocomplete="off">
        <div class="row g-3">
            <!-- Search field -->
            <div class="col-md-3">
                <label class="form-label form-label-custom" for="search">Search Keywords</label>
                <div class="input-group">
                    <span class="input-group-text bg-white border-end-0 text-slate-400"><i class="fa-solid fa-magnifying-glass"></i></span>
                    <input type="text" class="form-control form-control-custom border-start-0 ps-0" id="search" name="search" placeholder="Search title or notes..." value="<?php echo sanitize($search); ?>">
                </div>
            </div>
            
            <!-- Category -->
            <div class="col-md-2">
                <label class="form-label form-label-custom" for="category">Category</label>
                <select class="form-select form-control-custom" id="category" name="category">
                    <option value="">All Categories</option>
                    <option value="rent" <?php echo $category_filter == 'rent' ? 'selected' : ''; ?>>Rent</option>
                    <option value="electricity" <?php echo $category_filter == 'electricity' ? 'selected' : ''; ?>>Electricity</option>
                    <option value="water" <?php echo $category_filter == 'water' ? 'selected' : ''; ?>>Water</option>
                    <option value="wifi" <?php echo $category_filter == 'wifi' ? 'selected' : ''; ?>>WiFi</option>
                    <option value="food" <?php echo $category_filter == 'food' ? 'selected' : ''; ?>>Food</option>
                    <option value="maintenance" <?php echo $category_filter == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    <option value="other" <?php echo $category_filter == 'other' ? 'selected' : ''; ?>>Other</option>
                </select>
            </div>
            
            <!-- Paid By -->
            <div class="col-md-2">
                <label class="form-label form-label-custom" for="paid_by">Paid By</label>
                <select class="form-select form-control-custom" id="paid_by" name="paid_by">
                    <option value="0">All Roommates</option>
                    <?php foreach ($payers_list as $p): ?>
                        <option value="<?php echo $p['id']; ?>" <?php echo $payer_filter === (int)$p['id'] ? 'selected' : ''; ?>>
                            <?php echo sanitize($p['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Month -->
            <div class="col-md-2">
                <label class="form-label form-label-custom" for="month">Month</label>
                <input type="month" class="form-control form-control-custom" id="month" name="month" value="<?php echo sanitize($month_filter); ?>">
            </div>

            <!-- Actions -->
            <div class="col-md-3 d-flex align-items-end gap-2">
                <button type="submit" class="btn btn-primary w-100 py-2.5">
                    <i class="fa-solid fa-filter me-1"></i> Apply Filters
                </button>
                <a href="expenses.php" class="btn btn-outline-secondary py-2.5">
                    Clear
                </a>
            </div>
        </div>
    </form>
</div>

<!-- Expenses Table -->
<div class="card card-custom p-0">
    <?php if (empty($expenses_list)): ?>
        <div class="text-center py-5">
            <span class="text-slate-400"><i class="fa-regular fa-receipt d-block fs-1 mb-2"></i>No expenses match your criteria.</span>
        </div>
    <?php else: ?>
        <div class="table-responsive table-responsive-custom">
            <table class="table table-custom">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Paid By</th>
                        <th>Split Details</th>
                        <th>Amount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($expenses_list as $exp): ?>
                        <?php 
                        $exp_id = (int)$exp['id'];
                        $splits = $splits_map[$exp_id] ?? [];
                        ?>
                        <tr>
                            <td>
                                <div class="fw-semibold text-slate-900"><?php echo date("d", strtotime($exp['date'])); ?></div>
                                <small class="text-slate-500"><?php echo date("M Y", strtotime($exp['date'])); ?></small>
                            </td>
                            <td>
                                <a href="#" class="text-decoration-none fw-bold text-slate-800 detail-trigger"
                                   data-id="<?php echo $exp_id; ?>"
                                   data-title="<?php echo sanitize($exp['title']); ?>"
                                   data-amount="<?php echo format_currency($exp['amount']); ?>"
                                   data-payer="<?php echo sanitize($exp['payer_name']); ?>"
                                   data-date="<?php echo date("F d, Y", strtotime($exp['date'])); ?>"
                                   data-category="<?php echo ucfirst($exp['category']); ?>"
                                   data-splittype="<?php echo ucfirst($exp['split_type']); ?>"
                                   data-creator="<?php echo sanitize($exp['creator_name']); ?>"
                                   data-notes="<?php echo !empty($exp['notes']) ? sanitize($exp['notes']) : 'No description provided'; ?>"
                                   data-attachment="<?php echo !empty($exp['attachment']) ? $exp['attachment'] : ''; ?>"
                                   data-splits="<?php echo sanitize(json_encode($splits)); ?>"
                                   data-bs-toggle="modal"
                                   data-bs-target="#expenseDetailModal">
                                    <?php echo sanitize($exp['title']); ?>
                                </a>
                            </td>
                            <td>
                                <span class="badge badge-custom badge-<?php echo $exp['category']; ?>">
                                    <i class="fa-solid <?php echo get_category_icon($exp['category']); ?> me-1"></i>
                                    <?php echo ucfirst($exp['category']); ?>
                                </span>
                            </td>
                            <td><?php echo sanitize($exp['payer_name']); ?></td>
                            <td>
                                <div class="avatar-group">
                                    <?php foreach ($splits as $s): ?>
                                        <div class="avatar-circle avatar-sm" style="background-color: <?php echo get_avatar_bg($s['roommate_name']); ?>;" title="<?php echo sanitize($s['roommate_name'] . ' owes ' . format_currency($s['amount'])); ?>">
                                            <?php echo get_avatar_initials($s['roommate_name']); ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </td>
                            <td class="fw-bold text-slate-900 fs-6"><?php echo format_currency($exp['amount']); ?></td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <?php if (!empty($exp['attachment'])): ?>
                                        <a href="<?php echo $exp['attachment']; ?>" target="_blank" class="btn btn-sm btn-outline-info py-1 px-2" title="View Bill/Receipt">
                                            <i class="fa-solid fa-paperclip"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if ($is_admin || (int)$exp['created_by'] === $user_id): ?>
                                        <a href="add_expense.php?id=<?php echo $exp_id; ?>" class="btn btn-sm btn-outline-primary py-1 px-2" title="Edit Expense">
                                            <i class="fa-solid fa-pencil"></i>
                                        </a>
                                        <a href="expenses.php?delete=<?php echo $exp_id; ?>" class="btn btn-sm btn-outline-danger py-1 px-2" title="Delete Expense" onclick="return confirmDelete('Are you sure you want to delete this expense? All associated splits will be permanently removed.')">
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

<!-- Modal: Expense Detail Breakdown -->
<div class="modal fade" id="expenseDetailModal" tabindex="-1" aria-labelledby="expenseDetailTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
            <div class="modal-header border-bottom">
                <h5 class="modal-title fw-bold" id="expenseDetailTitle">Expense Breakdown</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <span class="badge badge-custom badge-other mb-2" id="modal-category">Food</span>
                    <h2 class="fw-bold text-slate-900 mb-1" id="modal-title">Electricity Bill</h2>
                    <h1 class="fw-extrabold text-primary" id="modal-amount">$120.00</h1>
                    <p class="text-slate-400 small mb-0">Registered by <span id="modal-creator">Alice</span></p>
                </div>
                
                <div class="p-3 bg-light rounded border mb-4">
                    <div class="row g-2 small">
                        <div class="col-6">
                            <span class="text-slate-500 d-block">Paid By</span>
                            <strong class="text-slate-800" id="modal-payer">Bob Smith</strong>
                        </div>
                        <div class="col-6">
                            <span class="text-slate-500 d-block">Date Paid</span>
                            <strong class="text-slate-800" id="modal-date">June 15, 2026</strong>
                        </div>
                        <div class="col-6 mt-2">
                            <span class="text-slate-500 d-block">Split Type</span>
                            <strong class="text-slate-800" id="modal-splittype">Equal</strong>
                        </div>
                        <div class="col-6 mt-2" id="modal-receipt-wrapper">
                            <span class="text-slate-500 d-block">Attachment</span>
                            <strong class="text-slate-800" id="modal-receipt">No attachment</strong>
                        </div>
                    </div>
                </div>

                <h6 class="fw-bold text-slate-800 mb-2"><i class="fa-solid fa-users me-2 text-primary"></i>Roommate Splits Share:</h6>
                <ul class="list-group mb-4 border rounded" id="modal-splits-list">
                    <!-- Dynamic details appended by JS -->
                </ul>

                <h6 class="fw-bold text-slate-800 mb-2"><i class="fa-solid fa-paragraph me-2 text-primary"></i>Notes:</h6>
                <div class="p-3 border rounded bg-white small text-slate-600 mb-2" id="modal-notes" style="white-space: pre-line;">
                    No description provided.
                </div>
            </div>
            <div class="modal-footer border-top bg-light">
                <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<!-- JS helper to populate Details Modal dynamically -->
<script>
document.addEventListener("DOMContentLoaded", function() {
    const detailTriggers = document.querySelectorAll(".detail-trigger");
    detailTriggers.forEach(trigger => {
        trigger.addEventListener("click", function() {
            // Populate basic elements
            document.getElementById("expenseDetailTitle").textContent = this.dataset.title;
            document.getElementById("modal-title").textContent = this.dataset.title;
            document.getElementById("modal-category").textContent = this.dataset.category;
            document.getElementById("modal-category").className = "badge badge-custom badge-" + this.dataset.category.toLowerCase();
            document.getElementById("modal-amount").textContent = this.dataset.amount;
            document.getElementById("modal-payer").textContent = this.dataset.payer;
            document.getElementById("modal-date").textContent = this.dataset.date;
            document.getElementById("modal-splittype").textContent = this.dataset.splittype;
            document.getElementById("modal-creator").textContent = this.dataset.creator;
            document.getElementById("modal-notes").textContent = this.dataset.notes;

            // Receipt handling
            const attachment = this.dataset.attachment;
            const receiptWrapper = document.getElementById("modal-receipt-wrapper");
            if (attachment) {
                receiptWrapper.innerHTML = `<span class="text-slate-500 d-block">Attachment</span><a href="${attachment}" target="_blank" class="text-primary fw-semibold small"><i class="fa-solid fa-paperclip me-1"></i>View Bill File</a>`;
            } else {
                receiptWrapper.innerHTML = '<span class="text-slate-500 d-block">Attachment</span><span class="text-slate-400 small">No attachment</span>';
            }

            // Splits list handling
            const splits = JSON.parse(this.dataset.splits);
            const splitsList = document.getElementById("modal-splits-list");
            splitsList.innerHTML = ""; // clear list

            splits.forEach(s => {
                const li = document.createElement("li");
                li.className = "list-group-item d-flex align-items-center justify-content-between py-2.5";
                li.innerHTML = `
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle avatar-sm me-2" style="background-color: var(--primary-glow); color: var(--primary)">
                            ${s.roommate_name.split(' ').map(w => w[0]).join('').toUpperCase().substring(0,2)}
                        </div>
                        <span class="fw-semibold text-slate-700">${s.roommate_name}</span>
                    </div>
                    <span class="fw-bold text-slate-900">$${s.amount.toFixed(2)}</span>
                `;
                splitsList.appendChild(li);
            });
        });
    });
});
</script>

<?php
require_once 'includes/footer.php';
?>

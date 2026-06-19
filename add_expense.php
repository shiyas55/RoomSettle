<?php
// add_expense.php
// Create or Edit Expenses with Equal/Custom splitting and Secure Uploads

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

require_login();

$user_id = $_SESSION['user_id'];
$is_admin = is_admin();

$expense_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$is_edit = ($expense_id > 0);

// Initialize fields
$title = "";
$category = "food";
$amount = "";
$paid_by = $user_id;
$date = date("Y-m-d");
$notes = "";
$split_type = "equal";
$attachment = "";
$selected_members_splits = []; // array of member_id => split_amount

// If Editing, load the current data
if ($is_edit) {
    $stmt = $conn->prepare("SELECT title, category, amount, paid_by, date, notes, attachment, split_type, created_by FROM expenses WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $expense_id);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
        // Authorization check: Only admin or the creator can edit
        if (!$is_admin && (int)$row['created_by'] !== $user_id) {
            set_flash_message('error', 'Access denied. You can only edit expenses you created.');
            header("Location: expenses.php");
            exit;
        }
        $title = $row['title'];
        $category = $row['category'];
        $amount = (float)$row['amount'];
        $paid_by = (int)$row['paid_by'];
        $date = $row['date'];
        $notes = $row['notes'];
        $split_type = $row['split_type'];
        $attachment = $row['attachment'];
        
        // Fetch current splits
        $split_stmt = $conn->prepare("SELECT member_id, amount FROM expense_splits WHERE expense_id = ?");
        $split_stmt->bind_param("i", $expense_id);
        $split_stmt->execute();
        $split_res = $split_stmt->get_result();
        while ($split_row = $split_res->fetch_assoc()) {
            $selected_members_splits[(int)$split_row['member_id']] = (float)$split_row['amount'];
        }
        $split_stmt->close();
    } else {
        set_flash_message('error', 'Expense not found.');
        header("Location: expenses.php");
        exit;
    }
    $stmt->close();
}

// Fetch active roommates for selection
$roommates = [];
$res = $conn->query("SELECT id, name FROM members WHERE status = 'active' ORDER BY name ASC");
while ($row = $res->fetch_assoc()) {
    $roommates[] = $row;
}

// 2. Process Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf_post();

    $title = trim($_POST['title'] ?? '');
    $category = $_POST['category'] ?? 'other';
    $amount = (float)($_POST['amount'] ?? 0);
    $paid_by = (int)($_POST['paid_by'] ?? $user_id);
    $date = $_POST['date'] ?? date("Y-m-d");
    $notes = trim($_POST['notes'] ?? '');
    $split_type = $_POST['split_type'] ?? 'equal';
    
    // Validations
    $errors = [];
    if (empty($title)) $errors[] = "Title is required.";
    if ($amount <= 0) $errors[] = "Amount must be greater than zero.";
    if (empty($date)) $errors[] = "Date is required.";
    
    // Receipt File Upload Handling
    $file_uploaded = false;
    $new_attachment_path = $attachment; // retain old unless overwritten
    
    if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['attachment'];
        $file_name = $file['name'];
        $file_tmp = $file['tmp_name'];
        $file_size = $file['size'];
        
        $allowed_exts = ['jpg', 'jpeg', 'png', 'pdf'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Secure Checks
        if (!in_array($file_ext, $allowed_exts)) {
            $errors[] = "Invalid file type. Only JPG, JPEG, PNG, and PDF are allowed.";
        } elseif ($file_size > 2 * 1024 * 1024) { // 2MB Limit
            $errors[] = "File size exceeds 2MB limit.";
        } else {
            // Validate MIME type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file_tmp);
            finfo_close($finfo);
            
            $allowed_mimes = ['image/jpeg', 'image/png', 'application/pdf'];
            if (!in_array($mime, $allowed_mimes)) {
                $errors[] = "Invalid file contents (MIME mismatch).";
            } else {
                // Ensure upload folder exists
                $upload_dir = 'uploads/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                    // Add index.html to prevent browsing
                    file_put_to_file($upload_dir . 'index.html', '');
                }
                
                // Rename file securely to prevent execution hijacks
                $safe_filename = uniqid('receipt_', true) . '.' . $file_ext;
                $target_path = $upload_dir . $safe_filename;
                
                if (move_uploaded_file($file_tmp, $target_path)) {
                    $new_attachment_path = $target_path;
                    // Delete old attachment if editing
                    if ($is_edit && !empty($attachment) && file_exists($attachment)) {
                        @unlink($attachment);
                    }
                } else {
                    $errors[] = "Failed to save uploaded file.";
                }
            }
        }
    }
    
    // Parse Splits
    $splits = []; // array of [member_id => amount]
    if ($split_type === 'equal') {
        $split_members = $_POST['split_members'] ?? [];
        $num_members = count($split_members);
        
        if ($num_members === 0) {
            $errors[] = "You must select at least one roommate for equal splitting.";
        } else {
            // Mathematically distribute splits to resolve floating penny leftovers
            $total_cents = round($amount * 100);
            $base_share_cents = floor($total_cents / $num_members);
            $leftover_cents = $total_cents - ($base_share_cents * $num_members);
            
            for ($i = 0; $i < $num_members; $i++) {
                $m_id = (int)$split_members[$i];
                $m_cents = $base_share_cents;
                if ($i < $leftover_cents) {
                    $m_cents += 1; // distribute penny
                }
                $splits[$m_id] = $m_cents / 100;
            }
        }
    } else { // custom split
        $custom_shares = $_POST['custom_shares'] ?? [];
        $sum_shares = 0;
        
        foreach ($roommates as $m) {
            $m_id = $m['id'];
            $share_val = (float)($custom_shares[$m_id] ?? 0);
            if ($share_val > 0) {
                $splits[$m_id] = $share_val;
                $sum_shares += $share_val;
            }
        }
        
        // Sum validation (allow negligible float difference)
        $diff = abs($amount - $sum_shares);
        if ($diff > 0.01) {
            $errors[] = "The sum of custom splits (" . format_currency($sum_shares) . ") must equal the total expense amount (" . format_currency($amount) . "). Difference: " . format_currency($diff);
        }
    }
    
    // If no errors, commit to Database
    if (empty($errors)) {
        // Begin Transaction for atomic updates
        $conn->begin_transaction();
        
        try {
            if ($is_edit) {
                // Update Expense
                $stmt = $conn->prepare("UPDATE expenses SET title = ?, category = ?, amount = ?, paid_by = ?, date = ?, notes = ?, attachment = ?, split_type = ? WHERE id = ?");
                $stmt->bind_param("ssdissssi", $title, $category, $amount, $paid_by, $date, $notes, $new_attachment_path, $split_type, $expense_id);
                $stmt->execute();
                $stmt->close();
                
                // Clear old splits
                $conn->query("DELETE FROM expense_splits WHERE expense_id = $expense_id");
            } else {
                // Insert Expense
                $stmt = $conn->prepare("INSERT INTO expenses (title, category, amount, paid_by, date, notes, attachment, split_type, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssdisisss", $title, $category, $amount, $paid_by, $date, $notes, $new_attachment_path, $split_type, $user_id);
                $stmt->execute();
                $expense_id = $conn->insert_id;
                $stmt->close();
            }
            
            // Insert Splits
            $split_stmt = $conn->prepare("INSERT INTO expense_splits (expense_id, member_id, amount) VALUES (?, ?, ?)");
            foreach ($splits as $m_id => $split_amt) {
                $split_stmt->bind_param("iid", $expense_id, $m_id, $split_amt);
                $split_stmt->execute();
            }
            $split_stmt->close();
            
            $conn->commit();
            set_flash_message('success', $is_edit ? 'Expense updated successfully.' : 'Expense recorded successfully.');
            header("Location: expenses.php");
            exit;
        } catch (Exception $e) {
            $conn->rollback();
            $errors[] = "Database transaction failed: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        set_flash_message('error', implode('<br>', $errors));
    }
}

require_once 'includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-9 col-lg-8">
        <div class="card card-custom p-4 mb-4">
            <h3 class="fw-bold text-slate-800 mb-2">
                <i class="fa-solid <?php echo $is_edit ? 'fa-pen-to-square' : 'fa-circle-plus'; ?> text-primary me-2"></i>
                <?php echo $is_edit ? 'Edit Shared Expense' : 'Record New Expense'; ?>
            </h3>
            <p class="text-slate-500 mb-4">Fill out the form to register an expense and choose how it splits among roommates.</p>
            <hr class="text-slate-200 mt-0">
            
            <form action="add_expense.php<?php echo $is_edit ? '?id=' . $expense_id : ''; ?>" method="POST" enctype="multipart/form-data">
                <?php csrf_field(); ?>
                
                <div class="row">
                    <!-- Title -->
                    <div class="col-md-8 mb-3">
                        <label for="title" class="form-label form-label-custom">Expense Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control form-control-custom" id="title" name="title" required placeholder="e.g., June Electricity Bill" value="<?php echo sanitize($title); ?>">
                    </div>
                    
                    <!-- Category -->
                    <div class="col-md-4 mb-3">
                        <label for="category" class="form-label form-label-custom">Category <span class="text-danger">*</span></label>
                        <select class="form-select form-control-custom" id="category" name="category" required>
                            <option value="rent" <?php echo $category == 'rent' ? 'selected' : ''; ?>>Rent</option>
                            <option value="electricity" <?php echo $category == 'electricity' ? 'selected' : ''; ?>>Electricity</option>
                            <option value="water" <?php echo $category == 'water' ? 'selected' : ''; ?>>Water</option>
                            <option value="wifi" <?php echo $category == 'wifi' ? 'selected' : ''; ?>>WiFi / Internet</option>
                            <option value="food" <?php echo $category == 'food' ? 'selected' : ''; ?>>Food / Groceries</option>
                            <option value="maintenance" <?php echo $category == 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                            <option value="other" <?php echo $category == 'other' ? 'selected' : ''; ?>>Other</option>
                        </select>
                    </div>
                </div>

                <div class="row">
                    <!-- Amount -->
                    <div class="col-md-4 mb-3">
                        <label for="amount" class="form-label form-label-custom">Total Amount (₹) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <span class="input-group-text bg-white text-slate-500">₹</span>
                            <input type="number" step="0.01" min="0.01" class="form-control form-control-custom border-start-0" id="amount" name="amount" required placeholder="0.00" value="<?php echo sanitize($amount); ?>">
                        </div>
                    </div>
                    
                    <!-- Paid By -->
                    <div class="col-md-4 mb-3">
                        <label for="paid_by" class="form-label form-label-custom">Who Paid? <span class="text-danger">*</span></label>
                        <select class="form-select form-control-custom" id="paid_by" name="paid_by" required>
                            <?php foreach ($roommates as $m): ?>
                                <option value="<?php echo $m['id']; ?>" <?php echo $paid_by === (int)$m['id'] ? 'selected' : ''; ?>>
                                    <?php echo sanitize($m['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Date -->
                    <div class="col-md-4 mb-3">
                        <label for="date" class="form-label form-label-custom">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control form-control-custom" id="date" name="date" required value="<?php echo sanitize($date); ?>">
                    </div>
                </div>

                <!-- Split Type Selector -->
                <div class="mb-3 mt-2">
                    <label class="form-label form-label-custom d-block">Split Type</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input split-type-radio" type="radio" name="split_type" id="split_equal" value="equal" <?php echo $split_type === 'equal' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-slate-700" for="split_equal">Split Equally</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input split-type-radio" type="radio" name="split_type" id="split_custom" value="custom" <?php echo $split_type === 'custom' ? 'checked' : ''; ?>>
                        <label class="form-check-label text-slate-700" for="split_custom">Split Custom</label>
                    </div>
                </div>

                <!-- EQUAL SPLIT WRAPPER -->
                <div id="equal-split-section" class="mb-4">
                    <label class="form-label form-label-custom mb-2">Select Roommates to include in split:</label>
                    <div class="row">
                        <?php foreach ($roommates as $m): ?>
                            <?php 
                            // Default checked on new, or matching split on edit
                            $is_checked = true;
                            if ($is_edit) {
                                $is_checked = isset($selected_members_splits[$m['id']]);
                            }
                            ?>
                            <div class="col-sm-6 mb-2">
                                <div class="split-member-card <?php echo $is_checked ? 'selected' : ''; ?>">
                                    <div class="d-flex align-items-center">
                                        <input class="form-check-input member-check me-2" type="checkbox" name="split_members[]" value="<?php echo $m['id']; ?>" id="chk_member_<?php echo $m['id']; ?>" <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <label class="form-check-label text-slate-800 fw-semibold" for="chk_member_<?php echo $m['id']; ?>">
                                            <?php echo sanitize($m['name']); ?>
                                        </label>
                                    </div>
                                    <span class="small text-muted fw-bold" id="share-amount-<?php echo $m['id']; ?>">$0.00</span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- CUSTOM SPLIT WRAPPER -->
                <div id="custom-split-section" class="mb-4 d-none">
                    <label class="form-label form-label-custom mb-2">Assign custom dollar share to each roommate:</label>
                    <div class="row">
                        <?php foreach ($roommates as $m): ?>
                            <?php 
                            $custom_val = "";
                            if ($is_edit && isset($selected_members_splits[$m['id']])) {
                                $custom_val = $selected_members_splits[$m['id']];
                            }
                            ?>
                            <div class="col-sm-6 mb-3">
                                <div class="p-2 border rounded bg-white d-flex align-items-center justify-content-between">
                                    <label class="form-label mb-0 fw-semibold text-slate-800 ps-1" for="custom_share_<?php echo $m['id']; ?>">
                                        <?php echo sanitize($m['name']); ?>
                                    </label>
                                    <div class="input-group" style="width: 140px;">
                                        <span class="input-group-text bg-white py-1 px-2 text-slate-500">₹</span>
                                        <input type="number" step="0.01" min="0" class="form-control form-control-custom custom-amount-input py-1 px-2" id="custom_share_<?php echo $m['id']; ?>" name="custom_shares[<?php echo $m['id']; ?>]" placeholder="0.00" value="<?php echo sanitize($custom_val); ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Split warning indicators -->
                <div class="alert bg-light py-2 px-3 border mb-4" id="split-warning">
                    <span class="text-slate-500 small"><i class="fa-solid fa-spinner me-1"></i> Waiting for splits inputs...</span>
                </div>

                <!-- Notes -->
                <div class="mb-3">
                    <label for="notes" class="form-label form-label-custom">Notes / Details</label>
                    <textarea class="form-control form-control-custom" id="notes" name="notes" rows="3" placeholder="Add descriptions, locations, bill numbers..."><?php echo sanitize($notes); ?></textarea>
                </div>

                <!-- Receipt Attachment -->
                <div class="mb-4">
                    <label class="form-label form-label-custom">Receipt / Bill Attachment</label>
                    <input type="file" class="form-control form-control-custom" id="attachment" name="attachment">
                    <div class="form-text text-slate-400 small">Supported files: JPG, JPEG, PNG, PDF. Max size: 2MB.</div>
                    
                    <?php if (!empty($attachment) && file_exists($attachment)): ?>
                        <div class="mt-2 p-2 border rounded bg-light d-flex align-items-center justify-content-between">
                            <span class="small text-slate-600"><i class="fa-solid fa-paperclip me-1 text-primary"></i> Current: <strong><?php echo basename($attachment); ?></strong></span>
                            <a href="<?php echo $attachment; ?>" target="_blank" class="btn btn-sm btn-outline-secondary py-0.5 px-2">View File</a>
                        </div>
                    <?php endif; ?>
                </div>

                <hr class="text-slate-200">
                
                <div class="d-flex gap-2 justify-content-end">
                    <a href="expenses.php" class="btn btn-secondary px-4">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4" id="submit-expense-btn">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>

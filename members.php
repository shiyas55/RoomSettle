<?php
// members.php
// Roommate Management Panel (CRUD)

require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/functions.php';

// Secure access - must be logged in to view, admin to modify
require_login();

$is_admin = is_admin();
$action_error = "";
$action_success = "";

// 1. Process Member Actions (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin checks
    if (!$is_admin) {
        set_flash_message('error', 'Unauthorized. Only administrators can modify roommate profiles.');
        header("Location: members.php");
        exit;
    }

    check_csrf_post();
    
    $action = $_POST['action'] ?? '';

    // A. ADD ROOMMATE
    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email) || empty($password)) {
            set_flash_message('error', 'Please fill in Name, Email, and Password fields.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('error', 'Please enter a valid email address.');
        } else {
            // Check email uniqueness
            $stmt = $conn->prepare("SELECT id FROM members WHERE email = ?");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                set_flash_message('error', 'Email is already registered.');
            } else {
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                $insert = $conn->prepare("INSERT INTO members (name, email, phone, password_hash, role, status) VALUES (?, ?, ?, ?, ?, ?)");
                $insert->bind_param("ssssss", $name, $email, $phone, $password_hash, $role, $status);
                if ($insert->execute()) {
                    set_flash_message('success', 'Roommate added successfully.');
                } else {
                    set_flash_message('error', 'Failed to add roommate: ' . $conn->error);
                }
                $insert->close();
            }
            $stmt->close();
        }
    }

    // B. EDIT ROOMMATE
    elseif ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'member';
        $status = $_POST['status'] ?? 'active';

        if (empty($name) || empty($email)) {
            set_flash_message('error', 'Name and Email fields are required.');
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            set_flash_message('error', 'Please enter a valid email address.');
        } else {
            // Check email uniqueness for other members
            $stmt = $conn->prepare("SELECT id FROM members WHERE email = ? AND id != ?");
            $stmt->bind_param("si", $email, $id);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                set_flash_message('error', 'Email is already taken by another roommate.');
            } else {
                if (!empty($password)) {
                    // Update including password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);
                    $update = $conn->prepare("UPDATE members SET name = ?, email = ?, phone = ?, password_hash = ?, role = ?, status = ? WHERE id = ?");
                    $update->bind_param("ssssssi", $name, $email, $phone, $password_hash, $role, $status, $id);
                } else {
                    // Update excluding password
                    $update = $conn->prepare("UPDATE members SET name = ?, email = ?, phone = ?, role = ?, status = ? WHERE id = ?");
                    $update->bind_param("sssssi", $name, $email, $phone, $role, $status, $id);
                }

                if ($update->execute()) {
                    set_flash_message('success', 'Roommate profile updated successfully.');
                } else {
                    set_flash_message('error', 'Failed to update roommate: ' . $conn->error);
                }
                $update->close();
            }
            $stmt->close();
        }
    }
    
    header("Location: members.php");
    exit;
}

// 2. Process Delete Action (GET)
if (isset($_GET['delete'])) {
    if (!$is_admin) {
        set_flash_message('error', 'Unauthorized. Only administrators can delete roommate records.');
    } else {
        $delete_id = (int)$_GET['delete'];
        
        // Prevent deleting oneself
        if ($delete_id === $_SESSION['user_id']) {
            set_flash_message('error', 'Deletions denied. You cannot delete your own profile.');
        } else {
            try {
                $stmt = $conn->prepare("DELETE FROM members WHERE id = ?");
                $stmt->bind_param("i", $delete_id);
                if ($stmt->execute()) {
                    set_flash_message('success', 'Roommate deleted successfully.');
                } else {
                    set_flash_message('error', 'Failed to delete roommate.');
                }
                $stmt->close();
            } catch (mysqli_sql_exception $e) {
                // Catch foreign key violations (roommate has expenses/splits/deposits/payments)
                set_flash_message('error', 'Cannot delete this roommate because they have active financial records. Set their status to "Inactive" instead to hide them from splits while preserving history.');
            }
        }
    }
    header("Location: members.php");
    exit;
}

// Fetch all roommates
$members_list = [];
$res = $conn->query("SELECT id, name, email, phone, role, status, created_at FROM members ORDER BY role ASC, name ASC");
while ($row = $res->fetch_assoc()) {
    $members_list[] = $row;
}

require_once 'includes/header.php';
?>

<div class="row mb-4 align-items-center">
    <div class="col-sm-8 mb-3 mb-sm-0">
        <h2 class="fw-bold text-slate-900 mb-1">Roommates Directory</h2>
        <p class="text-slate-500 mb-0">View flatmates, modify profiles, and manage system login access.</p>
    </div>
    <div class="col-sm-4 text-sm-end">
        <?php if ($is_admin): ?>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRoommateModal">
                <i class="fa-solid fa-user-plus me-1"></i> Add Roommate
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Roommates List Grid -->
<div class="row">
    <?php foreach ($members_list as $m): ?>
        <div class="col-md-6 col-lg-4 mb-4">
            <div class="card card-custom h-100">
                <div class="card-body p-4 d-flex flex-column">
                    <div class="d-flex align-items-center mb-3">
                        <div class="avatar-circle avatar-lg me-3" style="background-color: <?php echo get_avatar_bg($m['name']); ?>;">
                            <?php echo get_avatar_initials($m['name']); ?>
                        </div>
                        <div>
                            <h5 class="fw-bold text-slate-900 mb-0"><?php echo sanitize($m['name']); ?></h5>
                            <span class="badge rounded-pill <?php echo ($m['role'] === 'admin') ? 'bg-primary' : 'bg-secondary'; ?> mt-1 me-1">
                                <?php echo strtoupper($m['role']); ?>
                            </span>
                            <span class="badge rounded-pill <?php echo ($m['status'] === 'active') ? 'bg-success' : 'bg-danger'; ?> mt-1">
                                <?php echo ucfirst($m['status']); ?>
                            </span>
                        </div>
                    </div>
                    
                    <ul class="list-unstyled text-slate-600 mb-4 small mt-2">
                        <li class="mb-2"><i class="fa-regular fa-envelope me-2 text-slate-400"></i><?php echo sanitize($m['email']); ?></li>
                        <li class="mb-2">
                            <i class="fa-solid fa-phone me-2 text-slate-400"></i>
                            <?php echo !empty($m['phone']) ? sanitize($m['phone']) : '<em class="text-slate-400">No phone provided</em>'; ?>
                        </li>
                        <li><i class="fa-regular fa-calendar-plus me-2 text-slate-400"></i>Joined: <?php echo date("M d, Y", strtotime($m['created_at'])); ?></li>
                    </ul>
                    
                    <?php if ($is_admin): ?>
                        <div class="mt-auto pt-3 border-top d-flex gap-2 justify-content-end">
                            <button class="btn btn-sm btn-outline-primary px-3 edit-btn" 
                                    data-id="<?php echo $m['id']; ?>"
                                    data-name="<?php echo sanitize($m['name']); ?>"
                                    data-email="<?php echo sanitize($m['email']); ?>"
                                    data-phone="<?php echo sanitize($m['phone']); ?>"
                                    data-role="<?php echo $m['role']; ?>"
                                    data-status="<?php echo $m['status']; ?>"
                                    data-bs-toggle="modal" 
                                    data-bs-target="#editRoommateModal">
                                <i class="fa-solid fa-pencil me-1"></i> Edit
                            </button>
                            <?php if ($m['id'] !== $_SESSION['user_id']): ?>
                                <a href="members.php?delete=<?php echo $m['id']; ?>" 
                                   class="btn btn-sm btn-outline-danger px-3" 
                                   onclick="return confirmDelete('Are you sure you want to delete <?php echo sanitize(addslashes($m['name'])); ?>? This will permanently delete this roommate and ALL their recorded expenses, deposits, and payments!')">
                                    <i class="fa-solid fa-trash me-1"></i> Delete
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<?php if ($is_admin): ?>
    <!-- Modal: Add Roommate -->
    <div class="modal fade" id="addRoommateModal" tabindex="-1" aria-labelledby="addRoommateLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="addRoommateLabel"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Add New Roommate</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="members.php" method="POST" autocomplete="off">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="add">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="name" class="form-label form-label-custom">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="name" name="name" required placeholder="e.g., Bob Smith">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label form-label-custom">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-custom" id="email" name="email" required placeholder="e.g., bob@example.com">
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label form-label-custom">Phone Number</label>
                            <input type="text" class="form-control form-control-custom" id="phone" name="phone" placeholder="e.g., 234-567-8901">
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label form-label-custom">Default Password <span class="text-danger">*</span></label>
                            <input type="password" class="form-control form-control-custom" id="password" name="password" required placeholder="At least 6 characters">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="role" class="form-label form-label-custom">Role</label>
                                <select class="form-select form-control-custom" id="role" name="role">
                                    <option value="member" selected>Member</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="status" class="form-label form-label-custom">Status</label>
                                <select class="form-select form-control-custom" id="status" name="status">
                                    <option value="active" selected>Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer border-top bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4">Create Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Roommate -->
    <div class="modal fade" id="editRoommateModal" tabindex="-1" aria-labelledby="editRoommateLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content border-0 shadow-lg" style="border-radius: var(--radius-md);">
                <div class="modal-header border-bottom">
                    <h5 class="modal-title fw-bold" id="editRoommateLabel"><i class="fa-solid fa-user-gear me-2 text-primary"></i>Edit Roommate Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="members.php" method="POST" autocomplete="off">
                    <?php csrf_field(); ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" id="edit_id" name="id">
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label form-label-custom">Full Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control form-control-custom" id="edit_name" name="name" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_email" class="form-label form-label-custom">Email Address <span class="text-danger">*</span></label>
                            <input type="email" class="form-control form-control-custom" id="edit_email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="edit_phone" class="form-label form-label-custom">Phone Number</label>
                            <input type="text" class="form-control form-control-custom" id="edit_phone" name="phone">
                        </div>
                        <div class="mb-3">
                            <label for="edit_password" class="form-label form-label-custom">Reset Password</label>
                            <input type="password" class="form-control form-control-custom" id="edit_password" name="password" placeholder="Leave empty to keep current password">
                        </div>
                        <div class="row">
                            <div class="col-6 mb-3">
                                <label for="edit_role" class="form-label form-label-custom">Role</label>
                                <select class="form-select form-control-custom" id="edit_role" name="role">
                                    <option value="member">Member</option>
                                    <option value="admin">Administrator</option>
                                </select>
                            </div>
                            <div class="col-6 mb-3">
                                <label for="edit_status" class="form-label form-label-custom">Status</label>
                                <select class="form-select form-control-custom" id="edit_status" name="status">
                                    <option value="active">Active</option>
                                    <option value="inactive">Inactive</option>
                                </select>
                            </div>
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

    <!-- JS helper to populate Edit Modal fields -->
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const editButtons = document.querySelectorAll(".edit-btn");
        editButtons.forEach(btn => {
            btn.addEventListener("click", function() {
                document.getElementById("edit_id").value = this.dataset.id;
                document.getElementById("edit_name").value = this.dataset.name;
                document.getElementById("edit_email").value = this.dataset.email;
                document.getElementById("edit_phone").value = this.dataset.phone;
                document.getElementById("edit_role").value = this.dataset.role;
                document.getElementById("edit_status").value = this.dataset.status;
                document.getElementById("edit_password").value = ""; // clear password field
            });
        });
    });
    </script>
<?php endif; ?>

<?php
require_once 'includes/footer.php';
?>

<?php
// includes/functions.php
// Mathematical Models, splitting ledger, and formatting functions

// Format money values consistently
function format_currency($amount) {
    return '₹' . number_format((float)$amount, 2);
}

// Return FontAwesome icon classes based on expense categories
function get_category_icon($category) {
    switch ($category) {
        case 'rent':
            return 'fa-house-user text-primary';
        case 'electricity':
            return 'fa-bolt text-warning';
        case 'water':
            return 'fa-droplet text-info';
        case 'wifi':
            return 'fa-wifi text-secondary';
        case 'food':
            return 'fa-utensils text-success';
        case 'maintenance':
            return 'fa-wrench text-danger';
        default:
            return 'fa-receipt text-muted';
    }
}

// Generate letter initials avatar
function get_avatar_initials($name) {
    $words = explode(" ", $name);
    $initials = "";
    $count = 0;
    foreach ($words as $w) {
        if ($count < 2 && !empty($w)) {
            $initials .= strtoupper($w[0]);
            $count++;
        }
    }
    return $initials ?: '?';
}

// Generate dynamic color backgrounds for avatar initials
function get_avatar_bg($name) {
    $hash = md5($name);
    // Generate soft, modern colors
    $r = hexdec(substr($hash, 0, 2)) % 100 + 100; // 100-199
    $g = hexdec(substr($hash, 2, 2)) % 100 + 100; // 100-199
    $b = hexdec(substr($hash, 4, 2)) % 100 + 100; // 100-199
    return "rgb($r, $g, $b)";
}

// Fetch all roommate financial records
function get_roommate_balances($conn) {
    $members = [];
    
    // 1. Fetch active members
    $sql = "SELECT id, name, email, avatar, role FROM members WHERE status = 'active'";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        $members[$row['id']] = [
            'id' => (int)$row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'avatar' => $row['avatar'],
            'role' => $row['role'],
            'total_paid' => 0.0,
            'total_share' => 0.0,
            'payments_sent' => 0.0,
            'payments_received' => 0.0,
            'net_balance' => 0.0
        ];
    }
    
    // 2. Sum expenses paid by each roommate
    $sql = "SELECT paid_by, SUM(amount) AS total_paid FROM expenses GROUP BY paid_by";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        if (isset($members[$row['paid_by']])) {
            $members[$row['paid_by']]['total_paid'] = (float)$row['total_paid'];
        }
    }
    
    // 3. Sum expense shares (splits) owed by each roommate
    $sql = "SELECT member_id, SUM(amount) AS total_share FROM expense_splits GROUP BY member_id";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        if (isset($members[$row['member_id']])) {
            $members[$row['member_id']]['total_share'] = (float)$row['total_share'];
        }
    }
    
    // 4. Sum approved payments sent (from member to settle debts)
    $sql = "SELECT from_member_id, SUM(amount) AS sent FROM payments WHERE status = 'approved' GROUP BY from_member_id";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        if (isset($members[$row['from_member_id']])) {
            $members[$row['from_member_id']]['payments_sent'] = (float)$row['sent'];
        }
    }
    
    // 5. Sum approved payments received (received by member to settle debts)
    $sql = "SELECT to_member_id, SUM(amount) AS received FROM payments WHERE status = 'approved' GROUP BY to_member_id";
    $res = $conn->query($sql);
    while ($row = $res->fetch_assoc()) {
        if (isset($members[$row['to_member_id']])) {
            $members[$row['to_member_id']]['payments_received'] = (float)$row['received'];
        }
    }
    
    // 6. Calculate net balance for each member: Paid - Share + Sent - Received
    // Positive balance = Creditor (should receive money)
    // Negative balance = Debtor (should pay money)
    foreach ($members as $id => &$m) {
        $m['net_balance'] = $m['total_paid'] - $m['total_share'] + $m['payments_sent'] - $m['payments_received'];
    }
    unset($m);
    
    return $members;
}

// Calculate the optimized settlement list using a Greedy algorithm
function calculate_settlements($balances) {
    $debtors = [];
    $creditors = [];
    
    foreach ($balances as $m) {
        $bal = round($m['net_balance'], 2);
        if ($bal < -0.01) {
            $debtors[] = [
                'id' => $m['id'],
                'name' => $m['name'],
                'balance' => $bal
            ];
        } elseif ($bal > 0.01) {
            $creditors[] = [
                'id' => $m['id'],
                'name' => $m['name'],
                'balance' => $bal
            ];
        }
    }
    
    // Sort debtors ascending (e.g. -150 before -50, so most negative is first)
    usort($debtors, function($a, $b) {
        return $a['balance'] <=> $b['balance'];
    });
    
    // Sort creditors descending (e.g. 200 before 50, so most positive is first)
    usort($creditors, function($a, $b) {
        return $b['balance'] <=> $a['balance'];
    });
    
    $settlements = [];
    $d_idx = 0;
    $c_idx = 0;
    
    while ($d_idx < count($debtors) && $c_idx < count($creditors)) {
        $debtor = &$debtors[$d_idx];
        $creditor = &$creditors[$c_idx];
        
        $debt_amt = abs($debtor['balance']);
        $cred_amt = $creditor['balance'];
        
        // Find minimum of what debtor owes vs what creditor is owed
        $payment = min($debt_amt, $cred_amt);
        $payment = round($payment, 2);
        
        if ($payment > 0) {
            $settlements[] = [
                'from_id' => $debtor['id'],
                'from_name' => $debtor['name'],
                'to_id' => $creditor['id'],
                'to_name' => $creditor['name'],
                'amount' => $payment
            ];
        }
        
        // Update temporary balances
        $debtor['balance'] += $payment;
        $creditor['balance'] -= $payment;
        
        // Move pointers if balance is settled
        if (abs($debtor['balance']) < 0.01) {
            $d_idx++;
        }
        if (abs($creditor['balance']) < 0.01) {
            $c_idx++;
        }
    }
    
    return $settlements;
}

// Get aggregate stats for dashboard summaries
function get_system_totals($conn) {
    $totals = [
        'expenses' => 0.0,
        'payments' => 0.0,
        'deposits' => 0.0,
        'roommates' => 0
    ];
    
    // Total expenses
    $res = $conn->query("SELECT SUM(amount) AS total FROM expenses");
    if ($row = $res->fetch_assoc()) {
        $totals['expenses'] = (float)$row['total'];
    }
    
    // Total payments settled
    $res = $conn->query("SELECT SUM(amount) AS total FROM payments WHERE status = 'approved'");
    if ($row = $res->fetch_assoc()) {
        $totals['payments'] = (float)$row['total'];
    }
    
    // Total security deposits
    $res = $conn->query("SELECT SUM(amount) AS total FROM deposits");
    if ($row = $res->fetch_assoc()) {
        $totals['deposits'] = (float)$row['total'];
    }
    
    // Active roommates count
    $res = $conn->query("SELECT COUNT(*) AS total FROM members WHERE status = 'active'");
    if ($row = $res->fetch_assoc()) {
        $totals['roommates'] = (int)$row['total'];
    }
    
    return $totals;
}

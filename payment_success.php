<?php
session_start();
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'])) {
    $inquiry_id = $_POST['inquiry_id'];
    $amount     = $_POST['amount'];
    
    // Generate a simulated transaction reference ID
    $ref_id = 'eSewa_Mock_' . mt_rand(100000, 999999);
    
    // Update application record - Auto approve if status is currently Pending
    $stmt = $pdo->prepare("UPDATE admission_inquiries SET 
                            payment_status = 'Paid', 
                            payment_amount = ?, 
                            payment_reference = ?, 
                            payment_method = 'eSewa Mock',
                            status = IF(status = 'Pending', 'Approved', status)
                           WHERE id = ?");
    $stmt->execute([$amount, $ref_id, $inquiry_id]);
    
    // Trigger automated email (Admit Card + Receipt)
    sendPaymentConfirmationEmail($pdo, $inquiry_id);
    
    // Re-verify the session to redirect them back safely
    if (isset($_SESSION['student_id']) && $_SESSION['student_id'] == $inquiry_id) {
        header("Location: student_dashboard.php?tab=payments");
        exit;
    } else {
        // If paid by someone else (e.g. from a public link), redirect to status check
        header("Location: status_check.php");
        exit;
    }
}

// Invalid access
header("Location: index.php");
exit;

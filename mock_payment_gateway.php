<?php
session_start();
if (!isset($_POST['inquiry_id']) || !isset($_POST['amount'])) {
    die("Invalid Payment Request.");
}
$inquiry_id = $_POST['inquiry_id'];
$amount = $_POST['amount'];

// Simulate a slight completely artificial loading delay
usleep(500000); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>eSewa - Payment Gateway Gateway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>body { font-family: 'Inter', sans-serif; background-color: #f7f9fa; }</style>
</head>
<body class="flex items-center justify-center min-h-screen">

<div class="max-w-md w-full bg-white rounded flex justify-center flex-col items-center p-8 shadow-[0_0_20px_rgba(0,0,0,0.05)] border border-gray-100">
    
    <!-- Using a generic Green Theme to simulate Digital Wallet UI -->
    <div class="w-20 h-20 bg-[#60a839] text-white rounded-full flex items-center justify-center mb-6 shadow-md shadow-[#60a839]/30">
        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    </div>
    
    <h2 class="text-2xl font-bold text-gray-800 mb-1">Secure Payment</h2>
    <p class="text-sm text-gray-500 mb-8 border-b border-gray-100 pb-4 w-full text-center">App Admission Fee Collection</p>
    
    <div class="w-full bg-gray-50 rounded-lg p-5 mb-8 border border-gray-100">
        <div class="flex justify-between items-center mb-3">
            <span class="text-sm font-medium text-gray-500">Amount</span>
            <span class="text-xl font-bold text-gray-800">Rs. <?php echo number_format((float)$amount, 2); ?></span>
        </div>
        <div class="flex justify-between items-center mb-1">
            <span class="text-sm font-medium text-gray-500">Service Charge</span>
            <span class="text-sm font-bold text-gray-800">Rs. 0.00</span>
        </div>
        <div class="flex justify-between items-center">
            <span class="text-sm font-medium text-gray-500">Delivery Charge</span>
            <span class="text-sm font-bold text-gray-800">Rs. 0.00</span>
        </div>
        <hr class="my-4 border-gray-200">
        <div class="flex justify-between items-center">
            <span class="text-base font-bold text-gray-700">Total Paying Amount</span>
            <span class="text-2xl font-bold text-[#60a839]">Rs. <?php echo number_format((float)$amount, 2); ?></span>
        </div>
    </div>
    
    <form action="payment_success.php" method="POST" class="w-full">
        <input type="hidden" name="inquiry_id" value="<?php echo htmlspecialchars($inquiry_id); ?>">
        <input type="hidden" name="amount" value="<?php echo htmlspecialchars($amount); ?>">
        
        <p class="text-xs text-gray-400 text-center mb-4 leading-relaxed">
            By clicking on the button below, you will confirm your payment.<br>
            <i><strong class="text-red-400">NOTE:</strong> This is a Mock Gateway integration for testing.</i>
        </p>

        <button type="submit" class="w-full bg-[#60a839] hover:bg-[#508d2f] text-white font-bold py-3.5 px-6 rounded shadow shadow-[#60a839]/40 transition uppercase tracking-wide">
            Confirm Payment
        </button>
        <div class="mt-4 text-center">
            <a href="student_dashboard.php?tab=payments" class="text-sm font-medium text-gray-500 hover:text-gray-700">Cancel & Return to Merchant</a>
        </div>
    </form>

</div>
</body>
</html>

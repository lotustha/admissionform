<?php
// mobile_companion.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

$token = $_GET['session'] ?? '';
if (!$token) {
    die("Invalid session token.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Scanner Companion</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-black text-white h-screen flex flex-col overflow-hidden m-0">

    <div class="p-4 bg-gray-900 flex justify-between items-center z-10 shrink-0 shadow-md">
        <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-emerald-500 animate-pulse"></span>
            <h1 class="font-bold text-lg">Companion Scanner</h1>
        </div>
        <div class="text-xs text-gray-400 bg-gray-800 px-2 py-1 rounded">Session Active</div>
    </div>

    <div class="flex-1 relative flex flex-col justify-center items-center">
        <div id="reader" class="w-full h-full object-cover"></div>
        <div id="overlay" class="absolute inset-x-8 top-1/2 -translate-y-1/2 aspect-square border-2 border-emerald-500 rounded-2xl shadow-[0_0_0_4000px_rgba(0,0,0,0.6)] pointer-events-none"></div>
    </div>

    <div id="status-bar" class="absolute bottom-0 inset-x-0 p-6 bg-gradient-to-t from-black to-transparent z-20 text-center transition-transform translate-y-full">
        <div class="px-6 py-3 rounded-full font-bold shadow-lg" id="status-text"></div>
    </div>

    <script src="https://unpkg.com/html5-qrcode"></script>
    <script>
        const token = "<?php echo htmlspecialchars($token); ?>";
        let isProcessing = false;

        function showStatus(text, type = 'success') {
            const bar = document.getElementById('status-bar');
            const txt = document.getElementById('status-text');
            txt.textContent = text;
            txt.className = `px-6 py-3 rounded-full font-bold shadow-lg inline-block ${type === 'success' ? 'bg-emerald-600 text-white' : 'bg-red-600 text-white'}`;
            bar.classList.remove('translate-y-full');
            setTimeout(() => { bar.classList.add('translate-y-full'); }, 2000);
        }

        const scanner = new Html5Qrcode("reader");
        scanner.start({ facingMode: "environment" }, { fps: 10, qrbox: { width: 250, height: 250 } }, 
            async function(decodedText) {
                if (isProcessing) return;
                isProcessing = true;
                
                // Play beep sound
                const audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU'); // dummy base64, simple beep
                // Just use browser beep or vibrate
                if (navigator.vibrate) navigator.vibrate(200);

                const formData = new FormData();
                formData.append('action', 'push');
                formData.append('token', token);
                formData.append('payload', decodedText);

                try {
                    const res = await fetch('api_remote_scanner.php', { method: 'POST', body: formData });
                    const data = await res.json();
                    if (data.success) {
                        showStatus('Successfully pushed to PC!');
                        scanner.pause();
                        setTimeout(() => { scanner.resume(); isProcessing = false; }, 2000);
                    } else {
                        throw new Error('Failed');
                    }
                } catch(e) {
                    showStatus('Error pushing to PC', 'error');
                    isProcessing = false;
                }
            }, 
            function(error) { /* ignore */ }
        ).catch(err => {
            document.body.innerHTML = '<div class="p-8 text-center text-red-500 font-bold mt-20">Camera access denied or unavailable.</div>';
        });
    </script>
</body>
</html>

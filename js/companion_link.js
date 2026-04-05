// companion_link.js

let companionPollInterval = null;
let companionToken = null;
let companionLastTime = 0;

function initCompanionScanner(callbackIdOrFunction) {
    // 1. Ask server for a session token
    fetch('api_remote_scanner.php?action=create')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                companionToken = data.token;
                companionLastTime = Math.floor(Date.now() / 1000);
                
                // Show QR code Modal
                showCompanionQR(companionToken);
                
                // Start polling
                if (companionPollInterval) clearInterval(companionPollInterval);
                companionPollInterval = setInterval(() => {
                    pollCompanionScanner(callbackIdOrFunction);
                }, 1500);
            }
        });
}

function pollCompanionScanner(callback) {
    if (!companionToken) return;
    
    fetch(`api_remote_scanner.php?action=poll&token=${companionToken}&last_time=${companionLastTime}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.has_data) {
                companionLastTime = data.updated_ts;
                
                closeCompanionQR();
                
                // Play notification
                let audio = new Audio('data:audio/wav;base64,UklGRl9vT19XQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YU');
                audio.play().catch(e=>{});
                
                // Try executing it
                if (typeof callback === 'function') {
                    callback(data.payload);
                } else {
                    console.log("Companion scanned:", data.payload);
                }
            }
        });
}

function showCompanionQR(token) {
    let modal = document.getElementById('companion-qr-modal');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'companion-qr-modal';
        modal.className = 'fixed inset-0 z-[100] bg-black/80 flex items-center justify-center p-4 backdrop-blur-sm';
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl w-full max-w-sm overflow-hidden text-center relative">
                <div class="p-4 border-b border-gray-100 flex justify-between items-center bg-gray-50">
                    <h3 class="font-bold text-emerald-800">Mobile Companion Mode</h3>
                    <button onclick="closeCompanionQR()" class="text-gray-400 hover:text-red-500 bg-gray-200 p-2 rounded-full transition">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    </button>
                </div>
                <div class="p-6">
                    <p class="text-sm text-gray-500 mb-4">Scan this QR code with your mobile camera to turn your phone into a barcode scanner for this browser window.</p>
                    <div id="companion-qr-container" class="inline-block p-2 bg-white rounded-xl border border-gray-200"></div>
                    <div class="mt-4 flex items-center justify-center gap-2 text-xs font-bold text-emerald-600 animate-pulse">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span> Waiting for scans...
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
        
        // Ensure qrcode.js is available or we just use Google chart API
        if (typeof QRCode === 'undefined') {
            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
            script.onload = () => drawQR(token);
            document.head.appendChild(script);
        } else {
            drawQR(token);
        }
    } else {
        modal.classList.remove('hidden');
        document.getElementById('companion-qr-container').innerHTML = '';
        drawQR(token);
    }
}

function drawQR(token) {
    const url = window.location.origin + window.location.pathname.replace(/\/[^\/]*$/, '/') + "mobile_companion.php?session=" + token;
    new QRCode(document.getElementById('companion-qr-container'), {
        text: url,
        width: 200,
        height: 200,
        colorDark : "#065f46",
        colorLight : "#ffffff",
        correctLevel : QRCode.CorrectLevel.H
    });
}

function closeCompanionQR() {
    const modal = document.getElementById('companion-qr-modal');
    if (modal) modal.classList.add('hidden');
}

// Stop polling when closed/hidden visually but technically we can keep polling in background if they keep it linked!
// Actually let's keep polling in background. That way they can dismiss the modal and keep scanning!

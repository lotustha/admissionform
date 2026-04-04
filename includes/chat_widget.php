<?php
// includes/chat_widget.php
if (!function_exists('getRandomGeminiKey')) {
    require_once __DIR__ . '/functions.php';
}
if (!isset($pdo)) {
    require_once __DIR__ . '/connect.php';
}
$__has_key = getRandomGeminiKey($pdo) !== null;
if ($__has_key):
?>
<!-- includes/chat_widget.php -->
<link rel="stylesheet" href="assets/css/chat.css">
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<!-- Chat Widget Toggler -->
<button id="chatbot-toggle" class="fixed bottom-6 right-6 w-14 h-14 bg-indigo-600 hover:bg-indigo-700 text-white rounded-full flex items-center justify-center shadow-2xl transition hover:scale-110 z-50 overflow-hidden border-2 border-indigo-200" title="Need help?">
    <!-- Default Icon (Sparkles/Bot) -->
    <svg id="bot-icon-closed" class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" class="animate-pulse"></path>
    </svg>
    <!-- Open Icon (X) -->
    <svg id="bot-icon-open" class="w-6 h-6 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
    </svg>
</button>

<!-- Chat Window -->
<div id="chatbot-window" class="fixed bottom-24 right-6 w-80 sm:w-96 bg-white rounded-2xl shadow-2xl border border-gray-200 z-50 hidden flex-col overflow-hidden transition-all origin-bottom-right transform scale-95 opacity-0">
    <!-- Header -->
    <div class="bg-indigo-600 p-4 text-white flex justify-between items-center relative overflow-hidden shrink-0">
        <div class="absolute top-0 right-0 w-24 h-24 bg-indigo-400 rounded-full mix-blend-multiply opacity-20 transform translate-x-12 -translate-y-8"></div>
        <div class="absolute bottom-0 left-0 w-20 h-20 bg-indigo-300 rounded-full mix-blend-multiply opacity-20 transform -translate-x-8 translate-y-8"></div>
        <div class="relative z-10">
            <h3 class="font-bold tracking-wide flex items-center">
                <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                Admission Assistant
            </h3>
            <p id="chat-sub-text" class="text-xs text-indigo-100 mt-1 opacity-90 font-medium">Auto-filling form & answering queries</p>
        </div>
        <button id="chatbot-close" class="relative z-10 text-white/80 hover:text-white transition-colors" title="Close Chat">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>
    </div>

    <!-- Messages Container -->
    <div id="chat-messages" class="flex-1 p-4 overflow-y-auto bg-gray-50 flex flex-col gap-3 min-h-[300px] max-h-[400px]">
        <!-- Welcome Message -->
        <div class="flex justify-start">
            <div class="max-w-[85%] bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-sm shadow-sm text-sm text-gray-800 chat-bubble">
                Hi there! 👋 I'm your Admission Assistant.<br><br>
                To get started, <b>please tell me your full name and the class you'd like to apply for.</b> I'll fill the form for you automatically!<br><br>
                <span class="text-indigo-600 text-xs font-semibold">⚡ I can also answer questions about fees, schedule, and more.</span>
            </div>
        </div>
    </div>

    <!-- Typing Indicator -->
    <div id="typing-indicator" class="hidden absolute bottom-16 left-4 bg-white border border-gray-200 p-2 rounded-xl rounded-bl-sm shadow-sm z-10 w-16">
        <div class="flex space-x-1.5 justify-center">
            <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-bounce"></div>
            <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
            <div class="w-1.5 h-1.5 bg-indigo-500 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
        </div>
    </div>

    <!-- Handoff Panel (Appears if user needs a human) -->
    <div id="handoff-panel" class="hidden p-3 bg-red-50 border-t border-red-100 flex items-center justify-between">
        <span class="text-xs font-semibold text-red-600">Want to speak with official admins?</span>
        <button type="button" id="btn-human" class="text-xs font-bold bg-white text-red-600 border border-red-200 px-3 py-1.5 rounded-lg shadow-sm hover:bg-red-600 hover:text-white transition-colors">Talk to Human</button>
    </div>

    <!-- Input Area -->
    <div class="p-3 bg-white border-t border-gray-200 flex items-end gap-2 shrink-0">
        <div class="flex-1 bg-gray-100 rounded-xl px-3 py-2 focus-within:ring-2 focus-within:ring-indigo-500 focus-within:bg-white transition-all">
            <textarea id="chat-input" rows="1" placeholder="Type a message or instruction..." class="w-full bg-transparent outline-none resize-none text-[13px] text-gray-800 pt-1 border-0 ring-0 h-auto min-h-[24px] max-h-[100px] overflow-y-auto block"></textarea>
        </div>
        <button id="send-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white w-[38px] h-[38px] rounded-xl flex items-center justify-center transition-colors shadow-sm disabled:opacity-50 disabled:cursor-not-allowed shrink-0">
            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20"><path d="M10.894 2.553a1 1 0 00-1.788 0l-7 14a1 1 0 001.169 1.409l5-1.429A1 1 0 009 15.571V11a1 1 0 112 0v4.571a1 1 0 00.725.962l5 1.428a1 1 0 001.17-1.408l-7-14z"></path></svg>
        </button>
    </div>
</div>

<script src="assets/js/ai-chatbot.js" defer></script>
<?php endif; ?>

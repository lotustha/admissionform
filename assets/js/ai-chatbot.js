// assets/js/ai-chatbot.js

document.addEventListener('DOMContentLoaded', () => {
    // -----------------------------------------
    // Elements & State Initialization
    // -----------------------------------------
    const toggleBtn = document.getElementById('chatbot-toggle');
    const closeBtn = document.getElementById('chatbot-close');
    const chatbotWindow = document.getElementById('chatbot-window');
    const botIconClosed = document.getElementById('bot-icon-closed');
    const botIconOpen = document.getElementById('bot-icon-open');
    const messagesContainer = document.getElementById('chat-messages');
    const chatInput = document.getElementById('chat-input');
    const sendBtn = document.getElementById('send-btn');
    const typingIndicator = document.getElementById('typing-indicator');
    const handoffPanel = document.getElementById('handoff-panel');
    const btnHuman = document.getElementById('btn-human');

    let isChatOpen = false;
    let lastMessageId = 0;
    let pollInterval = null;
    let chatStatus = 'bot'; // 'bot', 'human_requested', 'human_active'

    // Retrieve or create Session Token
    let sessionToken = localStorage.getItem('chat_session_token');
    if (!sessionToken) {
        sessionToken = crypto.randomUUID ? crypto.randomUUID() : 'session_' + Math.random().toString(36).substr(2, 9) + Date.now();
        localStorage.setItem('chat_session_token', sessionToken);
    }

    // -----------------------------------------
    // UI Toggles
    // -----------------------------------------
    function toggleChat() {
        isChatOpen = !isChatOpen;
        if (isChatOpen) {
            chatbotWindow.classList.remove('hidden');
            // small delay for transition
            setTimeout(() => {
                chatbotWindow.classList.remove('scale-95', 'opacity-0');
                chatbotWindow.classList.add('scale-100', 'opacity-100');
            }, 10);
            botIconClosed.classList.add('hidden');
            botIconOpen.classList.remove('hidden');
            chatInput.focus();
            scrollToBottom();
            
            // If we haven't polled yet, fetch history
            if(lastMessageId === 0) {
                pollMessages();
            }
            startPolling();
        } else {
            chatbotWindow.classList.remove('scale-100', 'opacity-100');
            chatbotWindow.classList.add('scale-95', 'opacity-0');
            setTimeout(() => {
                chatbotWindow.classList.add('hidden');
            }, 300);
            botIconClosed.classList.remove('hidden');
            botIconOpen.classList.add('hidden');
            stopPolling();
        }
    }

    toggleBtn.addEventListener('click', toggleChat);
    closeBtn.addEventListener('click', toggleChat);

    // Auto-open on Success page (detect via URL)
    if (window.location.href.includes('admit_card.php') && window.location.href.includes('id=')) {
        setTimeout(toggleChat, 1000);
    }

    // -----------------------------------------
    // Form Context Scraping
    // -----------------------------------------
    function getFormContext() {
        const context = {};
        const form = document.getElementById('admissionForm');
        if (form) {
            const formData = new FormData(form);
            for (let [key, value] of formData.entries()) {
                if (value && typeof value === 'string' && value.length > 0) {
                    context[key] = value;
                }
            }
        }
        return context;
    }

    // -----------------------------------------
    // Rendering Messages
    // -----------------------------------------
    function scrollToBottom() {
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    }

    function appendMessage(text, sender, created_at = null) {
        const timeStr = created_at ? new Date(created_at).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const msgDiv = document.createElement('div');
        msgDiv.className = 'flex ' + (sender === 'user' ? 'justify-end' : 'justify-start');

        let innerHTML = '';
        if (sender === 'user') {
            innerHTML = `
                <div class="max-w-[85%] bg-indigo-600 text-white p-3 rounded-2xl rounded-tr-sm shadow-sm text-sm">
                    <div class="break-words">${escapeHTML(text)}</div>
                    <div class="text-[9px] text-indigo-200 mt-1 text-right">${timeStr}</div>
                </div>
            `;
        } else if (sender === 'admin') {
            innerHTML = `
                <div class="max-w-[85%] bg-emerald-50 border border-emerald-100 p-3 rounded-2xl rounded-tl-sm shadow-sm text-sm text-emerald-900 overflow-hidden">
                    <div class="text-[10px] font-bold text-emerald-600 mb-1 uppercase tracking-wider flex items-center">
                        <svg class="w-3 h-3 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        School Official
                    </div>
                    <div class="markdown-wrapper break-words text-[13px]">${marked.parse(text)}</div>
                    <div class="text-[9px] text-emerald-400 mt-1">${timeStr}</div>
                </div>
            `;
        } else {
            innerHTML = `
                <div class="max-w-[85%] bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-sm shadow-sm text-sm text-gray-800 overflow-hidden">
                    <div class="markdown-wrapper break-words text-[13px]">${marked.parse(text)}</div>
                    <div class="text-[9px] text-gray-400 mt-1">${timeStr}</div>
                </div>
            `;
        }

        msgDiv.innerHTML = innerHTML;
        messagesContainer.appendChild(msgDiv);
        scrollToBottom();
    }

    function escapeHTML(str) {
        return str.replace(/[&<>'"]/g, 
            tag => ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#39;',
                '"': '&quot;'
            }[tag] || tag)
        );
    }

    function showTyping() {
        typingIndicator.classList.remove('hidden');
        scrollToBottom();
    }

    function hideTyping() {
        typingIndicator.classList.add('hidden');
    }

    // -----------------------------------------
    // Handling UI Actions (Form Fill)
    // -----------------------------------------
    function processActions(actions) {
        if (!actions || !Array.isArray(actions)) return;
        actions.forEach(action => {
            const input = document.getElementById(action.target);
            if (input) {
                input.value = action.value;
                input.dispatchEvent(new Event('change', { bubbles: true }));
                // Highlight input briefly so user sees the magic
                input.classList.add('ring-2', 'ring-indigo-500', 'bg-indigo-50', 'transition-all');
                setTimeout(() => {
                    input.classList.remove('ring-2', 'ring-indigo-500', 'bg-indigo-50');
                }, 2000);
            } else {
                // Try to find inputs by name (e.g. for radio buttons like form_type_select)
                const radios = document.querySelectorAll(`input[name="${action.target}"]`);
                if(radios.length > 0) {
                    radios.forEach(r => {
                        if(r.value === action.value) {
                            r.checked = true;
                            r.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    });
                }
            }
        });
    }

    // -----------------------------------------
    // API Interaction
    // -----------------------------------------
    async function sendMessage(text, isHandoffRequest = false) {
        text = text.trim();
        if (!text) return;

        chatInput.value = '';
        chatInput.style.height = 'auto'; // reset textarea
        
        if(!isHandoffRequest) {
            appendMessage(text, 'user');
        }
        
        showTyping();
        sendBtn.disabled = true;

        try {
            const payload = {
                session_token: sessionToken,
                message: isHandoffRequest ? '[ACTIVATE_HUMAN_HANDOFF]' : text,
                form_context: getFormContext()
            };

            const response = await fetch('ai_chat_endpoint.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            hideTyping();
            
            if (data.success) {
                // If it wasn't a handoff request, process the bot's reply directly
                // (Though polling will catch it too, it's faster to display immediately 
                // but we should avoid duplicating. To avoid duplicating, we rely on chat_sync to get messages 
                // OR we render it here and advance lastMessageId when sync happens).
                // Actually, the simplest approach when polling is active is to let polling fetch the newest messages!
                // But polling is every 5 seconds. To be snappy, we can instantly fetch history right after sending.
                await pollMessages();
                
                if (data.actions) {
                    processActions(data.actions);
                }
                
                if (data.handoff) {
                    chatStatus = 'human_requested';
                    updateStatusUI();
                }
            } else {
                appendMessage("Failed to send: " + data.message, 'bot');
            }
        } catch (error) {
            hideTyping();
            console.error('Chat Error:', error);
            appendMessage("Sorry, network error.", 'bot');
        }
        
        sendBtn.disabled = false;
        chatInput.focus();
    }

    function updateStatusUI() {
        if (chatStatus === 'bot') {
            handoffPanel.classList.remove('hidden');
            document.getElementById('chat-sub-text').innerText = "Auto-filling form & answering queries";
            document.getElementById('chat-sub-text').className = "text-xs text-indigo-100 mt-1 opacity-90 font-medium";
        } else if (chatStatus === 'human_requested') {
            handoffPanel.classList.add('hidden');
            document.getElementById('chat-sub-text').innerText = "Waiting for an official...";
            document.getElementById('chat-sub-text').className = "text-xs text-yellow-300 mt-1 opacity-100 font-bold animate-pulse";
        } else if (chatStatus === 'human_active') {
            handoffPanel.classList.add('hidden');
            document.getElementById('chat-sub-text').innerText = "Chatting with Official";
            document.getElementById('chat-sub-text').className = "text-xs text-emerald-300 mt-1 opacity-100 font-bold";
        }
    }

    // -----------------------------------------
    // Polling Mechanism
    // -----------------------------------------
    async function pollMessages() {
        try {
            const res = await fetch(`chat_sync_endpoint.php?session_token=${sessionToken}&last_message_id=${lastMessageId}`);
            const data = await res.json();
            
            if(data.success) {
                // Update status
                if(chatStatus !== data.status) {
                    chatStatus = data.status;
                    updateStatusUI();
                }
                
                // Append new messages
                if (data.messages && data.messages.length > 0) {
                    data.messages.forEach(msg => {
                        appendMessage(msg.message, msg.sender_type, msg.created_at);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                    });
                }
            }
        } catch (e) {
            console.error('Polling error:', e);
        }
    }

    function startPolling() {
        if (!pollInterval) {
            pollInterval = setInterval(pollMessages, 5000); // 5 seconds
        }
    }

    function stopPolling() {
        if (pollInterval) {
            clearInterval(pollInterval);
            pollInterval = null;
        }
    }

    // -----------------------------------------
    // Event Listeners
    // -----------------------------------------
    sendBtn.addEventListener('click', () => sendMessage(chatInput.value));
    
    chatInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            sendMessage(chatInput.value);
        }
    });

    btnHuman.addEventListener('click', () => {
        sendMessage("", true); // trigger human handoff
    });

    // Initial State Check
    pollMessages();
});

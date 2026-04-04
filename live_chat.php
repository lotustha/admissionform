<?php
// live_chat.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Handle closing a session
if (isset($_POST['close_session'])) {
    $session_id = $_POST['session_id'];
    $stmt = $pdo->prepare("UPDATE chat_sessions SET status = 'resolved' WHERE id = ?");
    $stmt->execute([$session_id]);
    header("Location: live_chat.php");
    exit;
}

// Fetch active sessions requiring human intervention
$stmt = $pdo->query("
    SELECT s.*, i.student_first_name, i.student_last_name, i.entrance_roll_no 
    FROM chat_sessions s
    LEFT JOIN admission_inquiries i ON s.inquiry_id = i.id
    WHERE s.status IN ('human_requested', 'human_active')
    ORDER BY s.last_activity DESC
");
$active_sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

// If a specific session is selected
$selected_session_id = $_GET['session'] ?? null;
$messages = [];
$current_session = null;

if ($selected_session_id) {
    // Claim the session if it was just requested
    $stmt = $pdo->prepare("UPDATE chat_sessions SET status = 'human_active', assigned_to = ? WHERE id = ? AND status = 'human_requested'");
    $stmt->execute([$admin_id, $selected_session_id]);

    // Fetch session details
    $stmt = $pdo->prepare("
        SELECT s.*, i.student_first_name, i.student_last_name 
        FROM chat_sessions s
        LEFT JOIN admission_inquiries i ON s.inquiry_id = i.id
        WHERE s.id = ?
    ");
    $stmt->execute([$selected_session_id]);
    $current_session = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch messages
    $stmt = $pdo->prepare("SELECT * FROM chat_messages WHERE session_id = ? ORDER BY created_at ASC");
    $stmt->execute([$selected_session_id]);
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Handle sending a reply
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_reply'])) {
    $reply_text = trim($_POST['reply_text']);
    $session_id = $_POST['session_id'];

    if (!empty($reply_text)) {
        $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'admin', ?)");
        $stmt->execute([$session_id, $reply_text]);
        
        // Update session last_activity
        $pdo->prepare("UPDATE chat_sessions SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$session_id]);
        
        header("Location: live_chat.php?session=" . $session_id);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Support Inbox - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>
    <style>
        body { font-family: 'Inter', sans-serif; }
        .markdown-wrapper p { margin-bottom: 0.5rem; }
        .markdown-wrapper ul { list-style-type: disc; margin-left: 1.5rem; margin-bottom: 0.5rem; }
    </style>
</head>
<body class="bg-gray-50 font-sans border-box">
    <?php include 'includes/admin_sidebar.php'; ?>
        <div class="flex bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden" style="height: calc(100vh - 6rem);">
            
            <!-- Sidebar: Session List -->
            <div class="w-1/3 border-r border-gray-200 flex flex-col bg-gray-50/50">
                <div class="p-4 border-b border-gray-200 bg-white">
                    <h2 class="font-bold text-gray-800 flex items-center">
                        <span class="w-2 h-2 rounded-full bg-red-500 mr-2 animate-pulse"></span>
                        Active Requests
                    </h2>
                </div>
                
                <div class="flex-1 overflow-y-auto" id="session_list">
                    <?php if (empty($active_sessions)): ?>
                        <div class="p-6 text-center text-gray-400 text-sm">
                            <svg class="w-12 h-12 mx-auto mb-3 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                            No users are currently requesting human assistance.
                        </div>
                    <?php else: ?>
                        <ul class="divide-y divide-gray-100">
                            <?php foreach ($active_sessions as $sess): ?>
                                <?php $isActive = $selected_session_id == $sess['id']; ?>
                                <li>
                                    <a href="?session=<?php echo $sess['id']; ?>" class="block p-4 hover:bg-gray-100 transition-colors <?php echo $isActive ? 'bg-emerald-50 border-l-4 border-emerald-500' : 'border-l-4 border-transparent'; ?>">
                                        <div class="flex justify-between items-start mb-1">
                                            <span class="font-semibold text-gray-800 text-sm truncate">
                                                <?php echo $sess['student_first_name'] ? htmlspecialchars($sess['student_first_name'] . ' ' . $sess['student_last_name']) : 'Anonymous User'; ?>
                                                <?php if($sess['entrance_roll_no']): ?>
                                                    <span class="text-[10px] bg-gray-200 text-gray-700 px-1.5 py-0.5 rounded ml-1"><?php echo $sess['entrance_roll_no']; ?></span>
                                                <?php endif; ?>
                                            </span>
                                            <span class="text-[10px] text-gray-400 whitespace-nowrap"><?php echo date('h:i A', strtotime($sess['last_activity'])); ?></span>
                                        </div>
                                        <div class="text-xs text-gray-500">
                                            <?php if($sess['status'] === 'human_requested'): ?>
                                                <span class="text-red-600 font-semibold text-[10px] uppercase tracking-wider bg-red-100 px-1.5 py-0.5 rounded">Waiting</span>
                                            <?php else: ?>
                                                <span class="text-blue-600 font-semibold text-[10px] uppercase tracking-wider bg-blue-100 px-1.5 py-0.5 rounded">In Progress</span>
                                            <?php endif; ?>
                                        </div>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="w-2/3 flex flex-col bg-white">
                <?php if ($current_session): ?>
                    <!-- Chat Header -->
                    <div class="p-4 border-b border-gray-200 bg-white flex justify-between items-center z-10 shadow-sm">
                        <div>
                            <h2 class="font-bold text-gray-800">
                                <?php echo $current_session['student_first_name'] ? htmlspecialchars($current_session['student_first_name'] . ' ' . $current_session['student_last_name']) : 'Anonymous Session'; ?>
                            </h2>
                            <p class="text-xs text-gray-500">Session ID: <?php echo substr($current_session['session_token'], 0, 8); ?>...</p>
                        </div>
                        <form method="POST" action="">
                            <input type="hidden" name="session_id" value="<?php echo $current_session['id']; ?>">
                            <button type="submit" name="close_session" onclick="return confirm('End this conversation and close the ticket?');" class="text-xs font-semibold bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded transition border border-gray-300">Mark Resolved</button>
                        </form>
                    </div>

                    <!-- Chat History -->
                    <div class="flex-1 p-4 overflow-y-auto bg-gray-50 bg-[url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSI0IiBoZWlnaHQ9IjQiPjxyZWN0IHdpZHRoPSI0IiBoZWlnaHQ9IjQiIGZpbGw9IiNmZmYiLz48cmVjdCB3aWR0aD0iMSIgaGVpZ2h0PSIxIiBmaWxsPSIjZTVlN2ViIi8+PC9zdmc+')] relative" id="chat_history">
                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['sender_type'] === 'user'): ?>
                                <!-- User Message -->
                                <div class="flex justify-start mb-4">
                                    <div class="max-w-[75%] bg-white border border-gray-200 p-3 rounded-2xl rounded-tl-sm shadow-sm text-sm text-gray-800">
                                        <div class="markdown-wrapper break-words"><?php echo htmlspecialchars($msg['message']); ?></div>
                                        <div class="text-[9px] text-gray-400 mt-1 text-right"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php elseif ($msg['sender_type'] === 'bot'): ?>
                                <!-- Bot Message -->
                                <div class="flex justify-start mb-4">
                                    <div class="max-w-[75%] bg-indigo-50 border border-indigo-100 p-3 rounded-2xl rounded-tl-sm shadow-sm text-sm text-indigo-900">
                                        <div class="text-[10px] font-bold text-indigo-400 mb-1 uppercase tracking-wider">AI Assistant</div>
                                        <div class="markdown-wrapper break-words message-content" data-markdown="<?php echo htmlspecialchars($msg['message']); ?>"></div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <!-- Admin Message -->
                                <div class="flex justify-end mb-4">
                                    <div class="max-w-[75%] bg-emerald-600 text-white p-3 rounded-2xl rounded-tr-sm shadow-sm text-sm">
                                        <div class="text-[10px] font-bold text-emerald-200 mb-1 uppercase tracking-wider text-right">You</div>
                                        <div class="markdown-wrapper break-words"><?php echo htmlspecialchars($msg['message']); ?></div>
                                        <div class="text-[9px] text-emerald-200 mt-1 text-right"><?php echo date('h:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div id="scroll_anchor"></div>
                    </div>

                    <!-- Chat Input -->
                    <div class="p-3 bg-white border-t border-gray-200">
                        <form method="POST" action="" class="flex items-end gap-2">
                            <input type="hidden" name="session_id" value="<?php echo $current_session['id']; ?>">
                            <div class="flex-1 bg-gray-100 rounded-xl px-3 py-2 border border-gray-200 focus-within:border-emerald-500 focus-within:ring-1 focus-within:ring-emerald-500 transition-all">
                                <textarea name="reply_text" rows="2" required placeholder="Type your reply to the student..." class="w-full bg-transparent outline-none resize-none text-sm text-gray-800 font-medium"></textarea>
                            </div>
                            <button type="submit" name="send_reply" class="bg-emerald-600 hover:bg-emerald-700 text-white p-3 rounded-xl transition-colors shadow-sm text-sm font-semibold flex items-center justify-center flex-shrink-0">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path></svg>
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <div class="flex-1 flex flex-col items-center justify-center text-gray-400 bg-gray-50/50">
                        <svg class="w-16 h-16 mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8h2a2 2 0 012 2v6a2 2 0 01-2 2h-2v4l-4-4H9a1.994 1.994 0 01-1.414-.586m0 0L11 14h4a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2v4l.586-.586z"></path></svg>
                        <p class="text-lg font-medium text-gray-500">Select a conversation</p>
                        <p class="text-sm">Choose an active request from the sidebar to chat.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    <!-- Polling Logic & Markdown Rendering -->
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Render markdown bot messages
            document.querySelectorAll('.message-content').forEach(el => {
                const raw = el.getAttribute('data-markdown');
                if(raw) {
                    el.innerHTML = marked.parse(raw);
                }
            });

            // Scroll to bottom
            const anchor = document.getElementById('scroll_anchor');
            if(anchor) {
                anchor.scrollIntoView();
            }

            // Simple Auto-refresh feature for testing
            // When an admin is waiting, refresh the page every 15 seconds
            const sessionParam = new URLSearchParams(window.location.search).get('session');
            let refreshRate = sessionParam ? 15000 : 30000;
            
            // Only auto-refresh if there's no typing
            let isTyping = false;
            const textarea = document.querySelector('textarea[name="reply_text"]');
            if(textarea) {
                textarea.addEventListener('input', () => {
                    isTyping = textarea.value.length > 0;
                });
            }

            setInterval(() => {
                if(!isTyping) {
                    window.location.reload();
                }
            }, refreshRate);
        });
    </script>
        </div>
    </main>
</div>
</body>
</html>

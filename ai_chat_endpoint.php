<?php
// ai_chat_endpoint.php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input || empty($input['session_token']) || empty($input['message'])) {
    echo json_encode(['success' => false, 'message' => 'Missing session or message data.']);
    exit;
}

$session_token = $input['session_token'];
$user_message = trim($input['message']);
$form_context = $input['form_context'] ?? []; // Associative array of currents input values

// 1. Get or Create Session
$stmt = $pdo->prepare("SELECT * FROM chat_sessions WHERE session_token = ?");
$stmt->execute([$session_token]);
$session = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$session) {
    // Create new session
    $stmt = $pdo->prepare("INSERT INTO chat_sessions (session_token, status) VALUES (?, 'bot')");
    $stmt->execute([$session_token]);
    $session_id = $pdo->lastInsertId();
    $session = ['id' => $session_id, 'status' => 'bot'];
} else {
    $session_id = $session['id'];
}

// Update last activity
$pdo->prepare("UPDATE chat_sessions SET last_activity = CURRENT_TIMESTAMP WHERE id = ?")->execute([$session_id]);

// 2. Insert User Message
$stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'user', ?)");
$stmt->execute([$session_id, $user_message]);

// 3. Check if human requested / active
if ($session['status'] === 'human_requested' || $session['status'] === 'human_active') {
    // Return early, don't ping AI
    echo json_encode(['success' => true, 'is_human' => true]);
    exit;
}

// If user explicitly asks for a human inside the UI: 
// (Checking simple trigger before AI, or letting AI decide. Let's let the UI send a specific trigger, or AI output it).
if ($user_message === '[ACTIVATE_HUMAN_HANDOFF]') {
    $pdo->prepare("UPDATE chat_sessions SET status = 'human_requested' WHERE id = ?")->execute([$session_id]);
    $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'bot', ?)");
    $msg = "I have requested a human official to join this chat. Someone will be with you shortly.";
    $stmt->execute([$session_id, $msg]);
    echo json_encode(['success' => true, 'reply' => $msg, 'handoff' => true]);
    exit;
}

// 4. Ping Gemini AI
$apiKey = getRandomGeminiKey($pdo);
if (!$apiKey) {
    // Fallback if no keys
    $fallback = "Sorry, our AI is currently offline due to missing API keys. You can click 'Talk to Human' to reach an official.";
    $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'bot', ?)");
    $stmt->execute([$session_id, $fallback]);
    echo json_encode(['success' => true, 'reply' => $fallback]);
    exit;
}

// Gather Knowledge Base
$stmt = $pdo->query("SELECT category, content FROM knowledge_base");
$knowledgeItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
$knowledge_text = "";
foreach($knowledgeItems as $item) {
    $knowledge_text .= "### " . $item['category'] . "\n" . $item['content'] . "\n\n";
}

// Gather Class Incharge Info for Handoff context
$incharge_text = "";
try {
    $inc_stmt = $pdo->query("SELECT faculty_name, incharge_name, incharge_title, incharge_whatsapp FROM faculties WHERE incharge_name IS NOT NULL AND incharge_name != '' ORDER BY faculty_name ASC");
    $incharges = $inc_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach($incharges as $inc) {
        $incharge_text .= "- **" . $inc['faculty_name'] . "**: {$inc['incharge_name']}";
        if ($inc['incharge_title']) $incharge_text .= " ({$inc['incharge_title']})";
        if ($inc['incharge_whatsapp']) $incharge_text .= " — WhatsApp: " . $inc['incharge_whatsapp'];
        $incharge_text .= "\n";
    }
} catch (Exception $e) { $incharge_text = "(Not configured)"; }

// Gather Conversation History (last 8 messages)
$stmt = $pdo->prepare("
    SELECT sender_type, message 
    FROM chat_messages 
    WHERE session_id = ? 
    ORDER BY created_at DESC 
    LIMIT 8
");
$stmt->execute([$session_id]);
$history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

$contents = [];
foreach ($history as $h) {
    if ($h['sender_type'] === 'user') {
        $contents[] = ["role" => "user", "parts" => [["text" => $h['message']]]];
    } else if ($h['sender_type'] === 'bot') {
        $contents[] = ["role" => "model", "parts" => [["text" => $h['message']]]];
    }
}

// Count how many form fields are actually filled
$filled_fields = 0;
$important_fields = ['student_first_name', 'student_last_name', 'applied_class', 'father_name', 'father_contact'];
if (!empty($form_context)) {
    foreach($important_fields as $f) {
        if (!empty($form_context[$f])) $filled_fields++;
    }
}
$form_is_empty = $filled_fields === 0;

// System Instructions
$system_instruction = "
You are the official AI Admission Assistant for this school portal.
Your primary job is to COLLECT student info to fill the admission or inquiry form, THEN answer questions.

**CORE BEHAVIOR — FORM-FIRST RULE:**
The student must fill the form before you answer general questions.
- If the form is completely empty (no name, class, or contact entered), you MUST begin by warmly greeting the student and asking if they want to submit a 'Quick Inquiry' or a 'Full Admission' form. Then ask for Step 1 basics: their **full name** and the **class they want to apply for**.
- Guide the user systematically. Do NOT overwhelm them by asking for all information at once. Provide guidance on what is required next based on the form structure below.

**FORM STRUCTURE AND REQUIRED FIELDS:**
The UI has distinct steps. Ask for fields sequentially:
- **Step 1 (Student Basics):** First Name (target: 'student_first_name'), Last Name ('student_last_name'), Email ('student_email'), DOB ('dob_bs', format YYYY-MM-DD), Gender (target: 'gender', values: 'Male'/'Female'/'Other'), Applied Class (target: 'applied_class', MUST be exactly formatted like 'Class 9', 'Class 10', 'Class 11' etc., no bare numbers).
- **Step 2 (Parents):** Father's Name ('father_name'), Primary Contact No ('father_contact').
- **Step 3 (Address & Academics):** Province ('address_province'), District ('address_district'), Municipality ('address_municipality'), Ward ('address_ward_village'). Previous School and GPA are also here (required for Admission, optional for Inquiry).

**FORM TYPES:**
- If the user just wants to inquire, submit the action {\"target\": \"form_type_select\", \"value\": \"Inquiry\"}.
- If they want full admission, submit {\"target\": \"form_type_select\", \"value\": \"Admission\"}.

**CRITICAL RULES:**
1. Guide the user step by step! Collect Step 1, then Step 2, then Step 3. Fill fields using JSON form actions as soon as the user states them.
2. Base knowledge ONLY on the provided Knowledge Base. Do NOT invent fees or policies.
3. If user asks about their admit card or application status, tell them to click the 'Check Status' button at the top.
4. If asked to speak with a human or you cannot answer a question, provide the relevant class incharge's WhatsApp number.
5. If user EXPLICITLY demands immediate human transfer, ONLY output: `[TRANSFER_TO_HUMAN]`

**CLASS INCHARGE CONTACTS (for human handoff):**
" . ($incharge_text ?: "Not configured yet — ask user to contact the school directly.") . "

**FORM FILLING ABILITY:**
Current form state (" . ($form_is_empty ? 'EMPTY — must collect data first' : 'has some data') . "): " . json_encode($form_context) . "
When user shares personal details, output a JSON block to fill those fields dynamically in the UI:
```json
{
  \"reply\": \"I've filled in your name. Next, what is your Date of Birth in YYYY-MM-DD format?\",
  \"actions\": [
    {\"target\": \"form_type_select\", \"value\": \"Inquiry\"},
    {\"target\": \"student_first_name\", \"value\": \"John\"},
    {\"target\": \"student_last_name\", \"value\": \"Doe\"}
  ]
}
```
If not filling a form, just respond in standard Markdown (no JSON needed).

**KNOWLEDGE BASE:**
" . $knowledge_text . "
";

$postData = [
    "systemInstruction" => [
        "parts" => [
            ["text" => $system_instruction]
        ]
    ],
    "contents" => $contents,
    "generationConfig" => [
        "temperature" => 0.5,
    ]
];

$ch = curl_init("https://generativelanguage.googleapis.com/v1beta/models/gemini-3-flash-preview:generateContent?key=" . $apiKey);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Fix for XAMPP missing CA bundle
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$curl_err = curl_error($ch);
curl_close($ch);

$responseData = json_decode($response, true);
$bot_reply = "I'm sorry, our AI assistant is temporarily unavailable. Please try again in a moment or click 'Talk to Human' for direct assistance.";

if ($curl_err) {
    // Log curl errors for debugging
    error_log("AI Chat cURL Error: " . $curl_err);
}

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $bot_reply = $responseData['candidates'][0]['content']['parts'][0]['text'];
} elseif (isset($responseData['error']['message'])) {
    error_log("Gemini API Error: " . $responseData['error']['message']);
}

// Handle Human Handoff logic from AI
if (strpos($bot_reply, '[TRANSFER_TO_HUMAN]') !== false) {
    $pdo->prepare("UPDATE chat_sessions SET status = 'human_requested' WHERE id = ?")->execute([$session_id]);
    $msg = "I've sent a request to our officials. An admin will take over this chat shortly.";
    $stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'bot', ?)");
    $stmt->execute([$session_id, $msg]);
    echo json_encode(['success' => true, 'reply' => $msg, 'handoff' => true]);
    exit;
}

// Save Bot Message
$stmt = $pdo->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'bot', ?)");
$stmt->execute([$session_id, $bot_reply]);

// Detect JSON action blocks in the bot_reply to parse them correctly for the frontend
$actions = [];
$clean_reply = $bot_reply;

if (preg_match('/```json\s*(\{.*?\})\s*```/s', $bot_reply, $matches)) {
    $parsed = json_decode($matches[1], true);
    if (isset($parsed['reply'])) {
        $clean_reply = $parsed['reply'];
        
        // Update the saved message to be the clean message so history isn't littered with JSON blocks
        $stmt = $pdo->prepare("UPDATE chat_messages SET message = ? WHERE id = ?");
        $stmt->execute([$clean_reply, $pdo->lastInsertId()]);
    }
    if (isset($parsed['actions']) && is_array($parsed['actions'])) {
        $actions = $parsed['actions'];
    }
}

echo json_encode([
    'success' => true,
    'reply' => $clean_reply,
    'actions' => $actions
]);

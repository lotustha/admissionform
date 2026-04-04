<?php
// ocr_endpoint.php
require_once __DIR__ . '/includes/connect.php';
require_once __DIR__ . '/includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$apiKey = getRandomGeminiKey($pdo);
if (!$apiKey) {
    echo json_encode(['success' => false, 'message' => 'Gemini API keys not configured.']);
    exit;
}

$parts = [];
$parts[] = ["text" => "Extract the student's full name, date of birth (convert to YYYY-MM-DD if possible), gender (Male/Female/Other), father's name, mother's name, permanent address (Province, District, Municipality, Ward/Village), previous school name (the name of the school appearing on the marksheet), SEE symbol no, current class/grade level of the student based on the marksheet (e.g., return the number like 7, 8, 9, 10), and GPA or percentage from these documents. Return ONLY a valid JSON object with exactly these keys: student_first_name, student_last_name, dob_ad, dob_bs, gender, father_name, mother_name, address_province, address_district, address_municipality, address_ward_village, previous_school_name, see_symbol_no, current_class, gpa. If any value is missing or unreadable, return null for it. Do not include markdown formatting like ```json."];

$hasFiles = false;
foreach (['marksheet_doc', 'birth_cert'] as $key) {
    if (isset($_FILES[$key]) && $_FILES[$key]['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES[$key]['tmp_name'];
        $mimeType = mime_content_type($fileTmpPath);
        if (strpos($mimeType, 'image/') === 0 || $mimeType === 'application/pdf') {
            $fileData = base64_encode(file_get_contents($fileTmpPath));
            $parts[] = [
                "inline_data" => [
                    "mime_type" => $mimeType,
                    "data" => $fileData
                ]
            ];
            $hasFiles = true;
        }
    }
}

if (!$hasFiles) {
    echo json_encode(['success' => false, 'message' => 'No valid documents uploaded for OCR.']);
    exit;
}

$apiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

$requestData = [
    "contents" => [
        [
            "parts" => $parts
        ]
    ]
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error || $httpCode !== 200) {
    echo json_encode(['success' => false, 'message' => 'Failed to reach Gemini API. Detailed error: ' . $error, 'details' => $response]);
    exit;
}

$resData = json_decode($response, true);
$textValue = $resData['candidates'][0]['content']['parts'][0]['text'] ?? '';

// Clean up markdown if Gemini returned it despite instructions
$textValue = trim($textValue);
if (strpos($textValue, '```json') === 0) {
    $textValue = substr($textValue, 7);
    $textValue = substr($textValue, 0, -3);
} elseif (strpos($textValue, '```') === 0) {
    $textValue = substr($textValue, 3);
    $textValue = substr($textValue, 0, -3);
}
$textValue = trim($textValue);

$extracted = json_decode($textValue, true);
if (json_last_error() === JSON_ERROR_NONE && is_array($extracted)) {
    echo json_encode(['success' => true, 'extracted' => $extracted]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to parse AI response.']);
}
?>
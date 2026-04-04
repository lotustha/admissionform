<?php
// manage_knowledge.php
session_start();
require_once __DIR__ . '/includes/connect.php';

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit;
}

$msg = '';

// Handle Create / Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_knowledge'])) {
    $knowledge_id = $_POST['knowledge_id'] ?? '';
    $category = trim($_POST['category']);
    $content = trim($_POST['content']);

    if (empty($category) || empty($content)) {
        $msg = "Category and Content are required.";
    } else {
        if ($knowledge_id) {
            // Update
            $stmt = $pdo->prepare("UPDATE knowledge_base SET category = ?, content = ? WHERE id = ?");
            $stmt->execute([$category, $content, $knowledge_id]);
            $msg = "Knowledge Base updated successfully.";
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO knowledge_base (category, content) VALUES (?, ?)");
            $stmt->execute([$category, $content]);
            $msg = "New knowledge added successfully.";
        }
        header("Location: manage_knowledge.php?msg=" . urlencode($msg));
        exit;
    }
}

// Handle Delete
if (isset($_GET['delete'])) {
    $knowledge_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM knowledge_base WHERE id = ?");
    $stmt->execute([$knowledge_id]);
    header("Location: manage_knowledge.php?msg=" . urlencode("Knowledge deleted."));
    exit;
}

// Fetch display message
if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// Fetch all knowledge base items
$stmt = $pdo->query("SELECT * FROM knowledge_base ORDER BY category ASC");
$knowledge_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_item = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM knowledge_base WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $edit_item = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage AI Knowledge Base - Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 font-sans">
    <?php include 'includes/admin_sidebar.php'; ?>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-bold text-gray-800">AI Knowledge Base</h1>
        </div>

        <?php if ($msg): ?>
            <div class="mb-6 p-4 bg-emerald-50 border-l-4 border-emerald-500 text-emerald-700 rounded shadow-sm">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Form Area -->
            <div class="lg:col-span-1">
                <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-200 sticky top-6">
                    <h2 class="text-lg font-bold text-gray-700 border-b pb-3 mb-4">
                        <?php echo $edit_item ? 'Edit Knowledge' : 'Add New Knowledge'; ?>
                    </h2>
                    
                    <form method="POST" action="manage_knowledge.php">
                        <input type="hidden" name="knowledge_id" value="<?php echo $edit_item ? $edit_item['id'] : ''; ?>">
                        
                        <div class="mb-4">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Category / Topic</label>
                            <input type="text" name="category" required 
                                value="<?php echo $edit_item ? htmlspecialchars($edit_item['category']) : ''; ?>"
                                placeholder="e.g. Tuition Fees, Admission Rules..."
                                class="w-full border-gray-300 border rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-sm font-semibold text-gray-700 mb-2">Information Content</label>
                            <textarea name="content" rows="6" required 
                                placeholder="Provide the exact rules, dates, prices, or context the AI should know..."
                                class="w-full border-gray-300 border rounded-lg p-2.5 outline-none focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500 font-mono text-sm leading-relaxed text-gray-700"><?php echo $edit_item ? htmlspecialchars($edit_item['content']) : ''; ?></textarea>
                            <p class="text-xs text-gray-500 mt-2">The AI chatbot will read this text to accurately answer student queries.</p>
                        </div>
                        
                        <div class="flex gap-3">
                            <button type="submit" name="save_knowledge" class="flex-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-2.5 px-4 rounded-lg transition-colors">
                                <?php echo $edit_item ? 'Update' : 'Save Info'; ?>
                            </button>
                            <?php if ($edit_item): ?>
                                <a href="manage_knowledge.php" class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-bold py-2.5 px-4 rounded-lg transition-colors text-center">Cancel</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- List Area -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider w-1/4">Category</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase tracking-wider">Content Snippet</th>
                                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase tracking-wider w-1/5">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200 text-sm">
                            <?php if(empty($knowledge_items)): ?>
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-gray-500">
                                    No knowledge snippets added yet! Fill out the form to train your AI.
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($knowledge_items as $item): ?>
                                <tr class="hover:bg-gray-50 group">
                                    <td class="px-6 py-4 font-semibold text-gray-800 align-top">
                                        <?php echo htmlspecialchars($item['category']); ?>
                                        <div class="text-[10px] text-gray-400 mt-1 font-normal uppercase tracking-wider">Updated: <?php echo date('M d, y', strtotime($item['updated_at'])); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-gray-600 align-top">
                                        <div class="line-clamp-3 leading-relaxed whitespace-pre-wrap font-mono text-xs"><?php echo htmlspecialchars($item['content']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 text-right align-top whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity">
                                        <a href="manage_knowledge.php?edit=<?php echo $item['id']; ?>" class="text-indigo-600 hover:text-indigo-900 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded transition-colors text-xs font-semibold mr-2 border border-indigo-200">Edit</a>
                                        <a href="manage_knowledge.php?delete=<?php echo $item['id']; ?>" onclick="return confirm('Delete this knowledge? The AI will forget it.');" class="text-red-600 hover:text-red-900 bg-red-50 hover:bg-red-100 px-3 py-1.5 rounded transition-colors text-xs font-semibold border border-red-200">Delete</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
        </div>
    </div>
        </div>
    </main>
</div>
</body>
</html>

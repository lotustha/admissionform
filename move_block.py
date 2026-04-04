import sys
import gc

file_path = 'c:/xampp/htdocs/admission/student_dashboard.php'
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

marker_top = '        <!-- OVERVIEW TAB -->\n        <?php if ($tab === \'overview\'): ?>\n'
marker_app_overview = '            <h2 class="text-xl font-bold text-gray-900 mb-6 pb-2 border-b border-gray-100">Application Overview</h2>'
marker_exam_start = '            <!-- EXAM RESULT DISPLAY -->\n'
marker_exam_end = '            <?php endif; ?>\n            <?php endif; ?>\n'

idx_top = content.find(marker_top)
idx_app_overview = content.find(marker_app_overview, idx_top)
idx_exam_start = content.find(marker_exam_start, idx_app_overview)

if idx_exam_start == -1:
    print("Could not find exam start marker")
    sys.exit(1)

idx_exam_end = content.find(marker_exam_end, idx_exam_start) + len(marker_exam_end)

exam_result_block = content[idx_exam_start:idx_exam_end]

new_content = content[:idx_exam_start] + content[idx_exam_end:]
idx_app_over_new = new_content.find(marker_app_overview, idx_top)

final_content = new_content[:idx_app_over_new] + exam_result_block + '\n' + new_content[idx_app_over_new:]

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(final_content)

print("Successfully replaced!")

---
version: 1
slug: "resources-js-views-assignmentsview-vue"
primary_target: "resources/js/views/AssignmentsView.vue"
related_targets: ["resources/js/views/AssignmentEditorView.vue","resources/js/views/ChildAssignmentsView.vue","resources/js/views/ChildAssignmentTakeView.vue","resources/js/views/AssignmentGradingView.vue","resources/js/views/NotificationsView.vue"]
---

THESIS: Bàn điều phối học tập giúp Giáo lý viên thấy việc cần xử lý trước, còn Thiếu nhi chỉ thấy việc cần làm tiếp theo.
PRIMARY ACTION: Giáo lý viên tạo/giao bài; Thiếu nhi tiếp tục làm bài.
HIERARCHY: Hàng đợi công việc trước, danh mục bài tập sau; chi tiết và hành động phụ nằm trong panel ngữ cảnh.
COMPOSITION: Danh sách theo hàng, không dùng lưới thẻ đồng cỡ. Desktop dùng master-detail; mobile chuyển thành màn hình tuần tự.
VISUAL WORLD: Be Vietnam Pro, nền trắng/slate, xanh dương cho hành động, amber/đỏ chỉ cho thời hạn và cảnh báo thật.
MOTION: Một chuyển động vào nhẹ cho panel chi tiết; tôn trọng prefers-reduced-motion.
STATES: Loading skeleton, empty có hành động, lỗi có thử lại, disabled giải thích được, focus keyboard rõ.
RESPONSIVE: 360px không cuộn ngang; nút chính luôn trong tầm với; nội dung làm bài tập trung và giới hạn chiều rộng đọc.
ACCESSIBILITY: Nhãn thật, aria-live cho autosave, focus management, màu không phải tín hiệu duy nhất.

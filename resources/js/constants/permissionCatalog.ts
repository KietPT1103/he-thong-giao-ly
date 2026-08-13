export interface PermissionGroup {
    id: string;
    label: string;
    description: string;
    permissions: string[];
}

export const roleLabels: Record<string, string> = {
    admin: "Quản trị viên",
    teacher: "Giáo lý viên",
    parent: "Phụ huynh",
    child: "Thiếu nhi",
};

export const roleDescriptions: Record<string, string> = {
    admin: "Toàn quyền quản trị và cấu hình hệ thống.",
    teacher: "Quản lý lớp được phân công, thiếu nhi và điểm danh.",
    parent: "Theo dõi hồ sơ và hoạt động của các con đã liên kết.",
    child: "Truy cập nội dung dành cho thiếu nhi và tự quét QR điểm danh.",
};

export const permissionLabels: Record<string, string> = {
    "manage-system-settings": "Quản lý cài đặt hệ thống",
    "view-activity-logs": "Xem nhật ký hoạt động",
    "manage-users": "Quản lý tài khoản",
    "manage-roles": "Quản lý vai trò",
    "manage-permissions": "Quản lý phân quyền",
    "view-academic-years": "Xem niên khóa",
    "create-academic-years": "Tạo niên khóa",
    "update-academic-years": "Cập nhật niên khóa",
    "delete-academic-years": "Xóa niên khóa",
    "view-levels": "Xem khối giáo lý",
    "create-levels": "Tạo khối giáo lý",
    "update-levels": "Cập nhật khối giáo lý",
    "delete-levels": "Xóa khối giáo lý",
    "view-classes": "Xem lớp học",
    "create-classes": "Tạo lớp học",
    "update-classes": "Cập nhật lớp học",
    "delete-classes": "Xóa lớp học",
    "assign-teachers": "Phân công giáo lý viên",
    "enroll-children": "Xếp lớp thiếu nhi",
    "view-children": "Xem thiếu nhi",
    "create-children": "Tạo thiếu nhi",
    "update-children": "Cập nhật thiếu nhi",
    "delete-children": "Lưu trữ thiếu nhi",
    "view-parents": "Xem phụ huynh",
    "create-parents": "Tạo phụ huynh",
    "update-parents": "Cập nhật phụ huynh",
    "link-parent-child": "Liên kết phụ huynh và thiếu nhi",
    "view-attendance": "Xem điểm danh",
    "create-attendance-session": "Tạo buổi điểm danh",
    "take-attendance": "Thực hiện điểm danh",
    "update-attendance": "Cập nhật điểm danh",
    "view-attendance-reports": "Xem báo cáo điểm danh",
    "create-attendance-qr": "Tạo QR điểm danh theo buổi học",
    "check-in-attendance-qr": "Tự điểm danh bằng QR",
    "create-leave-request": "Tạo đơn xin phép",
    "view-leave-requests": "Xem đơn xin phép",
    "approve-leave-request": "Duyệt đơn xin phép",
    "reject-leave-request": "Từ chối đơn xin phép",
    "view-notifications": "Xem thông báo",
    "send-notifications": "Gửi thông báo",
    "manage-announcements": "Quản lý thông báo",
};

export const permissionGroups: PermissionGroup[] = [
    {
        id: "system",
        label: "Hệ thống",
        description: "Cấu hình chung và nhật ký quản trị.",
        permissions: ["manage-system-settings", "view-activity-logs"],
    },
    {
        id: "accounts",
        label: "Tài khoản & phân quyền",
        description: "Quản lý người dùng, vai trò và quyền truy cập.",
        permissions: ["manage-users", "manage-roles", "manage-permissions"],
    },
    {
        id: "catechism",
        label: "Niên khóa & khối giáo lý",
        description: "Thiết lập niên khóa và chương trình giáo lý.",
        permissions: ["view-academic-years", "create-academic-years", "update-academic-years", "delete-academic-years", "view-levels", "create-levels", "update-levels", "delete-levels"],
    },
    {
        id: "classes",
        label: "Lớp học",
        description: "Lớp, giáo lý viên phụ trách và danh sách học viên.",
        permissions: ["view-classes", "create-classes", "update-classes", "delete-classes", "assign-teachers", "enroll-children"],
    },
    {
        id: "family",
        label: "Thiếu nhi & phụ huynh",
        description: "Hồ sơ gia đình và liên kết phụ huynh – thiếu nhi.",
        permissions: ["view-children", "create-children", "update-children", "delete-children", "view-parents", "create-parents", "update-parents", "link-parent-child"],
    },
    {
        id: "attendance",
        label: "Điểm danh",
        description: "Phiên điểm danh, quét QR và báo cáo tham dự.",
        permissions: ["view-attendance", "create-attendance-session", "take-attendance", "update-attendance", "view-attendance-reports", "create-attendance-qr", "check-in-attendance-qr"],
    },
    {
        id: "leave",
        label: "Đơn xin phép",
        description: "Tạo, theo dõi và xử lý đơn xin nghỉ.",
        permissions: ["create-leave-request", "view-leave-requests", "approve-leave-request", "reject-leave-request"],
    },
    {
        id: "notifications",
        label: "Thông báo",
        description: "Nhận, gửi và quản lý thông báo trong hệ thống.",
        permissions: ["view-notifications", "send-notifications", "manage-announcements"],
    },
];

export const displayRole = (role: string) => roleLabels[role] ?? role;
export const displayPermission = (permission: string) => permissionLabels[permission] ?? permission;

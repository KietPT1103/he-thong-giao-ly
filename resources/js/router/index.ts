import {
    createRouter,
    createWebHistory,
    type RouteRecordRaw,
} from "vue-router";
import type { Component } from "vue";
import { useAuthStore } from "../stores/authStore";
import ModulePendingView from "../views/ModulePendingView.vue";

declare module "vue-router" {
    interface RouteMeta {
        public?: boolean;
        requiresAuth?: boolean;
        title?: string;
        roles?: string[];
        permission?: string;
        module?: string;
    }
}

const protectedRoute = (
    path: string,
    title: string,
    roles: string[],
    component: Component = ModulePendingView,
    permission?: string,
): RouteRecordRaw => ({
    path,
    component,
    meta: { requiresAuth: true, title, roles, permission },
});
const admin = (path: string, title: string) =>
    protectedRoute(`/admin${path}`, title, ["admin"]);
const parent = (path: string, title: string) =>
    protectedRoute(`/parent${path}`, title, ["parent"]);
const child = (path: string, title: string) =>
    protectedRoute(`/child${path}`, title, ["child"]);

const routes: RouteRecordRaw[] = [
    {
        path: "/",
        component: () => import("../views/LandingPage.vue"),
        meta: { public: true, title: "Hành Trang Đức Tin" },
    },
    {
        path: "/login",
        component: () => import("../views/LoginView.vue"),
        meta: { public: true, title: "Đăng nhập" },
    },
    {
        path: "/register",
        component: () => import("../views/LoginView.vue"),
        meta: { public: true, title: "Đăng ký" },
    },
    {
        path: "/news",
        component: ModulePendingView,
        meta: { public: true, title: "Tin tức" },
    },
    {
        path: "/events",
        component: ModulePendingView,
        meta: { public: true, title: "Sự kiện" },
    },
    {
        path: "/attendance/scan",
        component: () => import("../views/QuickAttendanceView.vue"),
        meta: { public: true, title: "Điểm danh QR" },
    },

    protectedRoute("/admin", "Tổng quan", ["admin"], () => import("../views/AdminDashboardView.vue")),
    protectedRoute("/admin/accounts", "Quản lý tài khoản", ["admin"], () => import("../views/AdminAccountsView.vue"), "manage-users"),
    protectedRoute("/admin/parishes", "Giáo xứ", ["admin"], () => import("../views/AdminParishesView.vue"), "manage-system-settings"),
    protectedRoute("/admin/teachers", "Giáo lý viên", ["admin"], () => import("../views/AdminTeachersView.vue"), "manage-users"),
    protectedRoute("/admin/class-catalogs", "Danh mục lớp học", ["admin"], () => import("../views/AdminClassCatalogView.vue"), "view-academic-years"),
    protectedRoute("/admin/classes", "Lớp học", ["admin"], () => import("../views/AdminClassesView.vue"), "view-classes"),
    protectedRoute("/admin/classes/:id/edit", "Chỉnh sửa lớp học", ["admin"], () => import("../views/AdminClassEditView.vue"), "view-classes"),
    protectedRoute("/admin/parents", "Phụ huynh", ["admin"], () => import("../views/AdminParentsView.vue"), "view-parents"),
    protectedRoute("/admin/children", "Thiếu nhi", ["admin"], () => import("../views/AdminChildrenView.vue"), "view-children"),
    ...[
        ["announcements", "Thông báo"],
    ].map(([module, title]) => ({
        path: `/admin/${module}`,
        component: () => import("../views/AdminDirectoryView.vue"),
        meta: { requiresAuth: true, title, roles: ["admin"], module },
    })),
    ...[
        ["/mass-events", "Thánh lễ và ngày lễ"],
        ["/qr-sessions", "Phiên quét QR"],
        ["/attendance", "Lịch sử tham dự"],
        ["/points", "Điểm thưởng"],
        ["/reports", "Báo cáo"],
        ["/settings", "Cài đặt"],
    ].map(([path, title]) => admin(path, title)),

    protectedRoute("/teacher", "Tổng quan", ["teacher"], () => import("../views/DashboardView.vue")),
    protectedRoute(
        "/teacher/classes",
        "Lớp của tôi",
        ["teacher"],
        () => import("../views/ClassesView.vue"),
        "view-classes",
    ),
    protectedRoute(
        "/teacher/classes/:id",
        "Chi tiết lớp",
        ["teacher"],
        () => import("../views/ClassesView.vue"),
        "view-classes",
    ),
    protectedRoute(
        "/teacher/children",
        "Thiếu nhi",
        ["teacher"],
        () => import("../views/ChildrenView.vue"),
        "view-children",
    ),
    protectedRoute(
        "/teacher/schedule",
        "Lịch dạy",
        ["teacher"],
        () => import("../views/ScheduleView.vue"),
        "view-classes",
    ),
    protectedRoute(
        "/teacher/attendance",
        "Điểm danh lớp",
        ["teacher"],
        () => import("../views/AttendanceView.vue"),
        "view-attendance",
    ),
    protectedRoute(
        "/teacher/attendance/sessions",
        "Danh sách phiên điểm danh",
        ["teacher"],
        () => import("../views/AttendanceSessionsView.vue"),
        "view-attendance",
    ),
    protectedRoute(
        "/teacher/assignments",
        "Bài tập",
        ["teacher"],
        () => import("../views/AssignmentsView.vue"),
        "view-assignments",
    ),
    protectedRoute("/teacher/assignments/new", "Tạo bài tập", ["teacher"], () => import("../views/AssignmentEditorView.vue"), "create-assignments"),
    protectedRoute("/teacher/assignments/:id/edit", "Chỉnh sửa bài tập", ["teacher"], () => import("../views/AssignmentEditorView.vue"), "update-assignments"),
    protectedRoute("/teacher/assignments/:id/submissions", "Chấm bài", ["teacher"], () => import("../views/AssignmentGradingView.vue"), "grade-assignments"),
    protectedRoute("/teacher/submissions", "Bài nộp cần chấm", ["teacher"], () => import("../views/AssignmentGradingView.vue"), "grade-assignments"),
    protectedRoute("/teacher/announcements", "Thông báo lớp", ["teacher"], () => import("../views/NotificationsView.vue"), "view-notifications"),
    protectedRoute(
        "/teacher/qr-scanner",
        "Tạo QR điểm danh",
        ["teacher"],
        () => import("../views/QrScannerView.vue"),
        "create-attendance-qr",
    ),
    ...[
        ["/mass-attendance", "Lịch sử đi lễ"],
        ["/lessons", "Bài học"],
        ["/leave-requests", "Đơn xin nghỉ"],
        ["/reports", "Báo cáo lớp"],
        ["/profile", "Hồ sơ"],
    ].map(([path, title]) =>
        protectedRoute(`/teacher${path}`, title, ["teacher"]),
    ),

    parent("", "Tổng quan"),
    protectedRoute(
        "/parent/children",
        "Các con của tôi",
        ["parent"],
        () => import("../views/ParentChildrenQrView.vue"),
    ),
    ...[
        ["/children/:id", "Hồ sơ thiếu nhi"],
        ["/schedule", "Lịch học"],
        ["/mass-attendance", "Lịch sử tham dự"],
        ["/assignments", "Bài tập"],
        ["/points", "Điểm thưởng"],
        ["/leave-requests", "Đơn xin nghỉ"],
        ["/notifications", "Thông báo"],
        ["/news", "Tin tức"],
        ["/profile", "Hồ sơ"],
    ].map(([path, title]) => parent(path, title)),

    {
        path: "/child",
        redirect: "/child/schedule",
        meta: { requiresAuth: true, title: "Lịch học", roles: ["child"] },
    },
    protectedRoute("/child/schedule", "Lịch học", ["child"], () => import("../views/ChildScheduleView.vue")),
    protectedRoute(
        "/child/my-qr",
        "Quét QR điểm danh",
        ["child"],
        () => import("../views/ChildQrView.vue"),
        "check-in-attendance-qr",
    ),
    protectedRoute("/child/assignments", "Bài tập", ["child"], () => import("../views/ChildAssignmentsView.vue"), "view-assignments"),
    protectedRoute("/child/assignments/:id", "Làm bài tập", ["child"], () => import("../views/ChildAssignmentTakeView.vue"), "view-assignments"),
    protectedRoute("/child/notifications", "Thông báo", ["child"], () => import("../views/NotificationsView.vue"), "view-notifications"),
    ...[
        ["/mass", "Thánh lễ"],
        ["/lessons", "Bài học"],
        ["/points", "Điểm thưởng"],
        ["/badges", "Huy hiệu"],
        ["/profile", "Cá nhân"],
    ].map(([path, title]) => child(path, title)),

    protectedRoute("/account", "Tài khoản của tôi", ["admin", "teacher", "parent", "child"], () => import("../views/AccountView.vue")),

    { path: "/dashboard", redirect: () => dashboardFor(useAuthStore().roles) },
    { path: "/lop-hoc", redirect: "/teacher/classes" },
    { path: "/diem-danh", redirect: "/teacher/attendance" },
    { path: "/bai-tap", redirect: "/teacher/assignments" },
    { path: "/thieu-nhi", redirect: "/teacher/children" },
    { path: "/lich-hoc", redirect: "/teacher/schedule" },
    {
        path: "/403",
        component: () => import("../views/ForbiddenView.vue"),
        meta: { public: true, title: "Không có quyền" },
    },
    {
        path: "/:pathMatch(.*)*",
        component: () => import("../views/NotFoundView.vue"),
        meta: { public: true, title: "Không tìm thấy trang" },
    },
];

export function dashboardFor(roles: string[]) {
    if (roles.includes("admin")) return "/admin";
    if (roles.includes("teacher")) return "/teacher";
    if (roles.includes("parent")) return "/parent";
    if (roles.includes("child")) return "/child/schedule";
    return "/403";
}
const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior: () => ({ top: 0 }),
});
router.beforeEach(async (to) => {
    const auth = useAuthStore();
    if (!auth.initialized) await auth.initialize();
    if (to.meta.requiresAuth && !auth.isAuthenticated)
        return { path: "/login", query: { redirect: to.fullPath } };
    if (["/login", "/register"].includes(to.path) && auth.isAuthenticated)
        return dashboardFor(auth.roles);
    if (
        to.meta.roles?.length &&
        !to.meta.roles.some((role) => auth.hasRole(role))
    )
        return "/403";
    if (to.meta.permission && !auth.hasPermission(to.meta.permission))
        return "/403";
    document.title = `${to.meta.title || "Hệ thống"} · Hành Trang Đức Tin`;
    return true;
});
export default router;

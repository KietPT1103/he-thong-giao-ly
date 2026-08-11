import {
    createRouter,
    createWebHistory,
    type RouteRecordRaw,
} from "vue-router";
import type { Component } from "vue";
import { useAuthStore } from "../stores/authStore";
import LandingPage from "../views/LandingPage.vue";
import LoginView from "../views/LoginView.vue";
import DashboardView from "../views/DashboardView.vue";
import ClassesView from "../views/ClassesView.vue";
import AttendanceView from "../views/AttendanceView.vue";
import ChildrenView from "../views/ChildrenView.vue";
import ScheduleView from "../views/ScheduleView.vue";
import AssignmentsView from "../views/AssignmentsView.vue";
import ModulePendingView from "../views/ModulePendingView.vue";
import ForbiddenView from "../views/ForbiddenView.vue";
import NotFoundView from "../views/NotFoundView.vue";

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
        component: LandingPage,
        meta: { public: true, title: "Hành Trang Đức Tin" },
    },
    {
        path: "/login",
        component: LoginView,
        meta: { public: true, title: "Đăng nhập" },
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

    protectedRoute("/admin", "Tổng quan", ["admin"], () => import("../views/AdminDashboardView.vue")),
    protectedRoute("/admin/accounts", "Quản lý tài khoản", ["admin"], () => import("../views/AdminAccountsView.vue"), "manage-users"),
    protectedRoute("/admin/parishes", "Giáo xứ", ["admin"], () => import("../views/AdminParishesView.vue"), "manage-system-settings"),
    protectedRoute("/admin/teachers", "Giáo lý viên", ["admin"], () => import("../views/AdminTeachersView.vue"), "manage-users"),
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

    protectedRoute("/teacher", "Tổng quan", ["teacher"], DashboardView),
    protectedRoute(
        "/teacher/classes",
        "Lớp của tôi",
        ["teacher"],
        ClassesView,
        "view-classes",
    ),
    protectedRoute(
        "/teacher/classes/:id",
        "Chi tiết lớp",
        ["teacher"],
        ClassesView,
        "view-classes",
    ),
    protectedRoute(
        "/teacher/children",
        "Thiếu nhi",
        ["teacher"],
        ChildrenView,
        "view-children",
    ),
    protectedRoute(
        "/teacher/schedule",
        "Lịch dạy",
        ["teacher"],
        ScheduleView,
        "view-classes",
    ),
    protectedRoute(
        "/teacher/attendance",
        "Điểm danh lớp",
        ["teacher"],
        AttendanceView,
        "view-attendance",
    ),
    protectedRoute(
        "/teacher/assignments",
        "Bài tập",
        ["teacher"],
        AssignmentsView,
    ),
    protectedRoute(
        "/teacher/qr-scanner",
        "Điểm danh QR",
        ["teacher"],
        () => import("../views/QrScannerView.vue"),
        "scan-attendance-qr",
    ),
    ...[
        ["/mass-attendance", "Lịch sử đi lễ"],
        ["/lessons", "Bài học"],
        ["/submissions", "Bài nộp cần chấm"],
        ["/leave-requests", "Đơn xin nghỉ"],
        ["/announcements", "Thông báo lớp"],
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
        "view-child-qr",
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

    child("", "Tổng quan"),
    protectedRoute(
        "/child/my-qr",
        "Mã QR của tôi",
        ["child"],
        () => import("../views/ChildQrView.vue"),
        "view-child-qr",
    ),
    ...[
        ["/schedule", "Lịch học"],
        ["/mass", "Thánh lễ"],
        ["/lessons", "Bài học"],
        ["/assignments", "Bài tập"],
        ["/points", "Điểm thưởng"],
        ["/badges", "Huy hiệu"],
        ["/notifications", "Thông báo"],
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
        component: ForbiddenView,
        meta: { public: true, title: "Không có quyền" },
    },
    {
        path: "/:pathMatch(.*)*",
        component: NotFoundView,
        meta: { public: true, title: "Không tìm thấy trang" },
    },
];

export function dashboardFor(roles: string[]) {
    if (roles.includes("admin")) return "/admin";
    if (roles.includes("teacher")) return "/teacher";
    if (roles.includes("parent")) return "/parent";
    if (roles.includes("child")) return "/child";
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
    if (to.path === "/login" && auth.isAuthenticated)
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

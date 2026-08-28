import client from "./client";
import type {
    ApiResponse,
    AttendanceSession,
    CatechismClass,
    Child,
    EnrollmentCandidate,
    TeacherEnrollment,
} from "../types/api";

export interface TeacherClassOption {
    id: number;
    parish_id: number;
    name: string;
    code?: string;
    capacity?: number | null;
    starts_on?: string;
    ends_on?: string;
    is_current?: boolean;
}

export interface TeacherClassOptions {
    parishes: Array<{ id: number; name: string; code: string }>;
    academic_years: TeacherClassOption[];
    levels: TeacherClassOption[];
    classrooms: TeacherClassOption[];
}

export interface TeacherClassInput {
    name: string;
    code: string;
    academic_year_id: number;
    catechism_level_id: number;
    classroom_id: number | null;
    status: "active" | "inactive";
}

export interface TeacherEnrollmentOptions {
    children: EnrollmentCandidate[];
    transfer_classes: Array<{ id: number; name: string; code: string }>;
}

export interface TeacherClassWorkspace {
    class: CatechismClass;
    children: Child[];
    children_meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export interface TeacherChildRow extends Child {
    class: { id: number; name: string; code: string };
}

export interface TeacherChildrenMeta {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    summary: {
        total_children: number;
        studying_children: number;
        class_count: number;
        next_schedule: {
            class_id: number;
            class_name: string;
            weekday: number;
            date: string;
            starts_at: string;
            ends_at: string;
        } | null;
    };
    filters: { classes: Array<{ id: number; name: string; code: string }> };
}

export type TeacherEnrollmentAction =
    | { action: "remove" | "stop" }
    | { action: "transfer"; target_class_id: number };

export const getTeacherClasses = (page = 1) =>
    client.get<ApiResponse<CatechismClass[]>>("/teachers/me/classes", {
        params: { page },
    });
export const getTeacherChildren = (params: { search?: string; class_id?: number; status?: string; page?: number } = {}) =>
    client.get<ApiResponse<TeacherChildRow[]>>("/teachers/me/children", { params });
export const getTeacherClass = (classId: number) =>
    client.get<ApiResponse<CatechismClass>>(`/classes/${classId}`);
export const getTeacherClassWorkspace = (classId: number) =>
    client.get<ApiResponse<TeacherClassWorkspace>>(`/teacher/classes/${classId}/workspace`);
export const getTeacherClassOptions = () =>
    client.get<ApiResponse<TeacherClassOptions>>("/teacher/classes/options");
export const createTeacherClass = (data: TeacherClassInput) =>
    client.post<ApiResponse<CatechismClass>>("/teacher/classes", data);
export const updateTeacherClass = (classId: number, data: TeacherClassInput) =>
    client.patch<ApiResponse<CatechismClass>>(`/teacher/classes/${classId}`, data);
export const archiveTeacherClass = (classId: number) =>
    client.delete<ApiResponse<null>>(`/teacher/classes/${classId}`);
export const getTeacherEnrollmentOptions = (classId: number, search = "") =>
    client.get<ApiResponse<TeacherEnrollmentOptions>>(
        `/teacher/classes/${classId}/enrollment-options`,
        { params: { search: search || undefined } },
    );
export const enrollTeacherClassChild = (classId: number, childId: number) =>
    client.post<ApiResponse<TeacherEnrollment>>(
        `/teacher/classes/${classId}/enrollments`,
        { child_id: childId },
    );
export const updateTeacherClassEnrollment = (
    classId: number,
    childId: number,
    payload: TeacherEnrollmentAction,
) => client.patch<ApiResponse<TeacherEnrollment | { source: TeacherEnrollment; target: TeacherEnrollment }>>(
    `/teacher/classes/${classId}/enrollments/${childId}`,
    payload,
);
export const getClassChildren = (
    classId: number,
    params: { page?: number; search?: string; status?: "studying" | "inactive" } = {},
) =>
    client.get<ApiResponse<Child[]>>(`/classes/${classId}/children`, {
        params: { ...params, compact: 1 },
    });
export interface AttendanceSessionPage {
    data: AttendanceSession[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
}
export interface AttendanceWorkspace {
    classes: Array<{ id: number; name: string; code: string }>;
    selected_class_id: number | null;
    sessions: AttendanceSessionPage;
    session_history_total: number;
    children: Child[];
}
export const getAttendanceWorkspace = (classId?: number) =>
    client.get<ApiResponse<AttendanceWorkspace>>("/teacher/attendance-workspace", {
        params: { class_id: classId },
    });
export const getAttendanceSessions = (classId: number, page = 1, status?: "active" | "ended" | "cancelled") =>
    client.get<ApiResponse<AttendanceSessionPage>>(
        `/classes/${classId}/attendance-sessions`, { params: { page, status } },
    );
export const createAttendanceSession = (
    classId: number,
    payload: { held_at: string; note?: string },
) =>
    client.post<ApiResponse<AttendanceSession>>(
        `/classes/${classId}/attendance-sessions`,
        payload,
    );
export const getAttendanceSession = (sessionId: number) =>
    client.get<ApiResponse<AttendanceSession>>(
        `/attendance-sessions/${sessionId}`,
    );
export const saveAttendance = (
    sessionId: number,
    attendances: Array<{
        child_id: number;
        status: string;
        note?: string | null;
    }>,
) =>
    client.post<ApiResponse<AttendanceSession>>(
        `/attendance-sessions/${sessionId}/mark`,
        { attendances },
    );
export const markAllPresent = (sessionId: number) =>
    client.post<ApiResponse<AttendanceSession>>(
        `/attendance-sessions/${sessionId}/mark-all-present`,
    );
export const endAttendanceSession = (sessionId: number) =>
    client.post<ApiResponse<AttendanceSession>>(`/attendance-sessions/${sessionId}/end`);
export const cancelAttendanceSession = (sessionId: number) =>
    client.post<ApiResponse<AttendanceSession>>(`/attendance-sessions/${sessionId}/cancel`);
export const deleteAttendanceSession = (sessionId: number) =>
    client.delete<ApiResponse<null>>(`/attendance-sessions/${sessionId}`);

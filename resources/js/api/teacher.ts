import client from "./client";
import type {
    ApiResponse,
    AttendanceSession,
    CatechismClass,
    Child,
} from "../types/api";

export const getTeacherClasses = (page = 1) =>
    client.get<ApiResponse<CatechismClass[]>>("/teachers/me/classes", {
        params: { page },
    });
export const getClassChildren = (classId: number, page = 1) =>
    client.get<ApiResponse<Child[]>>(`/classes/${classId}/children`, {
        params: { page },
    });
export const getAttendanceSessions = (classId: number) =>
    client.get<ApiResponse<{ data: AttendanceSession[] }>>(
        `/classes/${classId}/attendance-sessions`,
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

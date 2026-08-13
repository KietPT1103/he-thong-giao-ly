import client from "./client";
import type { ApiResponse } from "../types/api";

export interface AttendanceSessionQrPayload {
    token: string;
    session: {
        id: number;
        held_at: string;
        qr_expires_at: string;
        note: string | null;
        class: { id: number; name: string; code: string };
    };
}

export interface AttendanceQrCheckInResult {
    attendance: {
        id: number;
        child_id: number;
        status: "present" | "late";
        arrived_at: string;
    };
    session: {
        id: number;
        held_at: string;
        class: { id: number; name: string; code: string };
    };
    checked_in_at: string;
    was_duplicate: boolean;
}

export interface FamilyChild {
    id: number;
    code: string;
    full_name: string;
    saint_name: string | null;
    status: string;
}

export const getMyFamilyChildren = () =>
    client.get<ApiResponse<FamilyChild[]>>("/parents/me/children");

export const createAttendanceQr = (
    classId: number,
    payload: { held_at: string; qr_expires_at: string; note?: string },
) => client.post<ApiResponse<AttendanceSessionQrPayload>>(`/classes/${classId}/attendance-qr`, payload);

export const getAttendanceSessionQr = (sessionId: number) =>
    client.get<ApiResponse<AttendanceSessionQrPayload>>(`/attendance-sessions/${sessionId}/qr`);

export const checkInAttendanceQr = (token: string) =>
    client.post<ApiResponse<AttendanceQrCheckInResult>>("/attendance/qr/check-in", { token });

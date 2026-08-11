import client from "./client";
import type { ApiResponse } from "../types/api";

export interface ChildQrPayload {
    token: string;
    version: number;
    child: { id: number; code: string; full_name: string };
}

export interface QrScanResult {
    attendance: {
        id: number;
        child_id: number;
        status: "present" | "late";
        arrived_at: string;
    };
    child: { id: number; code: string; full_name: string; saint_name: string | null };
    scanned_at: string;
    was_duplicate: boolean;
}

export interface FamilyChild {
    id: number;
    code: string;
    full_name: string;
    saint_name: string | null;
    status: string;
}

export const getChildQr = (childId: number) =>
    client.get<ApiResponse<ChildQrPayload>>(`/children/${childId}/qr`);

export const rotateChildQr = (childId: number) =>
    client.post<ApiResponse<ChildQrPayload>>(`/admin/children/${childId}/qr/rotate`);

export const scanAttendanceQr = (sessionId: number, token: string) =>
    client.post<ApiResponse<QrScanResult>>(`/attendance-sessions/${sessionId}/qr/scan`, { token });

export const getMyFamilyChildren = () =>
    client.get<ApiResponse<FamilyChild[]>>("/parents/me/children");

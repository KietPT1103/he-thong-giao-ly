import client from "./client";
import type { ApiResponse } from "../types/api";

export interface ChildDeviceStatus {
    is_active: boolean;
    is_current_device: boolean;
    activated_at: string | null;
    expires_at: string | null;
    last_used_at: string | null;
}

export const getChildDevice = () =>
    client.get<ApiResponse<ChildDeviceStatus>>("/child-device");

export const activateChildDevice = () =>
    client.post<ApiResponse<ChildDeviceStatus>>("/child-device");

export const revokeChildDevice = () =>
    client.delete<ApiResponse<ChildDeviceStatus>>("/child-device");

import client from "./client";
import type { ApiResponse, User } from "../types/api";

export interface AccountOptions {
    roles: Array<{ name: string; permissions: string[] }>;
    permissions: string[];
}
export interface AccountMeta { current_page: number; last_page: number; per_page: number; total: number }
export const getProfile = () => client.get<ApiResponse<User>>("/account");
export const updateProfile = (data: { name: string; email: string; phone: string | null }) => client.patch<ApiResponse<User>>("/account", data);
export const uploadAvatar = (avatar: File) => {
    const data = new FormData(); data.append("avatar", avatar);
    return client.post<ApiResponse<User>>("/account/avatar", data);
};
export const listAccounts = (params: { search?: string; role?: string; status?: string; page?: number }) => client.get<ApiResponse<User[]>>("/admin/accounts", { params });
export const getAccountOptions = () => client.get<ApiResponse<AccountOptions>>("/admin/accounts/options");
export const createAccount = (data: { name: string; email: string; phone?: string; password: string; role: string }) => client.post<ApiResponse<User>>("/admin/accounts", data);
export const updateAccount = (id: number, data: Partial<Pick<User, "name" | "email" | "phone">>) => client.patch<ApiResponse<User>>(`/admin/accounts/${id}`, data);
export const updateAccountStatus = (id: number, status: "active" | "blocked") => client.patch<ApiResponse<User>>(`/admin/accounts/${id}/status`, { status });
export const updateAccountAccess = (id: number, data: { role: string; granted_permissions: string[]; denied_permissions: string[] }) => client.put<ApiResponse<User>>(`/admin/accounts/${id}/access`, data);
export const resetAccountPassword = (id: number, password: string, passwordConfirmation: string) => client.put<ApiResponse<null>>(`/admin/accounts/${id}/password`, { password, password_confirmation: passwordConfirmation });
export const archiveAccount = (id: number) => client.delete<ApiResponse<null>>(`/admin/accounts/${id}`);
export const restoreAccount = (id: number) => client.post<ApiResponse<User>>(`/admin/accounts/${id}/restore`);

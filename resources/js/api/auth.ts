import axios from "axios";
import client from "./client";
import type { ApiResponse, User } from "../types/api";

export const getCsrfCookie = () =>
    axios.get("/sanctum/csrf-cookie", { withCredentials: true });
export const login = (email: string, password: string) =>
    client.post<ApiResponse<User | null> & { code?: string }>("/auth/login", { email, password });
export const mfaChallenge = (code: string) =>
    client.post<ApiResponse<User>>("/auth/mfa-challenge", { code });
export const logout = () => client.post<ApiResponse<null>>("/auth/logout");
export const confirmPassword = (password: string) =>
    client.post<ApiResponse<null>>("/auth/confirm-password", { password });
export const me = () => client.get<ApiResponse<User>>("/auth/me");

import axios from "axios";
import client from "./client";
import type { ApiResponse, User } from "../types/api";

export const getCsrfCookie = () =>
    axios.get("/sanctum/csrf-cookie", { withCredentials: true });
export const login = (email: string, password: string) =>
    client.post<ApiResponse<User>>("/auth/login", { email, password });
export const logout = () => client.post<ApiResponse<null>>("/auth/logout");
export const confirmPassword = (password: string) =>
    client.post<ApiResponse<null>>("/auth/confirm-password", { password });
export const me = () => client.get<ApiResponse<User>>("/auth/me");

import { defineStore } from "pinia";
import {
    confirmPassword,
    getCsrfCookie,
    login,
    mfaChallenge,
    logout,
    me,
} from "../api/auth";
import type { User } from "../types/api";

export const useAuthStore = defineStore("auth", {
    state: () => ({
        user: null as User | null,
        isLoading: false,
        isSubmitting: false,
        initialized: false,
    }),
    getters: {
        isAuthenticated: (state) => !!state.user,
        roles: (state) => state.user?.roles ?? [],
        permissions: (state) => state.user?.permissions ?? [],
    },
    actions: {
        async initialize() {
            if (this.initialized) return;
            this.isLoading = true;
            try {
                this.user = (await me()).data.data;
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
                this.isLoading = false;
            }
        },
        async fetchCurrentUser() {
            this.initialized = false;
            await this.initialize();
        },
        async login(email: string, password: string) {
            this.isSubmitting = true;
            try {
                await getCsrfCookie();
                const response = await login(email, password);
                if (response.status === 202 || response.data.code === "MFA_REQUIRED") {
                    return "mfa_required" as const;
                }
                this.user = response.data.data;
                this.initialized = true;
                return "authenticated" as const;
            } finally {
                this.isSubmitting = false;
            }
        },
        async completeMfa(code: string) {
            this.isSubmitting = true;
            try {
                this.user = (await mfaChallenge(code)).data.data;
                this.initialized = true;
            } finally {
                this.isSubmitting = false;
            }
        },
        async logout() {
            await logout();
            this.user = null;
        },
        expireSession() {
            this.user = null;
            this.initialized = true;
            this.isLoading = false;
        },
        async confirmPassword(password: string) {
            await confirmPassword(password);
        },
        hasRole(role: string) {
            return this.roles.includes(role);
        },
        hasPermission(permission: string) {
            return this.permissions.includes(permission);
        },
    },
});

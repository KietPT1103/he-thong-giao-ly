import axios, { AxiosError } from "axios";

const client = axios.create({
    baseURL: import.meta.env.VITE_API_URL ?? "/api",
    withCredentials: true,
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
    },
});

client.interceptors.response.use(
    (response) => response,
    async (error: AxiosError) => {
        const isDeviceCheckIn = error.config?.url === "/attendance/qr/check-in";
        if (
            error.response?.status === 401 &&
            error.config?.url !== "/auth/me" &&
            !isDeviceCheckIn &&
            location.pathname !== "/login"
        ) {
            window.dispatchEvent(
                new CustomEvent("auth:expired", {
                    detail: { message: "Phiên đăng nhập đã hết hạn." },
                }),
            );
        }

        return Promise.reject(error);
    },
);

export default client;

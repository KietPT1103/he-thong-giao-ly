import type { RuleObject } from "ant-design-vue/es/form/interface";

export const VIETNAMESE_PHONE_ERROR = "Số điện thoại không hợp lệ.";

export function isValidVietnamesePhone(value: unknown): boolean {
    if (value === null || value === undefined || value === "") return true;
    if (typeof value !== "string") return false;

    const normalized = value.replace(/[\s.()\-]/g, "");

    return /^(?:\+84|0)(?:[35789]\d{8}|2\d{9})$/.test(normalized);
}

export function vietnamesePhoneRule(): RuleObject {
    return {
        validator: async (_rule: unknown, value: unknown) => {
            if (!isValidVietnamesePhone(value)) throw new Error(VIETNAMESE_PHONE_ERROR);
        },
        trigger: ["blur", "change"],
    };
}

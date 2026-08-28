import type { CatechismClass, ClassSchedule } from "../../types/api";

export type ScheduleViewMode = "day" | "week" | "month";
export type ScheduleTab = "all" | "teaching" | "attendance";

export interface TeachingCalendarEvent {
    key: string;
    kind: "teaching";
    date: Date;
    dateKey: string;
    startsAt: string;
    endsAt: string;
    startMinutes: number;
    endMinutes: number;
    classItem: CatechismClass;
    schedule: ClassSchedule;
    tone: "blue" | "violet" | "green" | "amber";
}

const tones: TeachingCalendarEvent["tone"][] = ["blue", "violet", "green"];

export function startOfWeek(value: Date): Date {
    const date = startOfDay(value);
    const day = date.getDay() || 7;
    date.setDate(date.getDate() - day + 1);
    return date;
}

export function startOfDay(value: Date): Date {
    const date = new Date(value);
    date.setHours(0, 0, 0, 0);
    return date;
}

export function addDays(value: Date, amount: number): Date {
    const date = new Date(value);
    date.setDate(date.getDate() + amount);
    return date;
}

export function localDateKey(value: Date): string {
    const year = value.getFullYear();
    const month = String(value.getMonth() + 1).padStart(2, "0");
    const day = String(value.getDate()).padStart(2, "0");
    return `${year}-${month}-${day}`;
}

export function sameDay(a: Date, b: Date): boolean {
    return localDateKey(a) === localDateKey(b);
}

export function weekDays(anchor: Date): Date[] {
    const monday = startOfWeek(anchor);
    return Array.from({ length: 7 }, (_, index) => addDays(monday, index));
}

export function monthGridDays(anchor: Date): Date[] {
    const first = new Date(anchor.getFullYear(), anchor.getMonth(), 1);
    const gridStart = startOfWeek(first);
    return Array.from({ length: 42 }, (_, index) => addDays(gridStart, index));
}

export function timeToMinutes(value: string): number {
    const [hours = 0, minutes = 0] = value.slice(0, 5).split(":").map(Number);
    return hours * 60 + minutes;
}

export function buildTeachingEvents(classes: CatechismClass[], days: Date[]): TeachingCalendarEvent[] {
    if (!days.length) return [];
    return classes.flatMap((classItem, classIndex) => classItem.schedules.flatMap(schedule =>
        days.filter(day => (day.getDay() || 7) === schedule.weekday).flatMap(date => {
            const dateKey = localDateKey(date);
            if (schedule.starts_on && dateKey < schedule.starts_on) return [];
            if (schedule.ends_on && dateKey > schedule.ends_on) return [];

            return [{
                key: `${schedule.id}-${dateKey}`,
                kind: "teaching" as const,
                date,
                dateKey,
                startsAt: schedule.starts_at.slice(0, 5),
                endsAt: schedule.ends_at.slice(0, 5),
                startMinutes: timeToMinutes(schedule.starts_at),
                endMinutes: timeToMinutes(schedule.ends_at),
                classItem,
                schedule,
                tone: tones[classIndex % tones.length],
            }];
        }),
    )).sort((a, b) => a.date.getTime() - b.date.getTime() || a.startMinutes - b.startMinutes);
}

export function formatWeekRange(days: Date[]): string {
    if (!days.length) return "";
    const formatter = new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit" });
    return `${formatter.format(days[0])} – ${formatter.format(days[days.length - 1])} · ${days[0].getFullYear()}`;
}

export function formatMonth(value: Date): string {
    return new Intl.DateTimeFormat("vi-VN", { month: "long", year: "numeric" }).format(value);
}

export function formatLongDate(value: Date): string {
    return new Intl.DateTimeFormat("vi-VN", { weekday: "long", day: "2-digit", month: "2-digit", year: "numeric" }).format(value);
}

import client from "./client";
import type { ApiResponse, CatechismClass } from "../types/api";

export interface ChildScheduleData {
    child: { id: number; code: string; full_name: string };
    class: CatechismClass | null;
}

export const getChildSchedule = () =>
    client.get<ApiResponse<ChildScheduleData>>("/child/schedule");

import client from './client';
import type { ApiResponse } from '../types/api';

export interface AdminDashboard {
    summary:{
        parish_count:number;
        teacher_count:number;
        child_count:number;
        active_class_count:number;
        pending_leave_request_count:number;
        class_session_count_this_week:number;
    };
    attendance:{rate_this_week:number|null;attended:number;total:number};
    parishes:Array<{id:number;name:string;code:string;children_count:number;academic_years_count:number}>;
    recent_announcements:Array<{id:number;title:string;importance:string;created_at:string}>;
    recent_sessions:Array<{id:number;held_at:string;attendances_count:number;catechism_class:{id:number;name:string}}>;
}

export const getAdminDashboard = () =>
    client.get<ApiResponse<AdminDashboard>>('/admin/dashboard');

export interface AdminListItem {
    id:number;
    name:string;
    code:string;
    secondary:string;
    details:string[];
    status:string;
}

export interface AdminListMeta {
    current_page:number;
    last_page:number;
    per_page:number;
    total:number;
}

export const getAdminDirectory = (module:string, params:{search?:string;page?:number;status?:string}) =>
    client.get<ApiResponse<AdminListItem[]>>(`/admin/${module}`, {params});

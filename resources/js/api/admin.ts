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

export interface ParishDependencyCounts {
    teachers:number;
    children:number;
    academic_years:number;
    levels:number;
    classrooms:number;
    announcements:number;
}

export interface ParishTeacher {
    id:number;
    code:string|null;
    phone:string|null;
    parish_id:number;
    user:{id:number;name:string;email:string;status:string};
}

export interface Parish {
    id:number;
    name:string;
    code:string;
    phone:string|null;
    email:string|null;
    teacher_count:number;
    children_count:number;
    academic_years_count:number;
    dependency_counts:ParishDependencyCounts;
    teachers?:ParishTeacher[];
    secondary:string;
    details:string[];
    status:string;
}

export interface ParishInput {
    name:string;
    code:string;
    phone:string|null;
    email:string|null;
}

export interface TeacherClass {
    id:number;
    name:string;
    code:string;
    status:string;
    role:string;
    academic_year:{id:number;name:string}|null;
    level:{id:number;name:string}|null;
}

export interface Teacher {
    id:number;
    user_id:number;
    name:string;
    email:string;
    phone:string|null;
    code:string;
    parish_id:number;
    parish:{id:number;name:string;code:string};
    account_status:"active"|"blocked"|"archived";
    is_archived:boolean;
    must_change_password:boolean;
    classes_count:number;
    classes?:TeacherClass[];
    created_at:string;
    secondary:string;
    details:string[];
    status:string;
}

export interface TeacherListParams {
    search?:string;
    page?:number;
    per_page?:number;
    parish_id?:number;
    status?:"active"|"blocked"|"archived";
    sort?:"name"|"created_at";
    direction?:"asc"|"desc";
}

export interface TeacherCreateInput {
    name:string;
    email:string;
    phone:string|null;
    code:string;
    parish_id:number;
    password:string;
    password_confirmation:string;
}

export interface TeacherUpdateInput {
    name:string;
    email:string;
    phone:string|null;
    code:string;
    parish_id:number;
    status:"active"|"blocked";
}

export const getAdminDirectory = (module:string, params:{search?:string;page?:number;status?:string;per_page?:number}) =>
    client.get<ApiResponse<AdminListItem[]>>(`/admin/${module}`, {params});

export const listParishes = (params:{search?:string;page?:number;per_page?:number}) =>
    client.get<ApiResponse<Parish[]>>('/admin/parishes', {params});

export const getParish = (id:number) =>
    client.get<ApiResponse<Parish>>(`/admin/parishes/${id}`);

export const createParish = (data:ParishInput) =>
    client.post<ApiResponse<Parish>>('/admin/parishes', data);

export const updateParish = (id:number, data:ParishInput) =>
    client.patch<ApiResponse<Parish>>(`/admin/parishes/${id}`, data);

export const assignParishTeachers = (id:number, teacherIds:number[]) =>
    client.put<ApiResponse<Parish>>(`/admin/parishes/${id}/teachers`, {teacher_ids:teacherIds});

export const deleteParish = (id:number) =>
    client.delete<ApiResponse<null>>(`/admin/parishes/${id}`);

export const listTeachers = (params:TeacherListParams) =>
    client.get<ApiResponse<Teacher[]>>('/admin/teachers', {params});

export const getTeacher = (id:number) =>
    client.get<ApiResponse<Teacher>>(`/admin/teachers/${id}`);

export const createTeacher = (data:TeacherCreateInput) =>
    client.post<ApiResponse<Teacher>>('/admin/teachers', data);

export const updateTeacher = (id:number, data:TeacherUpdateInput) =>
    client.patch<ApiResponse<Teacher>>(`/admin/teachers/${id}`, data);

export const archiveTeacher = (id:number) =>
    client.delete<ApiResponse<null>>(`/admin/teachers/${id}`);

export const restoreTeacher = (id:number) =>
    client.post<ApiResponse<Teacher>>(`/admin/teachers/${id}/restore`);

export interface ClassScheduleInput {
    weekday:number;
    starts_at:string;
    ends_at:string;
    starts_on:string|null;
    ends_on:string|null;
}

export interface ClassSchedule extends ClassScheduleInput { id:number }
export interface ClassTeacher { id:number;name:string;email:string;code:string;role:"primary"|"assistant" }
export interface ClassEnrollment { id:number;status:"active"|"inactive";child:{id:number;code:string;full_name:string} }
export interface AdminClass {
    id:number;
    name:string;
    code:string;
    status:"active"|"inactive";
    is_archived:boolean;
    academic_year_id:number;
    catechism_level_id:number;
    classroom_id:number|null;
    parish:{id:number;name:string;code:string}|null;
    academic_year:{id:number;name:string;starts_on:string;ends_on:string;is_current:boolean}|null;
    level:{id:number;name:string;code:string}|null;
    classroom:{id:number;name:string;capacity:number|null}|null;
    enrollments_count:number;
    teachers_count:number;
    attendance_sessions_count:number;
    teachers?:ClassTeacher[];
    enrollments?:ClassEnrollment[];
    schedules:ClassSchedule[];
}

export interface ClassListParams {
    search?:string;
    parish_id?:number;
    academic_year_id?:number;
    catechism_level_id?:number;
    status?:"active"|"inactive"|"archived";
    page?:number;
    per_page?:number;
}

export interface ClassInput {
    name:string;
    code:string;
    academic_year_id:number;
    catechism_level_id:number;
    classroom_id:number|null;
    status:"active"|"inactive";
}

export interface ClassOption { id:number;parish_id:number;name:string;code?:string;capacity?:number|null;starts_on?:string;ends_on?:string;is_current?:boolean }
export interface ClassPersonOption { id:number;name?:string;full_name?:string;email?:string;code:string;status?:string }
export interface ClassOptions {
    parishes:Array<Pick<Parish,"id"|"name"|"code">>;
    academic_years:ClassOption[];
    levels:ClassOption[];
    classrooms:ClassOption[];
    teachers:ClassPersonOption[];
    children:ClassPersonOption[];
}

export interface BusinessApiError {
    response?:{data?:{message?:string;code?:string;data?:{conflicts?:Array<{teacher_name?:string;class_name:string;class_id:number}>;child_ids?:number[]};errors?:Record<string,string[]>}};
}

export const listClasses = (params:ClassListParams) =>
    client.get<ApiResponse<AdminClass[]>>('/admin/classes', {params});
export const getClass = (id:number, includeArchived = false) =>
    client.get<ApiResponse<AdminClass>>(`/admin/classes/${id}`, {params:{include_archived:includeArchived || undefined}});
export const getClassOptions = (parishId?:number, search?:string) =>
    client.get<ApiResponse<ClassOptions>>('/admin/classes/options', {params:{parish_id:parishId, search:search || undefined}});
export const createClass = (data:ClassInput) => client.post<ApiResponse<AdminClass>>('/admin/classes', data);
export const updateClass = (id:number, data:ClassInput) => client.patch<ApiResponse<AdminClass>>(`/admin/classes/${id}`, data);
export const archiveClass = (id:number) => client.delete<ApiResponse<null>>(`/admin/classes/${id}`);
export const restoreClass = (id:number) => client.post<ApiResponse<AdminClass>>(`/admin/classes/${id}/restore`);
export const assignClassTeachers = (id:number, teachers:Array<{teacher_id:number;role:"primary"|"assistant"}>, allowTeacherConflicts = false) =>
    client.put<ApiResponse<AdminClass>>(`/admin/classes/${id}/teachers`, {teachers, allow_teacher_conflicts:allowTeacherConflicts});
export const updateClassEnrollments = (id:number, enrollments:Array<{child_id:number;status:"active"|"inactive"}>) =>
    client.put<ApiResponse<AdminClass>>(`/admin/classes/${id}/enrollments`, {enrollments});
export const updateClassSchedules = (id:number, schedules:ClassScheduleInput[], allowTeacherConflicts = false) =>
    client.put<ApiResponse<AdminClass>>(`/admin/classes/${id}/schedules`, {schedules, allow_teacher_conflicts:allowTeacherConflicts});

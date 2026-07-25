export interface ApiResponse<T>{success:boolean;message:string;data:T;meta:Record<string,unknown>}
export interface User{ id:number; name:string; email:string; status:'active'|'blocked'|'inactive'; roles:string[]; permissions:string[]; must_change_password:boolean }
export interface PaginatedResponse<T>{data:T[];current_page:number;last_page:number;total:number}
export interface ClassSchedule{id:number;weekday:number;starts_at:string;ends_at:string;starts_on:string|null;ends_on:string|null}
export interface CatechismClass{id:number;name:string;code:string;status:string;academic_year?:{id:number;name:string};level?:{id:number;name:string};classroom?:{id:number|null;name:string|null};children_count?:number;schedules:ClassSchedule[]}
export interface Child{id:number;code:string;full_name:string;saint_name:string|null;date_of_birth:string|null;status:string;parents?:Array<{id:number;name:string;phone:string|null}>}
export type AttendanceStatus='present'|'late'|'excused_absence'|'unexcused_absence'|'left_early'|'unknown';
export interface Attendance{id:number;child_id:number;status:AttendanceStatus;note:string|null;child?:Child}
export interface AttendanceSession{id:number;catechism_class_id:number;held_at:string;note:string|null;attendances:Attendance[];catechism_class?:CatechismClass}

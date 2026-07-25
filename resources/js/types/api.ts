export interface ApiResponse<T>{success:boolean;message:string;data:T;meta:Record<string,unknown>}
export interface User{ id:number; name:string; email:string; status:'active'|'blocked'|'inactive'; roles:string[]; permissions:string[]; must_change_password:boolean }
export interface PaginatedResponse<T>{data:T[];current_page:number;last_page:number;total:number}
export interface CatechismClass{id:number;name:string;code:string;status:string;academic_year_id:number;catechism_level_id:number}
export interface Attendance{id:number;child_id:number;status:string;note:string|null}
export interface AttendanceSession{id:number;catechism_class_id:number;held_at:string;attendances:Attendance[]}

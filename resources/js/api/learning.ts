import client from "./client";
import type { ApiResponse } from "../types/api";
import type {
    Announcement, AnnouncementInput, Assignment, AssignmentInput,
    AssignmentReport, Page, Submission,
} from "../types/learning";

export const getTeacherAssignments = (params: { search?: string; status?: string; class_id?: number; page?: number } = {}) =>
    client.get<ApiResponse<Page<Assignment>>>("/teacher/assignments", { params });
export const getTeacherAssignment = (id: number) =>
    client.get<ApiResponse<Assignment>>(`/teacher/assignments/${id}`);
export const createAssignment = (payload: AssignmentInput) =>
    client.post<ApiResponse<Assignment>>("/teacher/assignments", payload);
export const updateAssignment = (id: number, payload: AssignmentInput) =>
    client.patch<ApiResponse<Assignment>>(`/teacher/assignments/${id}`, payload);
export const publishAssignment = (id: number) =>
    client.post<ApiResponse<Assignment>>(`/teacher/assignments/${id}/publish`);
export const archiveAssignment = (id: number) =>
    client.delete<ApiResponse<null>>(`/teacher/assignments/${id}`);
export const changeAssignmentDueDate = (id: number, due_at: string) =>
    client.patch<ApiResponse<Assignment>>(`/teacher/assignments/${id}/due-date`, { due_at });
export const setAssignmentAccommodation = (assignmentId: number, childId: number, payload: { due_at: string | null; extra_attempts: number; reason: string }) =>
    client.put<ApiResponse<unknown>>(`/teacher/assignments/${assignmentId}/accommodations/${childId}`, payload);
export const closeAssignment = (id: number) =>
    client.post<ApiResponse<Assignment>>(`/teacher/assignments/${id}/close`);
export const withdrawAssignment = (id: number, reason: string) =>
    client.post<ApiResponse<Assignment>>(`/teacher/assignments/${id}/withdraw`, { reason });
export const reopenSubmission = (id: number, reason: string) =>
    client.post<ApiResponse<Submission>>(`/teacher/submissions/${id}/reopen`, { reason });
export const getAssignmentReport = (id: number) =>
    client.get<ApiResponse<AssignmentReport>>(`/teacher/assignments/${id}/report`);
export const assignmentReportExportUrl = (id: number) => `/api/teacher/assignments/${id}/report/export`;

export const getAssignmentSubmissions = (id: number, params: { status?: string; search?: string; page?: number } = {}) =>
    client.get<ApiResponse<Page<Submission>>>(`/teacher/assignments/${id}/submissions`, { params });
export const gradeSubmission = (id: number, payload: {
    version: number; general_feedback?: string; reason?: string;
    answers: Array<{ question_id: number; score: number; feedback?: string; rubric_scores?: Array<{ label: string; score: number }> }>;
}) => client.patch<ApiResponse<Submission>>(`/teacher/submissions/${id}/grade`, payload);
export const releaseAssignmentResults = (id: number) =>
    client.post<ApiResponse<Assignment>>(`/teacher/assignments/${id}/release`);

export const getChildAssignments = (params: { search?: string; page?: number } = {}) =>
    client.get<ApiResponse<Page<Assignment>>>("/child/assignments", { params });
export const getChildAssignment = (id: number) =>
    client.get<ApiResponse<Assignment>>(`/child/assignments/${id}`);
export const startAssignmentAttempt = (id: number) =>
    client.post<ApiResponse<Submission>>(`/child/assignments/${id}/attempts`);
export const saveSubmissionAnswers = (submissionId: number, payload: {
    version: number; answers: Array<{ question_id: number; answer: unknown }>;
}) => client.patch<ApiResponse<{ id: number; version: number; status: string; saved_at: string }>>(
    `/child/submissions/${submissionId}/answers`, payload,
);
export const submitAssignment = (submissionId: number) =>
    client.post<ApiResponse<Submission>>(`/child/submissions/${submissionId}/submit`);
export const uploadAssignmentFile = (assignmentId: number, file: File) => {
    const body = new FormData(); body.append("file", file);
    return client.post<ApiResponse<import("../types/learning").LearningFile>>(`/teacher/assignments/${assignmentId}/files`, body);
};
export const deleteAssignmentFile = (fileId: number) =>
    client.delete<ApiResponse<null>>(`/teacher/assignment-files/${fileId}`);
export const uploadSubmissionFile = (submissionId: number, file: File) => {
    const body = new FormData(); body.append("file", file);
    return client.post<ApiResponse<import("../types/learning").LearningFile>>(`/child/submissions/${submissionId}/files`, body);
};
export const deleteSubmissionFile = (fileId: number) =>
    client.delete<ApiResponse<null>>(`/child/submission-files/${fileId}`);

export const getTeacherAnnouncements = (params: { search?: string; status?: string; page?: number } = {}) =>
    client.get<ApiResponse<Page<Announcement>>>("/teacher/announcements", { params });
export const createAnnouncement = (payload: AnnouncementInput) =>
    client.post<ApiResponse<Announcement>>("/teacher/announcements", payload);
export const sendAnnouncement = (id: number) =>
    client.post<ApiResponse<Announcement>>(`/teacher/announcements/${id}/send`);
export const remindAnnouncement = (id: number) =>
    client.post<ApiResponse<{ reminded_count: number }>>(`/teacher/announcements/${id}/remind`);
export const withdrawAnnouncement = (id: number) =>
    client.post<ApiResponse<Announcement>>(`/teacher/announcements/${id}/withdraw`);
export const getNotifications = (unread = false) =>
    client.get<ApiResponse<Announcement[]>>("/notifications", { params: { unread: unread ? 1 : undefined } });
export const readNotification = (id: number) =>
    client.post<ApiResponse<Announcement>>(`/notifications/${id}/read`);
export const acknowledgeNotification = (id: number) =>
    client.post<ApiResponse<Announcement>>(`/notifications/${id}/acknowledge`);
export const readAllNotifications = () =>
    client.post<ApiResponse<{ updated_count: number }>>("/notifications/read-all");

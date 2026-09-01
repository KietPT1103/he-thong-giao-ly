export type AssignmentStatus =
    | "draft" | "scheduled" | "published" | "closed"
    | "grading" | "released" | "archived" | "withdrawn";
export type QuestionType = "single_choice" | "multiple_choice" | "true_false" | "short_answer" | "essay";
export type SubmissionStatus = "in_progress" | "submitted" | "grading" | "graded" | "released" | "reopened";

export interface LearningClassRef { id: number; name: string; code: string }
export interface LearningChildRef { id: number; code: string; full_name: string }
export interface LearningFile { id: number; original_name: string; mime_type: string; size: number; download_url: string }
export interface QuestionOption { id?: number; content: string; is_correct?: boolean }
export interface RubricItem { label: string; points: number }
export interface AssignmentQuestion {
    id?: number;
    source_question_id?: number | null;
    type: QuestionType;
    prompt: string;
    explanation?: string;
    points: number;
    position: number;
    options?: QuestionOption[] | null;
    accepted_answers?: string[] | null;
    rubric?: RubricItem[] | null;
    settings?: { partial_credit?: boolean } | null;
}
export interface AssignmentTarget {
    id?: number;
    catechism_class_id: number;
    child_id?: number | null;
    due_at?: string | null;
    attempt_limit?: number | null;
    catechism_class?: LearningClassRef;
    child?: LearningChildRef | null;
}
export interface AssignmentRecipient {
    id: number;
    child_id: number;
    catechism_class_id: number;
    due_at: string | null;
    child?: LearningChildRef;
}
export interface SubmissionAnswer {
    id?: number;
    assignment_question_id: number;
    answer?: unknown;
    auto_score?: number | null;
    manual_score?: number | null;
    feedback?: string | null;
    rubric_scores?: Array<{ label: string; score: number }> | null;
    question?: AssignmentQuestion;
}
export interface Submission {
    id: number;
    assignment_id: number;
    child_id: number;
    attempt_number: number;
    status: SubmissionStatus;
    started_at: string;
    submitted_at: string | null;
    graded_at?: string | null;
    released_at?: string | null;
    auto_score?: number | null;
    manual_score?: number | null;
    final_score?: number | null;
    general_feedback?: string | null;
    is_late: boolean;
    version: number;
    child?: LearningChildRef;
    answers?: SubmissionAnswer[];
    files?: LearningFile[];
}
export interface Assignment {
    id: number;
    created_by: number;
    title: string;
    description: string | null;
    type: "submission" | "quiz" | "hybrid";
    status: AssignmentStatus;
    max_score: number;
    passing_score: number;
    opens_at: string | null;
    due_at: string | null;
    time_limit_minutes: number | null;
    allowed_attempts: number;
    score_method: "highest" | "latest" | "average";
    allow_resume: boolean;
    allow_late: boolean;
    late_penalty_percent: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    allow_backtracking: boolean;
    result_release_mode: "manual" | "immediate" | "scheduled";
    results_release_at: string | null;
    show_answers: boolean;
    version: number;
    published_at?: string | null;
    released_at?: string | null;
    questions?: AssignmentQuestion[];
    targets?: AssignmentTarget[];
    recipient?: AssignmentRecipient;
    recipients?: AssignmentRecipient[];
    submissions?: Submission[];
    files?: LearningFile[];
    questions_count?: number;
    recipients_count?: number;
    submissions_count?: number;
}
export interface AssignmentInput {
    title: string;
    description: string;
    type: Assignment["type"];
    max_score: number;
    passing_score: number;
    opens_at: string | null;
    due_at: string | null;
    time_limit_minutes: number | null;
    allowed_attempts: number;
    score_method: Assignment["score_method"];
    allow_resume: boolean;
    allow_late: boolean;
    late_penalty_percent: number;
    shuffle_questions: boolean;
    shuffle_options: boolean;
    allow_backtracking: boolean;
    result_release_mode: Assignment["result_release_mode"];
    results_release_at: string | null;
    show_answers: boolean;
    version?: number;
    targets: Array<{
        catechism_class_id: number;
        child_ids: number[];
        due_at?: string | null;
        attempt_limit?: number | null;
    }>;
    questions: AssignmentQuestion[];
}
export interface Page<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}
export interface AssignmentReport {
    assignment: Pick<Assignment, "id" | "title" | "status" | "passing_score" | "score_method">;
    summary: {
        recipient_count: number; submitted_count: number; not_submitted_count: number;
        late_count: number; graded_count: number; average_score: number | null; pass_rate: number | null;
    };
    distribution: Record<string, number>;
    rows: Array<{
        recipient_id: number; child_id: number; child_code: string; child_name: string;
        class_name: string; submitted: boolean; is_late: boolean; attempt_count: number;
        score: number | null; passed: boolean | null;
    }>;
}

export type AnnouncementStatus = "draft" | "scheduled" | "sent" | "expired" | "archived" | "withdrawn";
export interface Announcement {
    id: number;
    title: string;
    body: string;
    importance: "normal" | "important" | "urgent";
    status: AnnouncementStatus;
    scheduled_at: string | null;
    sent_at: string | null;
    expires_at: string | null;
    is_pinned: boolean;
    requires_acknowledgement: boolean;
    source_type: string | null;
    source_id: number | null;
    version: number;
    targets?: Array<{
        catechism_class_id: number; child_id?: number | null; audience: "children" | "parents" | "both";
        catechism_class?: LearningClassRef; child?: LearningChildRef | null;
    }>;
    recipient_count?: number;
    unread_count?: number;
    unacknowledged_count?: number;
    is_read?: boolean;
    is_acknowledged?: boolean;
    reminded_at?: string | null;
}
export interface AnnouncementInput {
    title: string;
    body: string;
    importance: Announcement["importance"];
    scheduled_at: string | null;
    expires_at: string | null;
    is_pinned: boolean;
    requires_acknowledgement: boolean;
    version?: number;
    targets: Array<{
        catechism_class_id: number;
        audience: "children" | "parents" | "both";
        child_ids: number[];
    }>;
}

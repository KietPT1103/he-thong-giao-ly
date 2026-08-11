<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute, useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import {
    ArrowLeft, ArrowRight, BookOpen, CalendarDays, ClipboardCheck,
    Clock3, DoorOpen, GraduationCap, Search, Users,
} from "lucide-vue-next";
import { getClassChildren, getTeacherClass, getTeacherClasses } from "../api/teacher";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
import type { CatechismClass, Child } from "../types/api";

const route = useRoute();
const router = useRouter();
const classes = ref<CatechismClass[]>([]);
const selectedClass = ref<CatechismClass | null>(null);
const children = ref<Child[]>([]);
const query = ref("");
const loading = ref(true);
const error = ref("");
const classId = computed(() => {
    const value = Number(route.params.id);
    return Number.isInteger(value) && value > 0 ? value : null;
});
const isDetail = computed(() => classId.value !== null);
const filtered = computed(() =>
    classes.value.filter((item) =>
        `${item.name} ${item.code} ${item.level?.name}`
            .toLowerCase()
            .includes(query.value.trim().toLowerCase()),
    ),
);
const weekdays = ["", "Thứ hai", "Thứ ba", "Thứ tư", "Thứ năm", "Thứ sáu", "Thứ bảy", "Chủ nhật"];

async function load() {
    loading.value = true;
    error.value = "";
    try {
        if (classId.value) {
            const [classResponse, childrenResponse] = await Promise.all([
                getTeacherClass(classId.value),
                getClassChildren(classId.value),
            ]);
            selectedClass.value = classResponse.data.data;
            children.value = childrenResponse.data.data;
            return;
        }
        classes.value = (await getTeacherClasses()).data.data;
        selectedClass.value = null;
        children.value = [];
    } catch {
        error.value = isDetail.value
            ? "Không thể tải thông tin lớp học."
            : "Không thể tải danh sách lớp.";
    } finally {
        loading.value = false;
    }
}

function openClass(id: number) {
    router.push(`/teacher/classes/${id}`);
}

watch(() => route.fullPath, load, { immediate: true });
</script>

<template>
    <section class="teacher-page-stack class-information-page">
        <template v-if="!isDetail">
            <TeacherPageHeader title="Lớp của tôi" description="Theo dõi các lớp được phân công trong niên khóa hiện tại." :count="`${filtered.length} lớp`" />
            <ACard :bordered="false" class="teacher-card">
                <div class="teacher-toolbar">
                    <div class="teacher-toolbar-main">
                        <AInput v-model:value="query" allow-clear size="large" aria-label="Tìm lớp" placeholder="Tìm theo tên, mã lớp hoặc khối giáo lý">
                            <template #prefix><Search class="size-4 text-slate-400" /></template>
                        </AInput>
                    </div>
                </div>
                <div v-if="loading" class="space-y-3 p-5" aria-busy="true" aria-label="Đang tải danh sách lớp">
                    <div v-for="i in 4" :key="i" class="h-16 animate-pulse rounded-xl bg-slate-100" />
                </div>
                <div v-else-if="error" class="p-4">
                    <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
                </div>
                <div v-else-if="!filtered.length" class="teacher-empty-state">
                    <GraduationCap class="size-10 text-slate-400" />
                    <h3>Không tìm thấy lớp phù hợp</h3>
                    <p>Thử thay đổi từ khóa hoặc liên hệ quản trị viên nếu lớp chưa được phân công.</p>
                </div>
                <ul v-else class="teacher-list">
                    <li
                        v-for="item in filtered"
                        :key="item.id"
                        class="teacher-list-row class-list-row"
                        role="link"
                        tabindex="0"
                        @click="openClass(item.id)"
                        @keydown.enter="openClass(item.id)"
                    >
                        <span class="teacher-mark">{{ item.level?.name?.slice(0, 1) || "L" }}</span>
                        <div class="teacher-list-copy">
                            <b>{{ item.name }}</b>
                            <small>{{ item.code }} · {{ item.level?.name }} · {{ item.classroom?.name || "Chưa xếp phòng" }}</small>
                        </div>
                        <div class="teacher-row-actions" @click.stop>
                            <ATag color="blue"><span class="inline-flex items-center gap-1"><Users class="size-3.5" />{{ item.children_count || 0 }} thiếu nhi</span></ATag>
                            <ATooltip title="Mở thông tin lớp"><RouterLink :to="`/teacher/classes/${item.id}`" class="icon-action-button" aria-label="Mở thông tin lớp"><ArrowRight class="size-4" /></RouterLink></ATooltip>
                            <ATooltip title="Điểm danh"><RouterLink :to="`/teacher/attendance?class=${item.id}`" class="icon-action-button" aria-label="Điểm danh lớp"><ClipboardCheck class="size-4" /></RouterLink></ATooltip>
                        </div>
                    </li>
                </ul>
            </ACard>
        </template>

        <template v-else>
            <div v-if="loading" class="class-detail-loading teacher-card"><ASkeleton active :paragraph="{ rows: 9 }" /></div>
            <div v-else-if="error" class="teacher-card p-4">
                <AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load">Thử lại</AButton></template></AAlert>
            </div>
            <template v-else-if="selectedClass">
                <header class="class-detail-header">
                    <AButton class="class-back-button" aria-label="Quay lại danh sách lớp" @click="router.push('/teacher/classes')">
                        <template #icon><ArrowLeft class="size-4" /></template>
                        Danh sách lớp
                    </AButton>
                    <div class="class-identity">
                        <span class="class-identity-mark"><BookOpen /></span>
                        <div>
                            <span class="class-eyebrow">Thông tin lớp học</span>
                            <h1>{{ selectedClass.name }}</h1>
                            <p>{{ selectedClass.code }} · {{ selectedClass.level?.name || "Chưa xếp khối" }}</p>
                        </div>
                    </div>
                    <div class="class-header-actions">
                        <ATag :color="selectedClass.status === 'active' ? 'success' : 'warning'">{{ selectedClass.status === "active" ? "Đang hoạt động" : "Tạm ngưng" }}</ATag>
                        <RouterLink :to="`/teacher/attendance?class=${selectedClass.id}`"><AButton type="primary" size="large"><template #icon><ClipboardCheck class="size-4" /></template>Điểm danh lớp</AButton></RouterLink>
                    </div>
                </header>

                <div class="class-summary" aria-label="Tóm tắt lớp học">
                    <div><CalendarDays /><span><small>Niên khóa</small><b>{{ selectedClass.academic_year?.name || "Chưa cập nhật" }}</b></span></div>
                    <div><GraduationCap /><span><small>Khối giáo lý</small><b>{{ selectedClass.level?.name || "Chưa cập nhật" }}</b></span></div>
                    <div><DoorOpen /><span><small>Phòng học</small><b>{{ selectedClass.classroom?.name || "Chưa xếp phòng" }}</b></span></div>
                    <div><Users /><span><small>Sĩ số</small><b>{{ selectedClass.children_count || children.length }} thiếu nhi</b></span></div>
                </div>

                <div class="class-detail-grid">
                    <ACard :bordered="false" class="teacher-card class-roster-card">
                        <div class="class-section-heading">
                            <div><h2>Danh sách thiếu nhi</h2><p>Thông tin các em đang được xếp trong lớp.</p></div>
                            <span>{{ children.length }} em</span>
                        </div>
                        <ul v-if="children.length" class="class-roster-list">
                            <li v-for="child in children" :key="child.id">
                                <span class="student-avatar">{{ child.full_name.slice(0, 1) }}</span>
                                <div><b>{{ child.full_name }}</b><small>{{ child.code }}<template v-if="child.saint_name"> · {{ child.saint_name }}</template></small></div>
                                <ATag :color="child.status === 'studying' ? 'success' : 'default'">{{ child.status === "studying" ? "Đang học" : "Tạm nghỉ" }}</ATag>
                            </li>
                        </ul>
                        <AEmpty v-else description="Lớp chưa có thiếu nhi." class="py-10" />
                    </ACard>

                    <aside class="class-detail-aside">
                        <ACard :bordered="false" class="teacher-card class-schedule-card">
                            <div class="class-section-heading">
                                <div><h2>Lịch học định kỳ</h2><p>Khung giờ học đã được quản trị viên thiết lập.</p></div>
                                <CalendarDays class="size-5 text-primary-600" />
                            </div>
                            <ul v-if="selectedClass.schedules.length" class="class-schedule-list">
                                <li v-for="schedule in selectedClass.schedules" :key="schedule.id">
                                    <span><Clock3 /></span>
                                    <div><b>{{ weekdays[schedule.weekday] }}</b><small>{{ schedule.starts_at }}–{{ schedule.ends_at }}</small></div>
                                </li>
                            </ul>
                            <AEmpty v-else description="Chưa có lịch học định kỳ." class="py-6" />
                        </ACard>
                        <div class="class-next-step">
                            <ClipboardCheck />
                            <div><b>Sẵn sàng điểm danh?</b><p>Tạo phiên mới hoặc tiếp tục phiên điểm danh của lớp.</p></div>
                            <RouterLink :to="`/teacher/attendance?class=${selectedClass.id}`"><AButton>Đi đến điểm danh</AButton></RouterLink>
                        </div>
                    </aside>
                </div>
            </template>
        </template>
    </section>
</template>

<style scoped>
.class-list-row{cursor:pointer}.class-list-row:focus-visible{outline:2px solid #1677ff;outline-offset:-2px;background:#f8fbff}.class-detail-loading{padding:1.5rem}.class-detail-header{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:1rem;padding:.25rem 0}.class-back-button{align-self:start}.class-identity{display:flex;min-width:0;align-items:center;gap:.875rem}.class-identity-mark{display:grid;width:3rem;height:3rem;flex:none;place-items:center;border-radius:.875rem;background:#eaf2ff;color:#185fce}.class-identity-mark svg{width:1.25rem;height:1.25rem}.class-eyebrow{display:block;margin-bottom:.15rem;color:#64748b;font-size:.7rem;font-weight:650}.class-identity h1,.class-identity p{margin:0}.class-identity h1{overflow:hidden;color:#0b214d;font-size:1.35rem;font-weight:760;letter-spacing:-.025em;text-overflow:ellipsis;white-space:nowrap}.class-identity p{margin-top:.15rem;color:#64748b;font-size:.75rem}.class-header-actions{display:flex;align-items:center;gap:.75rem}.class-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));overflow:hidden;border:1px solid #dbe3ee;border-radius:.875rem;background:#fff;box-shadow:0 1px 2px rgba(15,23,42,.03)}.class-summary>div{display:flex;min-width:0;align-items:center;gap:.75rem;padding:1rem;border-left:1px solid #e2e8f0}.class-summary>div:first-child{border-left:0}.class-summary svg{width:1.05rem;height:1.05rem;flex:none;color:#185fce}.class-summary span{display:flex;min-width:0;flex-direction:column}.class-summary small{color:#64748b;font-size:.66rem}.class-summary b{overflow:hidden;margin-top:.2rem;color:#0b214d;font-size:.78rem;text-overflow:ellipsis;white-space:nowrap}.class-detail-grid{display:grid;grid-template-columns:minmax(0,1.65fr) minmax(18rem,.75fr);align-items:start;gap:1rem}.class-detail-aside{display:grid;gap:1rem}.class-section-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;padding:1rem 1.125rem;border-bottom:1px solid #e2e8f0}.class-section-heading h2,.class-section-heading p{margin:0}.class-section-heading h2{color:#0b214d;font-size:.9rem;font-weight:750}.class-section-heading p{margin-top:.2rem;color:#64748b;font-size:.7rem}.class-section-heading>span{display:inline-flex;min-height:1.5rem;align-items:center;border-radius:999px;background:#eff6ff;padding:.2rem .55rem;color:#1d4ed8;font-size:.68rem;font-weight:650;white-space:nowrap}.class-roster-list,.class-schedule-list{margin:0;padding:0;list-style:none}.class-roster-list li{display:grid;grid-template-columns:auto minmax(0,1fr) auto;align-items:center;gap:.75rem;min-height:4.25rem;padding:.75rem 1.125rem;border-bottom:1px solid #eef2f7}.class-roster-list li:last-child,.class-schedule-list li:last-child{border-bottom:0}.student-avatar{display:grid;width:2.25rem;height:2.25rem;place-items:center;border-radius:.625rem;background:#edf4ff;color:#185fce;font-size:.75rem;font-weight:750}.class-roster-list div,.class-schedule-list div{display:flex;min-width:0;flex-direction:column}.class-roster-list b,.class-roster-list small,.class-schedule-list b,.class-schedule-list small{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.class-roster-list b,.class-schedule-list b{color:#1e293b;font-size:.76rem}.class-roster-list small,.class-schedule-list small{margin-top:.16rem;color:#64748b;font-size:.67rem}.class-schedule-list li{display:flex;align-items:center;gap:.75rem;min-height:4rem;padding:.75rem 1.125rem;border-bottom:1px solid #eef2f7}.class-schedule-list li>span{display:grid;width:2.25rem;height:2.25rem;flex:none;place-items:center;border-radius:.625rem;background:#f1f5f9;color:#475569}.class-schedule-list svg{width:1rem;height:1rem}.class-next-step{display:grid;grid-template-columns:auto minmax(0,1fr);align-items:start;gap:.75rem;padding:1rem;border:1px solid #bfdbfe;border-radius:.875rem;background:#f8fbff}.class-next-step>svg{width:1.1rem;height:1.1rem;margin-top:.1rem;color:#185fce}.class-next-step b{color:#0b214d;font-size:.76rem}.class-next-step p{margin:.2rem 0 .75rem;color:#64748b;font-size:.68rem;line-height:1.55}.class-next-step a{grid-column:2}.class-next-step .ant-btn{width:100%}@media(max-width:1023px){.class-detail-header{grid-template-columns:auto minmax(0,1fr)}.class-header-actions{grid-column:1/-1;justify-content:flex-end}.class-summary{grid-template-columns:1fr 1fr}.class-summary>div:nth-child(3){border-left:0}.class-summary>div:nth-child(n+3){border-top:1px solid #e2e8f0}.class-detail-grid{grid-template-columns:1fr}.class-detail-aside{grid-template-columns:1fr 1fr}}@media(max-width:639px){.class-detail-header{grid-template-columns:1fr}.class-back-button{justify-self:start}.class-identity h1{font-size:1.15rem}.class-header-actions{grid-column:auto;justify-content:space-between}.class-header-actions a{flex:1}.class-header-actions .ant-btn{width:100%}.class-summary{grid-template-columns:1fr}.class-summary>div{border-top:1px solid #e2e8f0;border-left:0}.class-summary>div:first-child{border-top:0}.class-detail-aside{grid-template-columns:1fr}.class-section-heading{padding:1rem}.class-roster-list li{padding:.75rem 1rem}.teacher-list-row{grid-template-columns:auto minmax(0,1fr)}.teacher-row-actions{grid-column:2;justify-content:flex-start}}
</style>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from "vue";
import { useRouter } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import AEmpty from "ant-design-vue/es/empty";
import AInput from "ant-design-vue/es/input";
import APagination from "ant-design-vue/es/pagination";
import ASelect from "ant-design-vue/es/select";
import ASkeleton from "ant-design-vue/es/skeleton";
import ATag from "ant-design-vue/es/tag";
import ATooltip from "ant-design-vue/es/tooltip";
import { ArrowRight, BookOpen, CalendarDays, GraduationCap, Search, UsersRound } from "lucide-vue-next";
import { getTeacherChildren, type TeacherChildRow, type TeacherChildrenMeta } from "../api/teacher";
import TeacherPageHeader from "../components/TeacherPageHeader.vue";
import UserAvatar from "../components/UserAvatar.vue";

const router = useRouter();
const emptyMeta = (): TeacherChildrenMeta => ({
    current_page: 1, last_page: 1, per_page: 10, total: 0,
    summary: { total_children: 0, studying_children: 0, class_count: 0, next_schedule: null },
    filters: { classes: [] },
});
const rows = ref<TeacherChildRow[]>([]);
const query = ref("");
const classId = ref<number>();
const status = ref<string>();
const loading = ref(true);
const error = ref("");
const meta = ref<TeacherChildrenMeta>(emptyMeta());
let searchTimer: ReturnType<typeof setTimeout> | undefined;
let requestId = 0;

const classOptions = computed(() => meta.value.filters.classes.map((item) => ({ value: item.id, label: `${item.name} · ${item.code}` })));
const studyingRate = computed(() => meta.value.summary.total_children ? Math.round(meta.value.summary.studying_children / meta.value.summary.total_children * 100) : 0);
const classNames = computed(() => meta.value.filters.classes.map((item) => item.name).join(", "));
const weekdays = ["", "Thứ hai", "Thứ ba", "Thứ tư", "Thứ năm", "Thứ sáu", "Thứ bảy", "Chủ nhật"];
const firstItem = computed(() => meta.value.total ? (meta.value.current_page - 1) * meta.value.per_page + 1 : 0);
const lastItem = computed(() => Math.min(firstItem.value + rows.value.length - 1, meta.value.total));

async function load(page = 1) {
    const currentRequest = ++requestId;
    loading.value = true;
    error.value = "";
    try {
        const response = await getTeacherChildren({ search: query.value.trim() || undefined, class_id: classId.value, status: status.value, page });
        if (currentRequest !== requestId) return;
        rows.value = response.data.data;
        meta.value = response.data.meta as unknown as TeacherChildrenMeta;
    } catch {
        if (currentRequest === requestId) error.value = "Không thể tải danh sách thiếu nhi.";
    } finally {
        if (currentRequest === requestId) loading.value = false;
    }
}

function submitSearch() {
    clearTimeout(searchTimer);
    void load(1);
}

function statusLabel(value: string) {
    if (value === "studying") return "Đang học";
    if (value === "paused" || value === "inactive") return "Tạm nghỉ";
    return "Đã hoàn tất";
}

watch(query, () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => void load(1), 350);
});
onMounted(load);
</script>

<template>
    <section class="children-directory-page">
        <TeacherPageHeader title="Thiếu nhi" description="Danh sách thiếu nhi thuộc các lớp bạn đang phụ trách." :count="`${meta.summary.total_children} thiếu nhi`" />

        <div class="children-summary" aria-label="Tóm tắt danh sách thiếu nhi">
            <article class="summary-item"><span><UsersRound /></span><div><small>Tổng thiếu nhi</small><strong>{{ meta.summary.total_children }}</strong><p>Trong {{ meta.summary.class_count }} lớp</p></div></article>
            <article class="summary-item"><span><GraduationCap /></span><div><small>Đang học</small><strong>{{ meta.summary.studying_children }}</strong><p>{{ studyingRate }}% danh sách</p></div></article>
            <article class="summary-item"><span><BookOpen /></span><div><small>Lớp phụ trách</small><strong>{{ meta.summary.class_count }}</strong><p :title="classNames">{{ classNames || "Chưa có lớp" }}</p></div></article>
            <article class="summary-item summary-item--schedule"><span><CalendarDays /></span><div v-if="meta.summary.next_schedule"><small>Buổi học sắp tới</small><strong>{{ weekdays[meta.summary.next_schedule.weekday] }}</strong><p>{{ meta.summary.next_schedule.starts_at }} – {{ meta.summary.next_schedule.ends_at }} · {{ meta.summary.next_schedule.class_name }}</p></div><div v-else><small>Buổi học sắp tới</small><strong>Chưa có lịch</strong><p>Các lớp chưa thiết lập lịch định kỳ</p></div></article>
        </div>

        <section class="children-table-card" aria-label="Danh sách học viên">
            <div class="children-toolbar">
                <AInput v-model:value="query" allow-clear size="large" aria-label="Tìm thiếu nhi" placeholder="Tìm theo tên, mã học viên hoặc tên thánh" @press-enter="submitSearch"><template #prefix><Search class="size-4 text-slate-400" /></template></AInput>
                <ASelect v-model:value="classId" allow-clear size="large" aria-label="Lọc theo lớp" placeholder="Tất cả lớp" :options="classOptions" @change="load(1)" />
                <ASelect v-model:value="status" allow-clear size="large" aria-label="Lọc theo trạng thái" placeholder="Tất cả trạng thái" :options="[{value:'studying',label:'Đang học'},{value:'paused',label:'Tạm nghỉ'},{value:'graduated',label:'Đã hoàn tất'}]" @change="load(1)" />
            </div>

            <div v-if="error" class="children-error"><AAlert type="error" show-icon :message="error"><template #action><AButton size="small" @click="load()">Thử lại</AButton></template></AAlert></div>
            <div v-else class="children-table" role="table" aria-label="Danh sách thiếu nhi" :aria-busy="loading">
                <div class="children-table-head" role="row"><span role="columnheader">#</span><span role="columnheader">Họ và tên</span><span role="columnheader">Mã học viên · Tên thánh</span><span role="columnheader">Lớp</span><span role="columnheader">Trạng thái</span><span role="columnheader">Mở lớp</span></div>
                <div v-if="loading" class="children-loading"><ASkeleton active :paragraph="{ rows: 8 }" /></div>
                <div v-else-if="rows.length" class="children-table-body">
                    <article v-for="(student, index) in rows" :key="`${student.id}-${student.class.id}`" class="child-row" role="row">
                        <span class="child-number" role="cell">{{ firstItem + index }}</span>
                        <div class="child-person" role="cell"><UserAvatar :name="student.full_name" :avatar-url="student.avatar_url" /><b>{{ student.full_name }}</b></div>
                        <div class="child-identity" data-label="Mã · Tên thánh" role="cell"><span>{{ student.code }} · {{ student.saint_name || "Chưa cập nhật" }}</span></div>
                        <span class="child-class" data-label="Lớp" role="cell"><ATag color="blue">{{ student.class.name }}</ATag></span>
                        <span class="child-status" data-label="Trạng thái" role="cell"><ATag :color="student.status === 'studying' ? 'success' : 'default'">{{ statusLabel(student.status) }}</ATag></span>
                        <span class="child-action" role="cell"><ATooltip title="Mở chi tiết lớp"><AButton type="text" aria-label="Mở chi tiết lớp" @click="router.push(`/teacher/classes/${student.class.id}`)"><template #icon><ArrowRight class="size-4" /></template></AButton></ATooltip></span>
                    </article>
                </div>
                <AEmpty v-else description="Không có thiếu nhi phù hợp." class="py-12" />
            </div>

            <footer v-if="!loading && meta.total" class="children-footer"><span>Hiển thị {{ firstItem }}–{{ lastItem }} trong tổng số {{ meta.total }} thiếu nhi</span><APagination :current="meta.current_page" :page-size="10" :total="meta.total" :show-size-changer="false" responsive @change="load" /></footer>
        </section>
    </section>
</template>

<style scoped>
.children-directory-page{display:grid;width:100%;max-width:1600px;margin-inline:auto;gap:20px}.children-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:12px}.summary-item{display:grid;min-width:0;min-height:108px;grid-template-columns:auto minmax(0,1fr);align-items:start;gap:12px;padding:18px;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.05)}.summary-item>span{display:grid;width:42px;height:42px;place-items:center;border-radius:11px;background:#edf4ff;color:#1d63d6}.summary-item svg{width:21px;height:21px}.summary-item div{min-width:0}.summary-item small,.summary-item strong,.summary-item p{display:block;margin:0}.summary-item small{color:#64748b;font-size:11px;line-height:1.4}.summary-item strong{margin-top:2px;color:#10234b;font-size:21px;font-weight:760;line-height:1.25;font-variant-numeric:tabular-nums}.summary-item p{overflow:hidden;margin-top:5px;color:#64748b;font-size:10px;line-height:1.45;text-overflow:ellipsis;white-space:nowrap}.summary-item--schedule strong{font-size:14px}.children-table-card{overflow:hidden;border:1px solid #e2e8f0;border-radius:14px;background:#fff;box-shadow:0 4px 14px rgba(15,23,42,.05)}.children-toolbar{display:grid;grid-template-columns:minmax(260px,1fr) 190px 190px;gap:10px;padding:14px;border-bottom:1px solid #e2e8f0}.children-toolbar :deep(.ant-input-affix-wrapper),.children-toolbar :deep(.ant-select-selector){min-height:42px;border-radius:10px!important}.children-toolbar :deep(.ant-select){width:100%}.children-table{min-width:0}.children-table-head,.child-row{display:grid;grid-template-columns:42px minmax(170px,1.2fr) minmax(190px,1.35fr) minmax(110px,.65fr) 100px 54px;align-items:center;gap:12px;padding-inline:16px}.children-table-head{min-height:44px;border-bottom:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:10px;font-weight:700}.child-row{min-height:64px;border-bottom:1px solid #edf1f6;transition:background-color 160ms ease}.child-row:hover{background:#fbfdff}.child-row:last-child{border-bottom:0}.child-number{display:grid;width:28px;height:28px;place-items:center;border-radius:50%;background:#f1f5f9;color:#64748b;font-size:10px;font-variant-numeric:tabular-nums}.child-person{display:flex;min-width:0;align-items:center;gap:10px}.child-person b{overflow:hidden;color:#15284e;font-size:12px;font-weight:700;text-overflow:ellipsis;white-space:nowrap}.child-identity{display:flex;min-width:0;align-items:center;overflow:hidden;color:#52627c;font-size:11px}.child-identity span{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.child-class :deep(.ant-tag),.child-status :deep(.ant-tag){max-width:100%;overflow:hidden;margin:0;text-overflow:ellipsis;white-space:nowrap}.child-action{display:flex;justify-content:flex-end}.child-action :deep(.ant-btn){display:grid;width:36px;height:36px;place-items:center;border-radius:9px}.children-loading{padding:18px}.children-error{padding:14px}.children-footer{display:flex;min-height:62px;align-items:center;justify-content:space-between;gap:16px;padding:10px 16px;border-top:1px solid #e2e8f0;color:#64748b;font-size:11px}.children-footer :deep(.ant-pagination-item),.children-footer :deep(.ant-pagination-prev),.children-footer :deep(.ant-pagination-next){border-radius:8px}
@media(max-width:1199px){.children-summary{grid-template-columns:repeat(2,minmax(0,1fr))}.children-table{overflow-x:auto}.children-table-head,.children-table-body{min-width:850px}}
@media(max-width:767px){.children-directory-page{gap:16px}.children-toolbar{grid-template-columns:1fr 1fr}.children-toolbar>:first-child{grid-column:1/-1}.children-footer{align-items:flex-start;flex-direction:column}.children-footer :deep(.ant-pagination){align-self:flex-end}}
@media(max-width:639px){.children-summary{grid-template-columns:1fr 1fr;gap:8px}.summary-item{min-height:116px;grid-template-columns:1fr;gap:8px;padding:14px}.summary-item>span{width:36px;height:36px}.summary-item svg{width:18px;height:18px}.summary-item strong{font-size:18px}.children-toolbar{grid-template-columns:1fr;padding:12px}.children-toolbar>:first-child{grid-column:auto}.children-table{overflow:visible;padding:10px;background:#f8fafc}.children-table-head{display:none}.children-table-body{display:grid;min-width:0;gap:10px}.child-row{position:relative;display:grid;min-height:0;grid-template-columns:minmax(0,1fr) auto;gap:10px 12px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#fff}.child-row:last-child{border-bottom:1px solid #e2e8f0}.child-number{position:absolute;top:14px;right:14px}.child-person{grid-column:1/-1;padding-right:38px}.child-identity,.child-class,.child-status{display:grid;grid-column:1/-1;grid-template-columns:92px minmax(0,1fr);gap:8px;overflow:visible;white-space:normal}.child-identity::before,.child-class::before,.child-status::before{content:attr(data-label);color:#64748b;font-size:10px;font-weight:650}.child-identity span{white-space:normal}.child-action{grid-column:1/-1}.child-action :deep(.ant-btn){width:100%;border:1px solid #dbe3ee}.children-footer :deep(.ant-pagination){align-self:stretch;text-align:center}}
</style>

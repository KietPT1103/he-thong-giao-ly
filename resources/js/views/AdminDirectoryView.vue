<script setup lang="ts">
import { computed, ref, watch } from "vue";
import { useRoute } from "vue-router";
import AAlert from "ant-design-vue/es/alert";
import AButton from "ant-design-vue/es/button";
import ACard from "ant-design-vue/es/card";
import AEmpty from "ant-design-vue/es/empty";
import AInputSearch from "ant-design-vue/es/input/Search";
import APagination from "ant-design-vue/es/pagination";
import ASpace from "ant-design-vue/es/space";
import ATable from "ant-design-vue/es/table";
import ATag from "ant-design-vue/es/tag";
import ATypographyText from "ant-design-vue/es/typography/Text";
import type { ColumnsType } from "ant-design-vue/es/table/interface";
import { RefreshCw } from "lucide-vue-next";
import {
    getAdminDirectory,
    type AdminListItem,
    type AdminListMeta,
} from "../api/admin";

const route = useRoute();
const items = ref<AdminListItem[]>([]);
const meta = ref<AdminListMeta>({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const search = ref("");
const loading = ref(true);
const error = ref("");
const module = computed(() => String(route.meta.module || ""));
const columns: ColumnsType<AdminListItem> = [
    { title: "Thông tin", key: "name", dataIndex: "name" },
    { title: "Liên hệ / Mã", key: "secondary", dataIndex: "secondary", responsive: ["sm"] },
    { title: "Phân loại", key: "details", dataIndex: "details", responsive: ["lg"] },
    { title: "Trạng thái", key: "status", dataIndex: "status", width: 116 },
];

async function load(page = 1) {
    loading.value = true;
    error.value = "";
    try {
        const response = await getAdminDirectory(module.value, {
            search: search.value || undefined,
            page,
        });
        items.value = response.data.data;
        meta.value = response.data.meta as unknown as AdminListMeta;
    } catch {
        error.value = "Không thể tải dữ liệu. Vui lòng thử lại.";
    } finally {
        loading.value = false;
    }
}

watch(module, () => {
    search.value = "";
    load(1);
}, { immediate: true });
</script>

<template>
    <ASpace direction="vertical" :size="20" class="admin-page-stack">
        <AAlert v-if="error" type="error" show-icon :message="error">
            <template #action><AButton size="small" @click="load(1)">Thử lại</AButton></template>
        </AAlert>

        <ACard :bordered="false" class="admin-card admin-table-card">
            <div class="directory-toolbar">
                <div class="directory-toolbar-main">
                    <AInputSearch
                        v-model:value="search"
                        allow-clear
                        class="admin-search-input"
                        :placeholder="`Tìm trong ${String(route.meta.title).toLowerCase()}...`"
                        enter-button="Tìm kiếm"
                        @search="load(1)"
                    />
                    <AButton class="admin-refresh-button" :loading="loading" aria-label="Tải lại dữ liệu" title="Tải lại dữ liệu" @click="load(meta.current_page)">
                        <template #icon><RefreshCw class="size-4" /></template>
                    </AButton>
                </div>
                <ATag class="record-count">{{ meta.total.toLocaleString("vi-VN") }} bản ghi</ATag>
            </div>
            <ATable
                :columns="columns"
                :data-source="items"
                :loading="loading"
                :pagination="false"
                row-key="id"
                size="middle"
            >
                <template #emptyText>
                    <AEmpty description="Không tìm thấy bản ghi phù hợp." />
                </template>
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'name'">
                        <div class="flex min-w-0 items-center gap-3">
                            <span class="directory-avatar">{{ record.name.slice(0, 1).toUpperCase() }}</span>
                            <div class="min-w-0">
                                <ATypographyText strong>{{ record.name }}</ATypographyText>
                                <div><ATypographyText type="secondary" class="text-xs">#{{ record.id }}</ATypographyText></div>
                            </div>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'secondary'">
                        <div class="min-w-0">
                            <div class="truncate">{{ record.secondary || "—" }}</div>
                            <ATypographyText type="secondary" class="text-xs">{{ record.code || "—" }}</ATypographyText>
                        </div>
                    </template>
                    <template v-else-if="column.key === 'details'">
                        <ASpace wrap :size="[4, 4]">
                            <ATag v-for="detail in record.details" :key="detail">{{ detail }}</ATag>
                        </ASpace>
                    </template>
                    <template v-else-if="column.key === 'status'">
                        <ATag color="success">{{ record.status }}</ATag>
                    </template>
                </template>
            </ATable>
            <div v-if="meta.total > meta.per_page" class="flex justify-end border-t border-slate-100 px-4 py-3">
                <APagination
                    :current="meta.current_page"
                    :page-size="meta.per_page"
                    :total="meta.total"
                    :show-size-changer="false"
                    responsive
                    @change="load"
                />
            </div>
        </ACard>
    </ASpace>
</template>

<script setup lang="ts">
import { ref, onMounted } from "vue";
import { message } from "@/utils/message";
import { getPayQrcodes, deletePayQrcode } from "@/api/admin/qrcode";
import { ElMessageBox } from "element-plus";
import {
  AdminMobileActions,
  AdminMobileField,
  AdminMobileList,
  AdminMobileRecordCard,
  type AdminMobileMoreAction
} from "@/components/admin/mobile";

const props = defineProps<{
  type: 1 | 2;
  title: string;
  channelId?: number;
}>();

const loading = ref(false);
const list = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const limit = 10;
const previewImageUrl = ref("");

const getQrSourceUrl = (row: any) => String(row.qrcode || row.pay_url || "");

const getQrImageUrl = (row: any) => {
  const sourceUrl = getQrSourceUrl(row);
  if (!sourceUrl) return "";
  return row.qrcode
    ? sourceUrl
    : `/enQrcode?url=${encodeURIComponent(sourceUrl)}`;
};

const getQrTitle = (row: any) => (row.price ? `金额 ${row.price}` : "二维码");

const getQrActions = (_row: any): AdminMobileMoreAction[] => [
  {
    label: "删除",
    command: "delete",
    type: "danger"
  }
];

const openQrPreview = (row: any) => {
  const imageUrl = getQrImageUrl(row);
  if (imageUrl) {
    previewImageUrl.value = imageUrl;
  }
};

const handleQrMore = (row: any, command: string) => {
  if (command === "delete") return handleDelete(row);
};

const loadList = async () => {
  try {
    loading.value = true;
    const res = await getPayQrcodes({
      type: props.type,
      channelId: props.channelId,
      page: page.value,
      limit
    });
    if (res.code === 1) {
      list.value = res.data;
      total.value = res.count;
    }
  } finally {
    loading.value = false;
  }
};

const handleDelete = async (row: any) => {
  await ElMessageBox.confirm(`确认删除该二维码？`, "提示", {
    type: "warning"
  });
  const res = await deletePayQrcode({ id: row.id });
  if (res.code === 1) {
    message("删除成功", { type: "success" });
    loadList();
  } else {
    message(res.msg || "删除失败", { type: "error" });
  }
};

onMounted(loadList);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header
        ><span>{{ title }}</span></template
      >

      <el-table
        v-loading="loading"
        :data="list"
        border
        class="qr-desktop-table admin-desktop-only"
      >
        <el-table-column label="二维码" width="220">
          <template #default="{ row }">
            <el-image
              :src="`/enQrcode?url=${encodeURIComponent(row.pay_url)}`"
              style="width: 180px; height: 180px"
              fit="contain"
              :preview-src-list="[
                `/enQrcode?url=${encodeURIComponent(row.pay_url)}`
              ]"
            />
          </template>
        </el-table-column>
        <el-table-column label="金额" prop="price" width="100" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button
              type="danger"
              size="small"
              text
              @click="handleDelete(row)"
            >
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <AdminMobileList
        class="qr-mobile-list admin-mobile-only"
        :loading="loading"
        :empty="list.length === 0"
        empty-text="暂无二维码数据"
      >
        <AdminMobileRecordCard
          v-for="row in list"
          :key="row.id || row.pay_url || row.qrcode"
          :title="getQrTitle(row)"
        >
          <template #status>
            <el-tag type="info">二维码</el-tag>
          </template>
          <div class="qr-mobile-preview">
            <el-image
              :src="getQrImageUrl(row)"
              fit="contain"
              :preview-src-list="[getQrImageUrl(row)]"
            />
          </div>
          <AdminMobileField label="金额" :value="row.price || '未设置'" />
          <AdminMobileField label="二维码地址" truncate>
            {{ getQrSourceUrl(row) || "无" }}
          </AdminMobileField>
          <template #actions>
            <AdminMobileActions
              primary-text="预览"
              :primary-disabled="!getQrImageUrl(row)"
              :more-actions="getQrActions(row)"
              @primary="openQrPreview(row)"
              @more="command => handleQrMore(row, command)"
            />
          </template>
        </AdminMobileRecordCard>
      </AdminMobileList>

      <el-pagination
        v-if="total > limit"
        v-model:current-page="page"
        :page-size="limit"
        :total="total"
        layout="prev, pager, next"
        class="mt-4 justify-end"
        @current-change="loadList"
      />
    </el-card>
    <el-image-viewer
      v-if="previewImageUrl"
      :url-list="[previewImageUrl]"
      @close="previewImageUrl = ''"
    />
  </div>
</template>

<style scoped>
.qr-mobile-preview {
  display: flex;
  justify-content: center;
  padding: 8px 0 4px;
}

.qr-mobile-preview :deep(.el-image) {
  width: min(100%, 180px);
  aspect-ratio: 1 / 1;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 8px;
  background: var(--el-fill-color-lighter);
}
</style>

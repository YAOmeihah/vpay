<script setup lang="ts">
import { ref, onMounted } from "vue";
import { message } from "@/utils/message";
import { getPayQrcodes, deletePayQrcode } from "@/api/admin/qrcode";
import { ElMessageBox } from "element-plus";

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
      <template #header><span>{{ title }}</span></template>

      <el-table :data="list" v-loading="loading" border>
        <el-table-column label="二维码" width="220">
          <template #default="{ row }">
            <el-image
              :src="`/enQrcode?url=${encodeURIComponent(row.pay_url)}`"
              style="width: 180px; height: 180px"
              fit="contain"
              :preview-src-list="[`/enQrcode?url=${encodeURIComponent(row.pay_url)}`]"
            />
          </template>
        </el-table-column>
        <el-table-column label="金额" prop="price" width="100" />
        <el-table-column label="操作" width="100" fixed="right">
          <template #default="{ row }">
            <el-button type="danger" size="small" text @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

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
  </div>
</template>

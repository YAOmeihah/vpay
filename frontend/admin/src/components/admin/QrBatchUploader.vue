<script setup lang="ts">
import { ref, reactive } from "vue";
import { message } from "@/utils/message";
import { addPayQrcode } from "@/api/admin/qrcode";
import type { UploadFile } from "element-plus";
import { decodeQrFromFile } from "@/utils/qrcode";
import { isValidMoneyInput } from "@/utils/adminLegacy";

const props = defineProps<{
  type: 1 | 2;
  title: string;
  scanHint: string;
  channelId?: number;
}>();

interface QrRow {
  file: File;
  previewUrl: string;
  decodedUrl: string;
  price: string;
  status: "pending" | "ok" | "error";
  errMsg: string;
}

const rows = ref<QrRow[]>([]);
const submitting = ref(false);

const handleFileChange = async (uploadFile: UploadFile) => {
  const file = uploadFile.raw!;
  const previewUrl = URL.createObjectURL(file);
  const decodedUrl = await decodeQrFromFile(file);
  rows.value.push(
    reactive({
      file,
      previewUrl,
      decodedUrl,
      price: "",
      status: "pending",
      errMsg: ""
    })
  );
};

const removeRow = (index: number) => {
  URL.revokeObjectURL(rows.value[index].previewUrl);
  rows.value.splice(index, 1);
};

const submitAll = async () => {
  const pending = rows.value.filter(r => r.status === "pending");
  if (!pending.length) return;
  submitting.value = true;
  for (const row of pending) {
    if (!row.decodedUrl) {
      row.status = "error";
      row.errMsg = "未能识别二维码，请手动填写地址";
      continue;
    }
    if (!row.price) {
      row.status = "error";
      row.errMsg = "请填写金额";
      continue;
    }
    if (!isValidMoneyInput(row.price)) {
      row.status = "error";
      row.errMsg = "金额格式不正确";
      continue;
    }
    try {
      const res = await addPayQrcode({
        type: props.type,
        pay_url: row.decodedUrl,
        price: row.price,
        channelId: props.channelId
      });
      if (res.code === 1) {
        row.status = "ok";
      } else {
        row.status = "error";
        row.errMsg = res.msg || "保存失败";
      }
    } catch {
      row.status = "error";
      row.errMsg = "请求失败";
    }
  }
  submitting.value = false;
  const okCount = rows.value.filter(r => r.status === "ok").length;
  if (okCount) {
    message(`成功上传 ${okCount} 条`, { type: "success" });
    rows.value = rows.value.filter(row => row.status !== "ok");
  }
};
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header>
        <span>{{ title }}</span>
      </template>

      <el-alert :title="scanHint" type="info" :closable="false" class="mb-4" />

      <el-upload
        multiple
        :auto-upload="false"
        accept="image/*"
        :show-file-list="false"
        :on-change="handleFileChange"
      >
        <el-button type="primary">选择图片</el-button>
      </el-upload>

      <el-table v-if="rows.length" :data="rows" class="mt-4" border>
        <el-table-column label="预览" width="100">
          <template #default="{ row }">
            <el-image
              :src="row.previewUrl"
              style="width: 64px; height: 64px"
              fit="contain"
            />
          </template>
        </el-table-column>
        <el-table-column label="二维码地址" min-width="200">
          <template #default="{ row }">
            <el-input
              v-model="row.decodedUrl"
              placeholder="未识别，请手动填写"
              size="small"
            />
          </template>
        </el-table-column>
        <el-table-column label="金额" width="140">
          <template #default="{ row }">
            <el-input
              v-model="row.price"
              placeholder="如 0.01"
              size="small"
            />
          </template>
        </el-table-column>
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag v-if="row.status === 'ok'" type="success">成功</el-tag>
            <el-tooltip v-else-if="row.status === 'error'" :content="row.errMsg">
              <el-tag type="danger">失败</el-tag>
            </el-tooltip>
            <el-tag v-else type="info">待提交</el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="80">
          <template #default="{ $index }">
            <el-button
              type="danger"
              size="small"
              text
              @click="removeRow($index)"
            >删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <div v-if="rows.length" class="mt-4">
        <el-button
          type="primary"
          :loading="submitting"
          @click="submitAll"
        >全部提交</el-button>
      </div>
    </el-card>
  </div>
</template>

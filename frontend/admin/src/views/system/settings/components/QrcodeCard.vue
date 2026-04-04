<script setup lang="ts">
import { ref } from "vue";
import type { FormInstance, FormRules, UploadFile } from "element-plus";

import type { QrcodeSection } from "../sectionState";

const props = defineProps<{
  model: QrcodeSection;
  wxpayPreviewUrl: string;
  zfbpayPreviewUrl: string;
  loading: boolean;
}>();

const emit = defineEmits<{
  save: [];
  upload: [field: "wxpay" | "zfbpay", file: UploadFile];
}>();

const formRef = ref<FormInstance>();

const rules: FormRules<QrcodeSection> = {
  wxpay: [{ required: true, message: "请提供微信收款码内容", trigger: "blur" }],
  zfbpay: [{ required: true, message: "请提供支付宝收款码内容", trigger: "blur" }]
};

const handleSave = async () => {
  if (!formRef.value) return;
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;
  emit("save");
};

const handleUpload = (field: "wxpay" | "zfbpay", file: UploadFile) => {
  emit("upload", field, file);
};
</script>

<template>
  <el-card shadow="hover">
    <template #header>
      <div class="space-y-1">
        <div class="text-base font-medium">默认收款码</div>
        <div class="text-sm text-gray-500">上传无金额收款码，解析成功后再保存；也可手动粘贴二维码内容。</div>
      </div>
    </template>

    <el-form ref="formRef" :model="model" :rules="rules" label-width="120px">
      <el-form-item label="微信收款码" prop="wxpay">
        <div class="w-full space-y-3">
          <el-input
            v-model="model.wxpay"
            type="textarea"
            :rows="3"
            placeholder="可手动填写微信收款码内容"
          />
          <el-upload
            :auto-upload="false"
            :show-file-list="false"
            accept="image/*"
            :on-change="file => handleUpload('wxpay', file)"
          >
            <el-button type="primary" plain>上传微信收款二维码</el-button>
          </el-upload>
          <el-image
            v-if="props.wxpayPreviewUrl"
            :src="props.wxpayPreviewUrl"
            class="qr-preview"
            fit="contain"
          />
        </div>
      </el-form-item>

      <el-form-item label="支付宝收款码" prop="zfbpay">
        <div class="w-full space-y-3">
          <el-input
            v-model="model.zfbpay"
            type="textarea"
            :rows="3"
            placeholder="可手动填写支付宝收款码内容"
          />
          <el-upload
            :auto-upload="false"
            :show-file-list="false"
            accept="image/*"
            :on-change="file => handleUpload('zfbpay', file)"
          >
            <el-button type="primary" plain>上传支付宝收款二维码</el-button>
          </el-upload>
          <el-image
            v-if="props.zfbpayPreviewUrl"
            :src="props.zfbpayPreviewUrl"
            class="qr-preview"
            fit="contain"
          />
        </div>
      </el-form-item>

      <div class="flex justify-end">
        <el-button type="primary" :loading="loading" @click="handleSave">
          保存收款码
        </el-button>
      </div>
    </el-form>
  </el-card>
</template>

<style scoped>
.qr-preview {
  width: 200px;
  height: 200px;
}
</style>

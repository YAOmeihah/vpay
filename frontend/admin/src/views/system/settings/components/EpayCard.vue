<script setup lang="ts">
import { ref } from "vue";
import type { FormInstance, FormRules } from "element-plus";

import type { EpaySection } from "../sectionState";

const props = defineProps<{
  model: EpaySection;
  loading: boolean;
}>();

const emit = defineEmits<{
  save: [];
  generateMd5: [];
  generateRsa: [];
}>();

const formRef = ref<FormInstance>();

const validatePid = (_rule: unknown, value: string, callback: (error?: Error) => void) => {
  if (props.model.epay_enabled !== "1") {
    callback();
    return;
  }

  if (String(value ?? "").trim() === "") {
    callback(new Error("启用易支付兼容后必须填写商户 ID"));
    return;
  }

  callback();
};

const rules: FormRules<EpaySection> = {
  epay_pid: [{ validator: validatePid, trigger: "blur" }]
};

const handleSave = async () => {
  if (!formRef.value) return;
  const valid = await formRef.value.validate().catch(() => false);
  if (!valid) return;
  emit("save");
};
</script>

<template>
  <el-card shadow="hover">
    <template #header>
      <div class="space-y-1">
        <div class="text-base font-medium">易支付兼容</div>
        <div class="text-sm text-gray-500">敏感密钥留空表示保持原值不变，生成后需要单独保存才能持久化。</div>
      </div>
    </template>

    <el-form ref="formRef" :model="model" :rules="rules" label-width="120px">
      <el-form-item label="启用状态">
        <el-select v-model="model.epay_enabled" class="w-full">
          <el-option label="关闭" value="0" />
          <el-option label="开启" value="1" />
        </el-select>
      </el-form-item>

      <el-form-item label="商户 ID" prop="epay_pid">
        <el-input v-model="model.epay_pid" placeholder="启用时必填" />
      </el-form-item>

      <el-form-item label="商户名称">
        <el-input v-model="model.epay_name" placeholder="默认：订单支付" />
      </el-form-item>

      <el-form-item label="MD5 密钥(v1)">
        <div class="w-full flex gap-2">
          <el-input
            v-model="model.epay_key"
            placeholder="留空则不更新，用于 v1 MD5 签名"
          />
          <el-button @click="emit('generateMd5')">自动生成</el-button>
        </div>
      </el-form-item>

      <el-form-item label="RSA 密钥(v2)">
        <el-button :loading="loading" @click="emit('generateRsa')">
          自动生成 RSA 密钥对
        </el-button>
      </el-form-item>

      <el-form-item label="RSA 私钥">
        <el-input
          v-model="model.epay_private_key"
          type="textarea"
          :rows="4"
          placeholder="留空则不更新，PEM 格式 RSA 私钥"
        />
      </el-form-item>

      <el-form-item label="RSA 公钥">
        <el-input
          v-model="model.epay_public_key"
          type="textarea"
          :rows="4"
          placeholder="PEM 格式 RSA 公钥，用于 v2 验签"
        />
      </el-form-item>

      <div class="flex justify-end">
        <el-button type="primary" :loading="loading" @click="handleSave">
          保存易支付配置
        </el-button>
      </div>
    </el-form>
  </el-card>
</template>

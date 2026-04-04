<script setup lang="ts">
import { ref } from "vue";
import type { FormInstance, FormRules } from "element-plus";

import type { SecuritySection } from "../sectionState";

const props = defineProps<{
  model: SecuritySection;
  loading: boolean;
}>();

const emit = defineEmits<{
  save: [];
}>();

const formRef = ref<FormInstance>();

const validateConfirmPassword = (_rule: unknown, value: string, callback: (error?: Error) => void) => {
  if (props.model.newPassword.trim() === "" && value.trim() === "") {
    callback();
    return;
  }

  if (value.trim() === "") {
    callback(new Error("请再次输入新密码"));
    return;
  }

  if (value !== props.model.newPassword) {
    callback(new Error("两次输入的密码不一致"));
    return;
  }

  callback();
};

const validateNewPassword = (_rule: unknown, value: string, callback: (error?: Error) => void) => {
  if (value.trim() === "" && props.model.confirmPassword.trim() === "") {
    callback();
    return;
  }

  if (value.trim() === "") {
    callback(new Error("请输入新密码"));
    return;
  }

  callback();
};

const rules: FormRules<SecuritySection> = {
  user: [{ required: true, message: "请输入管理员账号", trigger: "blur" }],
  newPassword: [{ validator: validateNewPassword, trigger: "blur" }],
  confirmPassword: [{ validator: validateConfirmPassword, trigger: "blur" }]
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
        <div class="text-base font-medium">管理员安全</div>
        <div class="text-sm text-gray-500">账号修改与密码更新互不影响其它支付配置。</div>
      </div>
    </template>

    <el-form ref="formRef" :model="model" :rules="rules" label-width="120px">
      <el-form-item label="后台账号" prop="user">
        <el-input v-model="model.user" placeholder="请输入管理员账号" />
      </el-form-item>

      <el-form-item label="新密码" prop="newPassword">
        <el-input
          v-model="model.newPassword"
          type="password"
          show-password
          placeholder="留空表示不修改密码"
          autocomplete="new-password"
        />
      </el-form-item>

      <el-form-item label="确认密码" prop="confirmPassword">
        <el-input
          v-model="model.confirmPassword"
          type="password"
          show-password
          placeholder="再次输入新密码"
          autocomplete="new-password"
        />
      </el-form-item>

      <el-alert
        class="mb-4"
        type="info"
        :closable="false"
        title="留空表示不修改当前密码"
      />

      <div class="flex justify-end">
        <el-button type="primary" :loading="loading" @click="handleSave">
          保存账号与密码
        </el-button>
      </div>
    </el-form>
  </el-card>
</template>

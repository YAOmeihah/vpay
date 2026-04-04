<script setup lang="ts">
import { ref } from "vue";
import type { FormInstance, FormRules } from "element-plus";

import type { PaymentSection } from "../sectionState";

defineProps<{
  model: PaymentSection;
  loading: boolean;
}>();

const emit = defineEmits<{
  save: [];
}>();

const formRef = ref<FormInstance>();

const validatePositiveInteger = (_rule: unknown, value: string, callback: (error?: Error) => void) => {
  if (!/^[1-9]\d*$/.test(String(value ?? "").trim())) {
    callback(new Error("请输入大于 0 的整数分钟数"));
    return;
  }

  callback();
};

const rules: FormRules<PaymentSection> = {
  notifyUrl: [{ required: true, message: "请输入异步回调地址", trigger: "blur" }],
  returnUrl: [{ required: true, message: "请输入同步回调地址", trigger: "blur" }],
  key: [{ required: true, message: "请输入通讯密钥", trigger: "blur" }],
  close: [{ validator: validatePositiveInteger, trigger: "blur" }],
  payQf: [{ required: true, message: "请选择区分方式", trigger: "change" }]
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
        <div class="text-base font-medium">支付基础配置</div>
        <div class="text-sm text-gray-500">维护订单有效期、支付回调地址、通讯密钥和金额区分方式。</div>
      </div>
    </template>

    <el-form ref="formRef" :model="model" :rules="rules" label-width="120px">
      <el-form-item label="订单有效期" prop="close">
        <el-input
          v-model="model.close"
          type="number"
          placeholder="请输入订单过期分钟数"
        />
      </el-form-item>

      <el-form-item label="异步回调" prop="notifyUrl">
        <el-input v-model="model.notifyUrl" placeholder="请输入异步回调地址" />
      </el-form-item>

      <el-form-item label="同步回调" prop="returnUrl">
        <el-input v-model="model.returnUrl" placeholder="请输入支付完成跳转地址" />
      </el-form-item>

      <el-form-item label="通讯密钥" prop="key">
        <el-input v-model="model.key" placeholder="请输入通讯密钥" />
      </el-form-item>

      <el-form-item label="区分方式" prop="payQf">
        <el-select v-model="model.payQf" class="w-full">
          <el-option label="金额递增" value="1" />
          <el-option label="金额递减" value="2" />
        </el-select>
      </el-form-item>

      <div class="flex justify-end">
        <el-button type="primary" :loading="loading" @click="handleSave">
          保存支付配置
        </el-button>
      </div>
    </el-form>
  </el-card>
</template>

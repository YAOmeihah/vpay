<script setup lang="ts">
import { ref } from "vue";
import type { FormInstance, FormRules } from "element-plus";

import type { PaymentSection } from "../sectionState";

const props = defineProps<{
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
  key: [{ required: true, message: "请输入通讯密钥", trigger: "blur" }],
  close: [{ validator: validatePositiveInteger, trigger: "blur" }],
  payQf: [{ required: true, message: "请选择区分方式", trigger: "change" }],
  allocationStrategy: [{ required: true, message: "请选择分配策略", trigger: "change" }]
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
        <div class="text-sm text-gray-500">维护订单有效期、默认支付回调地址、通讯密钥和金额区分方式。</div>
      </div>
    </template>

    <el-form ref="formRef" :model="model" :rules="rules" label-width="120px">
      <el-form-item label="订单有效期" prop="close">
        <el-input
          v-model="props.model.close"
          type="number"
          placeholder="请输入订单过期分钟数"
        />
      </el-form-item>

      <el-form-item label="异步回调" prop="notifyUrl">
        <div class="w-full space-y-2">
          <el-input v-model="props.model.notifyUrl" placeholder="留空则不设置默认异步回调地址" />
          <div class="text-xs leading-5 text-gray-500">
            订单未传 notifyUrl 时使用；每笔订单传入 notifyUrl 会优先覆盖此默认值。
          </div>
        </div>
      </el-form-item>

      <el-form-item label="同步回调" prop="returnUrl">
        <div class="w-full space-y-2">
          <el-input v-model="props.model.returnUrl" placeholder="留空则不设置默认支付完成跳转地址" />
          <div class="text-xs leading-5 text-gray-500">
            订单未传 returnUrl 时使用；每笔订单传入 returnUrl 会优先覆盖此默认值。
          </div>
        </div>
      </el-form-item>

      <el-form-item label="通知 SSL 校验">
        <div class="w-full space-y-2">
          <div class="flex min-h-8 items-center">
            <el-switch
              v-model="props.model.notifySslVerify"
              active-value="1"
              inactive-value="0"
              inline-prompt
              active-text="开"
              inactive-text="关"
            />
          </div>
          <div class="text-xs leading-5 text-gray-500">
            生产环境建议开启；本地自签名 HTTPS 或证书链不完整时可临时关闭。
          </div>
        </div>
      </el-form-item>

      <el-form-item label="通讯密钥" prop="key">
        <el-input v-model="props.model.key" placeholder="请输入通讯密钥" />
      </el-form-item>

      <el-form-item label="区分方式" prop="payQf">
        <el-select v-model="props.model.payQf" class="w-full">
          <el-option label="金额递增" value="1" />
          <el-option label="金额递减" value="2" />
        </el-select>
      </el-form-item>

      <el-form-item label="分配策略" prop="allocationStrategy">
        <div class="w-full space-y-2">
          <el-select v-model="props.model.allocationStrategy" class="w-full">
            <el-option label="固定优先级" value="fixed_priority" />
            <el-option label="顺序轮询" value="round_robin" />
          </el-select>
          <div class="text-xs leading-5 text-gray-500">
            固定优先级会优先选择分配顺序最小的在线终端；顺序轮询会在可用终端之间依次切换。
          </div>
        </div>
      </el-form-item>

      <div class="flex justify-end">
        <el-button type="primary" :loading="props.loading" @click="handleSave">
          保存支付配置
        </el-button>
      </div>
    </el-form>
  </el-card>
</template>

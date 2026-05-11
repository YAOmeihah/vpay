<script setup lang="ts">
import { computed } from "vue";

import type { MaintenanceSection } from "../sectionState";

const props = defineProps<{
  model: MaintenanceSection;
  loading: boolean;
  tokenLoading: boolean;
  testLoading: boolean;
}>();

const emit = defineEmits<{
  "update:model": [model: MaintenanceSection];
  save: [];
  generateToken: [];
  testNotification: [];
}>();

const updateModel = (patch: Partial<MaintenanceSection>) => {
  emit("update:model", {
    ...props.model,
    ...patch
  });
};

const enabled = computed({
  get: () => props.model.enabled,
  set: value => updateModel({ enabled: String(value ?? "0") })
});

const token = computed({
  get: () => props.model.token,
  set: value => updateModel({ token: String(value ?? "") })
});

const allowedIps = computed({
  get: () => props.model.allowedIps,
  set: value => updateModel({ allowedIps: String(value ?? "") })
});

const terminalOfflineTask = computed({
  get: () => props.model.terminalOfflineTask,
  set: value => updateModel({ terminalOfflineTask: String(value ?? "0") })
});

const expiredOrderCleanupTask = computed({
  get: () => props.model.expiredOrderCleanupTask,
  set: value => updateModel({ expiredOrderCleanupTask: String(value ?? "0") })
});

const telegramEnabled = computed({
  get: () => props.model.telegramEnabled,
  set: value => updateModel({ telegramEnabled: String(value ?? "0") })
});

const telegramBotToken = computed({
  get: () => props.model.telegramBotToken,
  set: value => updateModel({ telegramBotToken: String(value ?? "") })
});

const telegramChatId = computed({
  get: () => props.model.telegramChatId,
  set: value => updateModel({ telegramChatId: String(value ?? "") })
});

const notifyTerminalOffline = computed({
  get: () => props.model.notifyTerminalOffline,
  set: value => updateModel({ notifyTerminalOffline: String(value ?? "0") })
});

const notifyTerminalRecovered = computed({
  get: () => props.model.notifyTerminalRecovered,
  set: value => updateModel({ notifyTerminalRecovered: String(value ?? "0") })
});

const notifyExpiredOrderCleanup = computed({
  get: () => props.model.notifyExpiredOrderCleanup,
  set: value => updateModel({ notifyExpiredOrderCleanup: String(value ?? "0") })
});

const notifyMaintenanceException = computed({
  get: () => props.model.notifyMaintenanceException,
  set: value =>
    updateModel({ notifyMaintenanceException: String(value ?? "0") })
});

const notifyPaymentSuccess = computed({
  get: () => props.model.notifyPaymentSuccess,
  set: value => updateModel({ notifyPaymentSuccess: String(value ?? "0") })
});

const notifyPaymentCallbackStatus = computed({
  get: () => props.model.notifyPaymentCallbackStatus,
  set: value =>
    updateModel({ notifyPaymentCallbackStatus: String(value ?? "0") })
});

const lastRunText = computed(() => {
  const timestamp = Number(props.model.lastRunAt);
  if (!Number.isFinite(timestamp) || timestamp <= 0) return "尚未执行";

  return new Date(timestamp * 1000).toLocaleString();
});

const lastRunResult = computed(() => props.model.lastRunResult || "暂无结果");
const maintenancePath = "/maintenance/run";
const requestUrl = computed(() => {
  const origin = typeof window === "undefined" ? "" : window.location.origin;

  return `${origin}${maintenancePath}`;
});
const curlExample = computed(() => {
  const headerToken = props.model.token || "<维护密钥>";

  return `curl -X POST "${requestUrl.value}" -H "X-Maintenance-Token: ${headerToken}"`;
});
</script>

<template>
  <el-card shadow="hover">
    <template #header>
      <div class="space-y-1">
        <div class="text-base font-medium">维护计划</div>
        <div class="text-sm text-gray-500">
          定时入口、维护任务与 Telegram 事件通知。
        </div>
      </div>
    </template>

    <el-form :model="props.model" label-width="140px">
      <el-form-item label="维护接口">
        <el-switch
          v-model="enabled"
          active-value="1"
          inactive-value="0"
          active-text="启用"
          inactive-text="关闭"
        />
      </el-form-item>

      <el-form-item label="接入提示">
        <div class="w-full space-y-3">
          <el-descriptions :column="1" border label-width="96px">
            <el-descriptions-item label="请求地址">
              <span class="break-all font-mono text-xs">
                POST /maintenance/run
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="完整地址">
              <span class="break-all font-mono text-xs">{{ requestUrl }}</span>
            </el-descriptions-item>
            <el-descriptions-item label="请求头">
              <span class="break-all font-mono text-xs">
                X-Maintenance-Token: {{ token || "维护密钥" }}
              </span>
            </el-descriptions-item>
            <el-descriptions-item label="请求参数">
              无需请求体；调用服务器 IP 需加入允许服务器 IP。
            </el-descriptions-item>
          </el-descriptions>
          <el-input
            :model-value="curlExample"
            type="textarea"
            :rows="2"
            readonly
          />
        </div>
      </el-form-item>

      <el-form-item label="维护密钥">
        <el-input
          v-model="token"
          type="password"
          show-password
          placeholder="请生成或填写维护密钥"
        >
          <template #append>
            <el-button :loading="tokenLoading" @click="emit('generateToken')">
              重新生成
            </el-button>
          </template>
        </el-input>
      </el-form-item>

      <el-form-item label="允许服务器 IP">
        <el-input
          v-model="allowedIps"
          type="textarea"
          :rows="2"
          placeholder="多个 IP 使用英文逗号分隔，例如：127.0.0.1,10.0.0.2"
        />
      </el-form-item>

      <el-form-item label="默认任务">
        <div class="flex flex-col gap-2 sm:flex-row sm:gap-6">
          <el-switch
            v-model="terminalOfflineTask"
            active-value="1"
            inactive-value="0"
            active-text="监控端检查"
          />
          <el-switch
            v-model="expiredOrderCleanupTask"
            active-value="1"
            inactive-value="0"
            active-text="关闭过期未支付订单"
          />
        </div>
      </el-form-item>

      <el-divider />

      <el-form-item label="Telegram">
        <el-switch
          v-model="telegramEnabled"
          active-value="1"
          inactive-value="0"
          active-text="启用"
          inactive-text="关闭"
        />
      </el-form-item>

      <el-form-item label="Bot Token">
        <el-input
          v-model="telegramBotToken"
          type="password"
          show-password
          placeholder="Telegram Bot Token"
        />
      </el-form-item>

      <el-form-item label="Chat ID">
        <el-input v-model="telegramChatId" placeholder="Telegram Chat ID" />
      </el-form-item>

      <el-form-item label="通知事件">
        <div class="grid gap-2 sm:grid-cols-2">
          <el-switch
            v-model="notifyTerminalOffline"
            active-value="1"
            inactive-value="0"
            active-text="离线"
          />
          <el-switch
            v-model="notifyTerminalRecovered"
            active-value="1"
            inactive-value="0"
            active-text="恢复"
          />
          <el-switch
            v-model="notifyExpiredOrderCleanup"
            active-value="1"
            inactive-value="0"
            active-text="过期订单"
          />
          <el-switch
            v-model="notifyMaintenanceException"
            active-value="1"
            inactive-value="0"
            active-text="异常"
          />
          <el-switch
            v-model="notifyPaymentSuccess"
            active-value="1"
            inactive-value="0"
            active-text="支付成功"
          />
          <el-switch
            v-model="notifyPaymentCallbackStatus"
            active-value="1"
            inactive-value="0"
            active-text="回调状态"
          />
        </div>
      </el-form-item>

      <el-divider />

      <el-form-item label="最近执行">
        <div class="w-full space-y-2 text-sm">
          <div>{{ lastRunText }}</div>
          <el-input
            :model-value="lastRunResult"
            type="textarea"
            :rows="3"
            readonly
          />
        </div>
      </el-form-item>

      <el-form-item>
        <div class="flex flex-wrap gap-2">
          <el-button type="primary" :loading="loading" @click="emit('save')">
            保存维护配置
          </el-button>
          <el-button :loading="testLoading" @click="emit('testNotification')">
            测试推送
          </el-button>
        </div>
      </el-form-item>
    </el-form>
  </el-card>
</template>

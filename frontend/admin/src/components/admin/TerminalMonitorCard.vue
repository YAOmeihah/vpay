<script setup lang="ts">
import type { MonitorOverviewCard } from "@/views/system/monitor/overviewState";
import { monitorQrPreviewStyle } from "@/views/system/monitor/qrPreview";

defineOptions({ name: "TerminalMonitorCard" });

const props = withDefaults(
  defineProps<{
    card: MonitorOverviewCard;
    showPaymentConfigButton?: boolean;
    showTerminalManagementButton?: boolean;
  }>(),
  {
    showPaymentConfigButton: true,
    showTerminalManagementButton: true
  }
);

const emit = defineEmits<{
  paymentConfig: [card: MonitorOverviewCard];
  terminalManagement: [card: MonitorOverviewCard];
}>();
</script>

<template>
  <el-card shadow="hover">
    <template #header>
      <div class="flex items-start justify-between gap-3">
        <div class="space-y-1">
          <div class="font-medium">{{ props.card.terminalName }}</div>
          <div class="text-xs text-gray-500">
            {{ props.card.terminalCode || `terminal-${props.card.id}` }} · 分配顺序
            {{ props.card.dispatchPriority }}
          </div>
        </div>
        <el-tag :type="props.card.statusType">
          {{ props.card.statusText }}
        </el-tag>
      </div>
    </template>

    <el-descriptions :column="1" border>
      <el-descriptions-item label="终端状态">
        {{ props.card.terminalStatus === "enabled" ? "启用" : "停用" }}
      </el-descriptions-item>
      <el-descriptions-item label="在线状态">
        {{ props.card.onlineState === "online" ? "在线" : "离线" }}
      </el-descriptions-item>
      <el-descriptions-item label="最后心跳">
        {{ props.card.lastHeartbeatText }}
      </el-descriptions-item>
      <el-descriptions-item label="最后收款">
        {{ props.card.lastPaidText }}
      </el-descriptions-item>
      <el-descriptions-item label="配置数据">
        <span class="break-all">{{ props.card.configUrl || "未设置" }}</span>
      </el-descriptions-item>
    </el-descriptions>

    <el-divider content-position="left">配置二维码</el-divider>

    <template v-if="props.card.configUrl">
      <el-alert
        title="使用监控端 App 扫描此二维码进行绑定"
        type="info"
        :closable="false"
        class="mb-4"
      />
      <div class="flex justify-center rounded bg-gray-50 p-4">
        <img
          :src="props.card.qrcodeUrl"
          alt="配置二维码"
          class="max-w-full"
          :style="monitorQrPreviewStyle"
        />
      </div>
      <el-input :model-value="props.card.configUrl" readonly class="mt-4">
        <template #prepend>配置地址</template>
      </el-input>
    </template>
    <el-alert
      v-else
      title="该终端还没有监控密钥，请前往终端管理补全。"
      type="warning"
      :closable="false"
    />

    <div
      v-if="props.showPaymentConfigButton || props.showTerminalManagementButton"
      class="mt-4 flex flex-wrap justify-end gap-3"
    >
      <el-button
        v-if="props.showPaymentConfigButton"
        type="primary"
        plain
        @click="emit('paymentConfig', props.card)"
      >
        支付配置
      </el-button>
      <el-button
        v-if="props.showTerminalManagementButton"
        @click="emit('terminalManagement', props.card)"
      >
        终端管理
      </el-button>
    </div>
  </el-card>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from "vue";
import { useRouter } from "vue-router";

import { getTerminals } from "@/api/admin/terminal";
import TerminalMonitorCard from "@/components/admin/TerminalMonitorCard.vue";
import { message } from "@/utils/message";
import {
  buildMonitorOverviewCards,
  type MonitorOverviewCard
} from "./overviewState";

defineOptions({ name: "MonitorSettings" });

const TERMINAL_PAGE_SIZE = 100;

const router = useRouter();
const loading = ref(false);
const cards = ref<MonitorOverviewCard[]>([]);

const terminalCountText = computed(() => `共 ${cards.value.length} 台终端`);

const loadTerminals = async () => {
  try {
    loading.value = true;
    const rows: Record<string, any>[] = [];
    let page = 1;
    let total = 0;

    while (true) {
      const res = await getTerminals({ page, limit: TERMINAL_PAGE_SIZE });
      if (res.code !== 1) {
        throw new Error(res.msg || "监控终端加载失败");
      }

      const payload = res.data ?? { data: [], count: 0 };
      const batch = Array.isArray(payload.data) ? payload.data : [];

      rows.push(...batch);
      total = Number(payload.count ?? rows.length);

      if (rows.length >= total || batch.length < TERMINAL_PAGE_SIZE) {
        break;
      }

      page += 1;
    }

    cards.value = buildMonitorOverviewCards(rows, location.host);
  } catch (error: any) {
    message(error?.message || "监控终端加载失败", { type: "error" });
  } finally {
    loading.value = false;
  }
};

const openTerminalManagement = () => {
  router.push("/system/terminals");
};

const openPaymentConfig = (card: MonitorOverviewCard) => {
  router.push({
    name: "TerminalPaymentConfig",
    params: { terminalId: String(card.id) },
    query: { terminalName: card.terminalName }
  });
};

const openLocalAppDownload = () => {
  window.open("/v.apk", "_blank");
};

const openLatestAppDownload = () => {
  window.open("https://github.com/szvone/vmqApk/releases", "_blank");
};

onMounted(loadTerminals);
</script>

<template>
  <div class="p-4 space-y-4">
    <el-card shadow="never">
      <div class="flex flex-wrap items-start justify-between gap-4">
        <div class="space-y-1">
          <h2 class="text-lg font-medium">监控总览</h2>
          <p class="text-sm text-gray-500">
            按终端集中查看监控状态、心跳时间、最后收款和绑定二维码，扫码时不需要再跳去终端详情页找配置。
          </p>
          <div class="text-xs text-gray-400">{{ terminalCountText }}</div>
        </div>
        <div class="flex flex-wrap gap-3">
          <el-button @click="openTerminalManagement">终端管理</el-button>
          <el-button @click="openLocalAppDownload">下载监控端</el-button>
          <el-button @click="openLatestAppDownload">最新版监控端下载</el-button>
        </div>
      </div>
    </el-card>

    <el-empty
      v-if="!loading && cards.length === 0"
      description="还没有监控终端，请先在终端管理里创建终端。"
    >
      <el-button type="primary" @click="openTerminalManagement">
        前往终端管理
      </el-button>
    </el-empty>

    <div v-else class="grid gap-4 xl:grid-cols-2">
      <TerminalMonitorCard
        v-for="card in cards"
        :key="card.id || card.terminalCode"
        :card="card"
        @payment-config="openPaymentConfig"
        @terminal-management="openTerminalManagement"
      />
    </div>
  </div>
</template>

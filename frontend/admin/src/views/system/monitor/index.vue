<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { getSettings } from "@/api/admin/settings";
import {
  buildMonitorConfigUrl,
  buildQrcodePreviewUrl,
  formatUnixTimestamp,
  getMonitorStatus
} from "@/utils/adminLegacy";
import { monitorQrPreviewStyle } from "./qrPreview";

defineOptions({ name: "MonitorSettings" });

const loading = ref(false);
const settings = ref<any>({});

const status = computed(() => getMonitorStatus(settings.value.jkstate));

const configUrl = computed(() =>
  buildMonitorConfigUrl(location.host, settings.value.key)
);

const qrcodeUrl = computed(() =>
  buildQrcodePreviewUrl(configUrl.value)
);

const loadSettings = async () => {
  try {
    loading.value = true;
    const res = await getSettings();
    if (res.code === 1) settings.value = res.data;
  } finally {
    loading.value = false;
  }
};

const openLocalAppDownload = () => {
  window.open("/v.apk", "_blank");
};

const openLatestAppDownload = () => {
  window.open("https://github.com/szvone/vmqApk/releases", "_blank");
};

onMounted(loadSettings);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover" v-loading="loading">
      <template #header><span>监控端状态</span></template>

        <el-descriptions :column="1" border>
        <el-descriptions-item label="运行状态">
          <el-tag :type="status.type">{{ status.text }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="最后心跳">
          {{ formatUnixTimestamp(settings.lastheart) }}
        </el-descriptions-item>
        <el-descriptions-item label="最后收款">
          {{ formatUnixTimestamp(settings.lastpay) }}
        </el-descriptions-item>
        <el-descriptions-item label="配置数据">
          {{ configUrl || "未设置" }}
        </el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">配置二维码</el-divider>

      <template v-if="configUrl">
        <el-alert title="使用监控端 App 扫描此二维码进行绑定" type="info" :closable="false" class="mb-4" />
        <div class="flex justify-center p-4 bg-gray-50 rounded">
          <img
            :src="qrcodeUrl"
            alt="配置二维码"
            class="max-w-full"
            :style="monitorQrPreviewStyle"
          />
        </div>
        <el-input :model-value="configUrl" readonly class="mt-4">
          <template #prepend>配置地址</template>
        </el-input>
      </template>
      <el-alert v-else title="请先在系统设置中配置通讯密钥" type="warning" :closable="false" />

      <div class="mt-4 flex gap-3 justify-end">
        <el-button @click="openLocalAppDownload">下载监控端</el-button>
        <el-button @click="openLatestAppDownload">
          最新版监控端下载
        </el-button>
      </div>
    </el-card>
  </div>
</template>

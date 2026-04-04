<script setup lang="ts">
import { ref, computed, onMounted } from "vue";
import { getSettings } from "@/api/admin/settings";

defineOptions({ name: "MonitorSettings" });

const loading = ref(false);
const settings = ref<any>({});

const statusText = computed(() => {
  const s = settings.value.jkstate;
  if (s === -1) return "监控端未绑定，请您扫码绑定";
  if (s === 0) return "监控端已掉线，请检查 App 是否正常运行";
  if (s === 1) return "运行正常";
  return "未知状态";
});

const statusType = computed(() => {
  const s = settings.value.jkstate;
  if (s === 1) return "success";
  if (s === 0) return "danger";
  return "warning";
});

const configUrl = computed(() =>
  settings.value.key ? `${location.origin}/${settings.value.key}` : ""
);

const qrcodeUrl = computed(() =>
  configUrl.value ? `/enQrcode?url=${encodeURIComponent(configUrl.value)}` : ""
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

onMounted(loadSettings);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover" v-loading="loading">
      <template #header><span>监控端状态</span></template>

      <el-descriptions :column="1" border>
        <el-descriptions-item label="运行状态">
          <el-tag :type="statusType">{{ statusText }}</el-tag>
        </el-descriptions-item>
        <el-descriptions-item label="最后心跳">{{ settings.lastheart || "无" }}</el-descriptions-item>
        <el-descriptions-item label="最后支付">{{ settings.lastpay || "无" }}</el-descriptions-item>
        <el-descriptions-item label="通讯密钥">{{ settings.key || "未设置" }}</el-descriptions-item>
      </el-descriptions>

      <el-divider content-position="left">配置二维码</el-divider>

      <template v-if="configUrl">
        <el-alert title="使用监控端 App 扫描此二维码进行绑定" type="info" :closable="false" class="mb-4" />
        <div class="flex justify-center p-5 bg-gray-50 rounded">
          <img :src="qrcodeUrl" alt="配置二维码" class="max-w-xs w-full" />
        </div>
        <el-input v-model="configUrl" readonly class="mt-4">
          <template #prepend>配置地址</template>
        </el-input>
      </template>
      <el-alert v-else title="请先在系统设置中配置通讯密钥" type="warning" :closable="false" />
    </el-card>
  </div>
</template>

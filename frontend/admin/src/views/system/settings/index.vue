<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";

import { message } from "@/utils/message";
import {
  generateMaintenanceToken,
  getSettings,
  saveSettings,
  testMaintenanceNotification
} from "@/api/admin/settings";

import MaintenanceCard from "./components/MaintenanceCard.vue";
import PaymentConfigCard from "./components/PaymentConfigCard.vue";
import SecurityCard from "./components/SecurityCard.vue";
import SystemUpdateCard from "./components/SystemUpdateCard.vue";
import {
  buildMaintenancePayload,
  buildPaymentPayload,
  buildSecurityPayload,
  createSettingsSections,
  hydrateSettingsSections
} from "./sectionState";

defineOptions({ name: "SystemSettings" });

type SectionKey =
  | ""
  | "security"
  | "payment"
  | "maintenance"
  | "maintenance-token"
  | "maintenance-test";

const initialLoading = ref(false);
const activeSection = ref<SectionKey>("");
const sections = reactive(createSettingsSections());

const loadSettings = async () => {
  try {
    initialLoading.value = true;
    const res = await getSettings();
    if (res.code === 1) {
      hydrateSettingsSections(sections, res.data);
    } else {
      message(res.msg || "系统设置加载失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "系统设置加载失败", {
      type: "error"
    });
  } finally {
    initialLoading.value = false;
  }
};

const saveSection = async (
  section: Exclude<SectionKey, "" | "maintenance-token" | "maintenance-test">,
  label: string,
  payload: Record<string, string>
) => {
  try {
    activeSection.value = section;
    const res = await saveSettings(payload);

    if (res.code !== 1) {
      message(res.msg || `${label}保存失败`, { type: "error" });
      return;
    }

    await loadSettings();
    message(`${label}已保存`, { type: "success" });
  } catch (error: any) {
    message(error?.msg || error?.message || `${label}保存失败`, {
      type: "error"
    });
  } finally {
    activeSection.value = "";
  }
};

const generateToken = async () => {
  try {
    activeSection.value = "maintenance-token";
    const res = await generateMaintenanceToken();

    if (res.code !== 1) {
      message(res.msg || "维护密钥生成失败", { type: "error" });
      return;
    }

    sections.maintenance.token = res.data?.token || "";
    await loadSettings();
    message("维护密钥已生成", { type: "success" });
  } catch (error: any) {
    message(error?.msg || error?.message || "维护密钥生成失败", {
      type: "error"
    });
  } finally {
    activeSection.value = "";
  }
};

const testNotification = async () => {
  try {
    activeSection.value = "maintenance-test";
    const res = await testMaintenanceNotification();

    if (res.code !== 1) {
      message(res.msg || "测试推送失败", { type: "error" });
      return;
    }

    message("测试推送已发送", { type: "success" });
  } catch (error: any) {
    message(error?.msg || error?.message || "测试推送失败", {
      type: "error"
    });
  } finally {
    activeSection.value = "";
  }
};

onMounted(loadSettings);
</script>

<template>
  <div v-loading="initialLoading" class="p-4 space-y-4">
    <el-card shadow="never">
      <div class="space-y-1">
        <h2 class="text-lg font-medium">系统设置</h2>
        <p class="text-sm text-gray-500">
          按功能分区维护后台安全、支付基础配置和多终端分配策略。终端密钥与收款码请到终端管理中维护。
        </p>
      </div>
    </el-card>

    <SystemUpdateCard />

    <SecurityCard
      v-model:model="sections.security"
      :loading="activeSection === 'security'"
      @save="
        saveSection(
          'security',
          '账号与密码',
          buildSecurityPayload(sections.security)
        )
      "
    />

    <PaymentConfigCard
      v-model:model="sections.payment"
      :loading="activeSection === 'payment'"
      @save="
        saveSection(
          'payment',
          '支付基础配置',
          buildPaymentPayload(sections.payment)
        )
      "
    />

    <MaintenanceCard
      v-model:model="sections.maintenance"
      :loading="activeSection === 'maintenance'"
      :token-loading="activeSection === 'maintenance-token'"
      :test-loading="activeSection === 'maintenance-test'"
      @save="
        saveSection(
          'maintenance',
          '维护计划配置',
          buildMaintenancePayload(sections.maintenance)
        )
      "
      @generate-token="generateToken"
      @test-notification="testNotification"
    />
  </div>
</template>

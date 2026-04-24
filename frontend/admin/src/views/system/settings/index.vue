<script setup lang="ts">
import { onMounted, reactive, ref } from "vue";

import { message } from "@/utils/message";
import { getSettings, saveSettings } from "@/api/admin/settings";

import PaymentConfigCard from "./components/PaymentConfigCard.vue";
import SecurityCard from "./components/SecurityCard.vue";
import SystemUpdateCard from "./components/SystemUpdateCard.vue";
import {
  buildPaymentPayload,
  buildSecurityPayload,
  createSettingsSections,
  hydrateSettingsSections
} from "./sectionState";

defineOptions({ name: "SystemSettings" });

type SectionKey = "" | "security" | "payment";

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
  section: Exclude<SectionKey, "">,
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

onMounted(loadSettings);
</script>

<template>
  <div class="p-4 space-y-4" v-loading="initialLoading">
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
      :model="sections.security"
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
      :model="sections.payment"
      :loading="activeSection === 'payment'"
      @save="
        saveSection(
          'payment',
          '支付基础配置',
          buildPaymentPayload(sections.payment)
        )
      "
    />
  </div>
</template>

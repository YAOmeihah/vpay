<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import type { UploadFile } from "element-plus";

import { message } from "@/utils/message";
import { getSettings, saveSettings } from "@/api/admin/settings";
import { buildQrcodePreviewUrl } from "@/utils/adminLegacy";
import { decodeQrFromFile } from "@/utils/qrcode";

import PaymentConfigCard from "./components/PaymentConfigCard.vue";
import QrcodeCard from "./components/QrcodeCard.vue";
import SecurityCard from "./components/SecurityCard.vue";
import {
  buildPaymentPayload,
  buildQrcodePayload,
  buildSecurityPayload,
  createSettingsSections,
  hydrateSettingsSections
} from "./sectionState";

defineOptions({ name: "SystemSettings" });

type SectionKey = "" | "security" | "payment" | "qrcode";

const initialLoading = ref(false);
const activeSection = ref<SectionKey>("");
const sections = reactive(createSettingsSections());

const wxpayPreviewUrl = computed(() =>
  buildQrcodePreviewUrl(sections.qrcode.wxpay)
);
const zfbpayPreviewUrl = computed(() =>
  buildQrcodePreviewUrl(sections.qrcode.zfbpay)
);

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

const handleQrcodeChange = async (
  field: "wxpay" | "zfbpay",
  uploadFile: UploadFile
) => {
  const file = uploadFile.raw;
  if (!file) return;

  try {
    const decoded = await decodeQrFromFile(file);
    if (!decoded) {
      message("二维码解析失败，可以手动填写二维码内容", {
        type: "error"
      });
      return;
    }

    sections.qrcode[field] = decoded;
    message("二维码解析成功，请点击保存收款码", { type: "success" });
  } catch (error: any) {
    message(error?.msg || error?.message || "二维码解析失败", {
      type: "error"
    });
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
          按功能分区维护后台安全、支付基础配置和默认收款码。
        </p>
      </div>
    </el-card>

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

    <QrcodeCard
      :model="sections.qrcode"
      :wxpay-preview-url="wxpayPreviewUrl"
      :zfbpay-preview-url="zfbpayPreviewUrl"
      :loading="activeSection === 'qrcode'"
      @save="
        saveSection('qrcode', '默认收款码', buildQrcodePayload(sections.qrcode))
      "
      @upload="handleQrcodeChange"
    />
  </div>
</template>

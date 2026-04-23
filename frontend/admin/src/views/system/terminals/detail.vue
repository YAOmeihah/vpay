<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute, useRouter } from "vue-router";
import { ElMessageBox } from "element-plus";

import { getTerminalDetail } from "@/api/admin/terminal";
import {
  getTerminalChannels,
  saveTerminalChannel,
  toggleTerminalChannel,
  type ChannelPayload
} from "@/api/admin/channel";
import QrBatchUploader from "@/components/admin/QrBatchUploader.vue";
import QrList from "@/components/admin/QrList.vue";
import TerminalMonitorCard from "@/components/admin/TerminalMonitorCard.vue";
import { message } from "@/utils/message";
import {
  buildMonitorOverviewCards,
  type MonitorOverviewCard
} from "../monitor/overviewState";
import {
  buildPaymentSlots,
  defaultChannelName,
  paymentTypeLabel,
  type PaymentSlot
} from "./paymentSlotState";

defineOptions({ name: "TerminalPaymentConfig" });

const route = useRoute();
const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const list = ref<PaymentSlot[]>([]);
const dialogVisible = ref(false);
const monitorCard = ref<MonitorOverviewCard | null>(null);

const terminalId = computed(() => Number(route.params.terminalId));
const terminalName = computed(
  () =>
    monitorCard.value?.terminalName ??
    String(route.query.terminalName ?? `终端 #${terminalId.value}`)
);

const form = reactive<ChannelPayload>({
  terminalId: 0,
  type: 1,
  channelName: "",
  status: "enabled",
  payUrl: ""
});

const dialogTitle = computed(
  () => `${form.id ? "编辑" : "配置"}${paymentTypeLabel(form.type)}收款`
);

const resetForm = (type: 1 | 2) => {
  form.id = undefined;
  form.terminalId = terminalId.value;
  form.type = type;
  form.channelName = defaultChannelName(type);
  form.status = "enabled";
  form.payUrl = "";
};

const loadList = async () => {
  try {
    loading.value = true;
    const [terminalRes, channelRes] = await Promise.all([
      getTerminalDetail({ id: terminalId.value }),
      getTerminalChannels({ terminalId: terminalId.value })
    ]);

    if (terminalRes.code !== 1) {
      throw new Error(terminalRes.msg || "终端详情加载失败");
    }

    if (channelRes.code !== 1) {
      throw new Error(channelRes.msg || "支付配置加载失败");
    }

    const [card] = buildMonitorOverviewCards([terminalRes.data ?? {}], location.host);
    monitorCard.value = card ?? null;
    list.value = buildPaymentSlots(channelRes.data ?? [], terminalId.value);
  } catch (error: any) {
    message(error?.msg || error?.message || "终端页面加载失败", {
      type: "error"
    });
  } finally {
    loading.value = false;
  }
};

const openTerminalManagement = () => {
  router.push("/system/terminals");
};

const openSlotEditor = (slot: PaymentSlot) => {
  form.id = slot.id;
  form.terminalId = slot.terminalId;
  form.type = slot.type;
  form.channelName = slot.channelName;
  form.status = slot.exists ? slot.status : "enabled";
  form.payUrl = slot.payUrl;
  dialogVisible.value = true;
};

const submitForm = async () => {
  try {
    saving.value = true;
    const res = await saveTerminalChannel(form);
    if (res.code === 1) {
      message("支付配置已保存", { type: "success" });
      dialogVisible.value = false;
      await loadList();
    } else {
      message(res.msg || "支付配置保存失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "支付配置保存失败", {
      type: "error"
    });
  } finally {
    saving.value = false;
  }
};

const handleToggle = async (slot: PaymentSlot) => {
  await ElMessageBox.confirm(
    `确认切换${slot.slotLabel}收款配置的启用状态？`,
    "提示",
    {
      type: "warning"
    }
  );
  const res = await toggleTerminalChannel({ id: Number(slot.id) });
  if (res.code === 1) {
    message(`${slot.slotLabel}收款配置状态已更新`, { type: "success" });
    loadList();
  } else {
    message(res.msg || "支付配置状态更新失败", { type: "error" });
  }
};

onMounted(() => {
  resetForm(1);
  loadList();
});
</script>

<template>
  <div class="p-4 space-y-4">
    <el-card shadow="never">
      <div class="space-y-1">
        <h2 class="text-lg font-medium">{{ terminalName }} 支付配置</h2>
        <p class="text-sm text-gray-500">
          固定维护微信和支付宝两个支付配置。每个终端只区分设备本身与支付类型，不再开放式新增同类型通道。
        </p>
      </div>
    </el-card>

    <TerminalMonitorCard
      v-if="monitorCard"
      :card="monitorCard"
      :show-payment-config-button="false"
      @terminal-management="openTerminalManagement"
    />

    <div class="grid gap-4 md:grid-cols-2">
      <el-card
        v-for="slot in list"
        :key="slot.type"
        shadow="hover"
        v-loading="loading"
      >
        <template #header>
          <div class="flex items-center justify-between gap-3">
            <div>
              <div class="font-medium">{{ slot.slotLabel }}收款配置</div>
              <div class="text-xs text-gray-500">
                {{ slot.exists ? slot.channelName : `尚未创建${slot.slotLabel}配置` }}
              </div>
            </div>
            <el-tag :type="slot.exists && slot.status === 'enabled' ? 'success' : 'info'">
              {{
                slot.exists
                  ? slot.status === "enabled"
                    ? "启用"
                    : "停用"
                  : "未配置"
              }}
            </el-tag>
          </div>
        </template>

        <el-descriptions :column="1" border size="small">
          <el-descriptions-item label="配置名称">
            {{ slot.channelName }}
          </el-descriptions-item>
          <el-descriptions-item label="默认收款地址">
            <span class="break-all">{{ slot.payUrl || "未设置" }}</span>
          </el-descriptions-item>
        </el-descriptions>

        <el-alert
          v-if="!slot.exists"
          :title="`先创建${slot.slotLabel}配置，再上传该支付类型的金额二维码。`"
          type="warning"
          :closable="false"
          class="mt-4"
        />

        <div class="mt-4 flex gap-3">
          <el-button type="primary" @click="openSlotEditor(slot)">
            {{ slot.exists ? "编辑配置" : "创建配置" }}
          </el-button>
          <el-button
            v-if="slot.exists"
            type="warning"
            plain
            @click="handleToggle(slot)"
          >
            {{ slot.status === "enabled" ? "停用" : "启用" }}
          </el-button>
        </div>
      </el-card>
    </div>

    <template v-for="slot in list" :key="`${slot.type}-qrcode`">
      <template v-if="slot.exists && slot.id">
        <QrBatchUploader
          :type="slot.type"
          :title="`${slot.slotLabel}金额二维码上传`"
          :scan-hint="`上传${slot.slotLabel}金额二维码，成功后会自动绑定到当前支付配置。`"
          :channel-id="slot.id"
        />
        <QrList
          :type="slot.type"
          :title="`${slot.slotLabel}金额二维码列表`"
          :channel-id="slot.id"
        />
      </template>
      <el-card v-else shadow="hover">
        <template #header>
          <span>{{ slot.slotLabel }}金额二维码</span>
        </template>
        <el-alert
          :title="`当前终端还没有${slot.slotLabel}支付配置，暂时无法上传金额二维码。`"
          type="info"
          :closable="false"
        />
      </el-card>
    </template>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form label-width="120px">
        <el-form-item label="支付类型">
          <el-input :model-value="paymentTypeLabel(form.type)" readonly />
        </el-form-item>
        <el-form-item label="配置名称">
          <el-input
            v-model="form.channelName"
            :placeholder="defaultChannelName(form.type)"
          />
        </el-form-item>
        <el-form-item label="默认收款地址">
          <el-input
            v-model="form.payUrl"
            type="textarea"
            :rows="3"
            placeholder="未上传金额二维码时使用此地址"
          />
        </el-form-item>
        <el-form-item label="启用状态">
          <el-select v-model="form.status" class="w-full">
            <el-option label="启用" value="enabled" />
            <el-option label="停用" value="disabled" />
          </el-select>
        </el-form-item>
      </el-form>

      <template #footer>
        <el-button @click="dialogVisible = false">取消</el-button>
        <el-button type="primary" :loading="saving" @click="submitForm">
          保存
        </el-button>
      </template>
    </el-dialog>
  </div>
</template>

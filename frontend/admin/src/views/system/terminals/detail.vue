<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRoute } from "vue-router";
import { ElMessageBox } from "element-plus";

import {
  getTerminalChannels,
  saveTerminalChannel,
  toggleTerminalChannel,
  type ChannelPayload
} from "@/api/admin/channel";
import QrBatchUploader from "@/components/admin/QrBatchUploader.vue";
import QrList from "@/components/admin/QrList.vue";
import { message } from "@/utils/message";

defineOptions({ name: "TerminalChannels" });

const route = useRoute();
const loading = ref(false);
const saving = ref(false);
const list = ref<any[]>([]);
const dialogVisible = ref(false);

const terminalId = computed(() => Number(route.params.terminalId));
const terminalName = computed(() => String(route.query.terminalName ?? `终端 #${terminalId.value}`));

const form = reactive<ChannelPayload>({
  terminalId: 0,
  type: 1,
  channelName: "",
  status: "enabled",
  payUrl: "",
  priority: 100
});

const dialogTitle = computed(() => (form.id ? "编辑通道" : "新建通道"));

const resetForm = () => {
  form.id = undefined;
  form.terminalId = terminalId.value;
  form.type = 1;
  form.channelName = "";
  form.status = "enabled";
  form.payUrl = "";
  form.priority = 100;
};

const loadList = async () => {
  try {
    loading.value = true;
    const res = await getTerminalChannels({ terminalId: terminalId.value });
    if (res.code === 1) {
      list.value = res.data ?? [];
    } else {
      message(res.msg || "通道列表加载失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "通道列表加载失败", { type: "error" });
  } finally {
    loading.value = false;
  }
};

const openCreate = () => {
  resetForm();
  dialogVisible.value = true;
};

const openEdit = (row: any) => {
  form.id = Number(row.id);
  form.terminalId = Number(row.terminal_id ?? terminalId.value);
  form.type = Number(row.type ?? 1) as 1 | 2;
  form.channelName = String(row.channel_name ?? "");
  form.status = String(row.status ?? "enabled");
  form.payUrl = String(row.pay_url ?? "");
  form.priority = Number(row.priority ?? 100);
  dialogVisible.value = true;
};

const submitForm = async () => {
  try {
    saving.value = true;
    const res = await saveTerminalChannel(form);
    if (res.code === 1) {
      message("通道已保存", { type: "success" });
      dialogVisible.value = false;
      await loadList();
    } else {
      message(res.msg || "通道保存失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "通道保存失败", { type: "error" });
  } finally {
    saving.value = false;
  }
};

const handleToggle = async (row: any) => {
  await ElMessageBox.confirm("确认切换该通道的启用状态？", "提示", {
    type: "warning"
  });
  const res = await toggleTerminalChannel({ id: Number(row.id) });
  if (res.code === 1) {
    message("通道状态已更新", { type: "success" });
    loadList();
  } else {
    message(res.msg || "通道状态更新失败", { type: "error" });
  }
};

onMounted(() => {
  resetForm();
  loadList();
});
</script>

<template>
  <div class="p-4 space-y-4">
    <el-card shadow="never">
      <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
          <h2 class="text-lg font-medium">{{ terminalName }} 通道管理</h2>
          <p class="text-sm text-gray-500">
            为当前终端维护微信/支付宝通道，以及该通道下的金额二维码。
          </p>
        </div>
        <el-button type="primary" @click="openCreate">新增通道</el-button>
      </div>
    </el-card>

    <el-card shadow="hover">
      <el-table :data="list" v-loading="loading" border>
        <el-table-column label="通道名称" prop="channel_name" min-width="180" />
        <el-table-column label="类型" width="100">
          <template #default="{ row }">
            {{ Number(row.type) === 1 ? "微信" : "支付宝" }}
          </template>
        </el-table-column>
        <el-table-column label="优先级" prop="priority" width="100" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'">
              {{ row.status === "enabled" ? "启用" : "停用" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="默认收款地址" prop="pay_url" min-width="220" show-overflow-tooltip />
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text @click="openEdit(row)">编辑</el-button>
            <el-button size="small" text type="warning" @click="handleToggle(row)">
              {{ row.status === "enabled" ? "停用" : "启用" }}
            </el-button>
          </template>
        </el-table-column>
      </el-table>
    </el-card>

    <template v-for="channel in list" :key="channel.id">
      <QrBatchUploader
        :type="Number(channel.type) as 1 | 2"
        :title="`${channel.channel_name} 金额二维码上传`"
        :scan-hint="'上传该通道的金额二维码，成功后会自动绑定到 channel_id'"
        :channel-id="Number(channel.id)"
      />
      <QrList
        :type="Number(channel.type) as 1 | 2"
        :title="`${channel.channel_name} 金额二维码列表`"
        :channel-id="Number(channel.id)"
      />
    </template>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form label-width="120px">
        <el-form-item label="通道名称">
          <el-input v-model="form.channelName" placeholder="请输入通道名称" />
        </el-form-item>
        <el-form-item label="支付类型">
          <el-select v-model="form.type" class="w-full">
            <el-option label="微信" :value="1" />
            <el-option label="支付宝" :value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="优先级">
          <el-input v-model.number="form.priority" type="number" />
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

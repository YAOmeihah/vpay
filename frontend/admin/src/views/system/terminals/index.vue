<script setup lang="ts">
import { computed, onMounted, reactive, ref } from "vue";
import { useRouter } from "vue-router";
import { ElMessageBox } from "element-plus";

import {
  deleteTerminal,
  getTerminals,
  resetTerminalKey,
  saveTerminal,
  toggleTerminal,
  type TerminalPayload
} from "@/api/admin/terminal";
import { message } from "@/utils/message";

defineOptions({ name: "TerminalManagement" });

const router = useRouter();
const loading = ref(false);
const saving = ref(false);
const list = ref<any[]>([]);
const total = ref(0);
const page = ref(1);
const limit = 10;

const dialogVisible = ref(false);
const form = reactive<TerminalPayload>({
  terminalCode: "",
  terminalName: "",
  dispatchPriority: 100,
  status: "enabled",
  online_state: "offline",
  monitorKey: ""
});

const dialogTitle = computed(() => (form.id ? "编辑终端" : "新建终端"));

const resetForm = () => {
  form.id = undefined;
  form.terminalCode = "";
  form.terminalName = "";
  form.dispatchPriority = 100;
  form.status = "enabled";
  form.online_state = "offline";
  form.monitorKey = "";
};

const loadList = async () => {
  try {
    loading.value = true;
    const res = await getTerminals({ page: page.value, limit });
    if (res.code === 1) {
      list.value = res.data.data ?? [];
      total.value = Number(res.data.count ?? 0);
    } else {
      message(res.msg || "终端列表加载失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "终端列表加载失败", {
      type: "error"
    });
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
  form.terminalCode = String(row.terminal_code ?? "");
  form.terminalName = String(row.terminal_name ?? "");
  form.dispatchPriority = Number(row.dispatch_priority ?? 100);
  form.status = String(row.status ?? "enabled");
  form.online_state = String(row.online_state ?? "offline");
  form.monitorKey = String(row.monitor_key ?? "");
  dialogVisible.value = true;
};

const submitForm = async () => {
  try {
    saving.value = true;
    const res = await saveTerminal(form);
    if (res.code === 1) {
      message("终端已保存", { type: "success" });
      dialogVisible.value = false;
      await loadList();
    } else {
      message(res.msg || "终端保存失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "终端保存失败", { type: "error" });
  } finally {
    saving.value = false;
  }
};

const handleToggle = async (row: any) => {
  await ElMessageBox.confirm("确认切换该终端的启用状态？", "提示", {
    type: "warning"
  });
  const res = await toggleTerminal({ id: Number(row.id) });
  if (res.code === 1) {
    message("终端状态已更新", { type: "success" });
    loadList();
  } else {
    message(res.msg || "终端状态更新失败", { type: "error" });
  }
};

const handleDelete = async (row: any) => {
  await ElMessageBox.confirm(
    "确认删除该终端？删除后会同时清理终端下的支付通道；若存在未支付订单则会被阻止。",
    "提示",
    {
      type: "warning",
      confirmButtonText: "删除",
      confirmButtonClass: "el-button--danger"
    }
  );

  try {
    const res = await deleteTerminal({ id: Number(row.id) });
    if (res.code === 1) {
      message("终端已删除", { type: "success" });
      await loadList();
    } else {
      message(res.msg || "终端删除失败", { type: "error" });
    }
  } catch (error: any) {
    message(error?.msg || error?.message || "终端删除失败", { type: "error" });
  }
};

const handleResetKey = async (row: any) => {
  await ElMessageBox.confirm("确认重置该终端的监控密钥？", "提示", {
    type: "warning"
  });
  const res = await resetTerminalKey({ id: Number(row.id) });
  if (res.code === 1) {
    message("监控密钥已重置", { type: "success" });
    loadList();
  } else {
    message(res.msg || "监控密钥重置失败", { type: "error" });
  }
};

const openChannels = (row: any) => {
  router.push({
    name: "TerminalPaymentConfig",
    params: { terminalId: String(row.id) },
    query: { terminalName: String(row.terminal_name ?? "") }
  });
};

onMounted(loadList);
</script>

<template>
  <div class="p-4 space-y-4">
    <el-card shadow="never">
      <div class="flex items-center justify-between gap-4">
        <div class="space-y-1">
          <h2 class="text-lg font-medium">终端管理</h2>
          <p class="text-sm text-gray-500">
            管理监控终端、分配顺序、在线状态和专属监控密钥。数字越小越优先参与固定顺序分配。
          </p>
        </div>
        <el-button type="primary" @click="openCreate">新建终端</el-button>
      </div>
    </el-card>

    <el-card shadow="hover">
      <el-table :data="list" v-loading="loading" border>
        <el-table-column label="终端名称" prop="terminal_name" min-width="160" />
        <el-table-column label="终端编码" prop="terminal_code" min-width="180" />
        <el-table-column label="分配顺序" prop="dispatch_priority" min-width="110" />
        <el-table-column label="运行状态" min-width="110">
          <template #default="{ row }">
            <el-tag :type="row.status === 'enabled' ? 'success' : 'info'">
              {{ row.status === "enabled" ? "启用" : "停用" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="在线状态" min-width="110">
          <template #default="{ row }">
            <el-tag :type="row.online_state === 'online' ? 'success' : 'danger'">
              {{ row.online_state === "online" ? "在线" : "离线" }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="监控密钥" prop="monitor_key" min-width="220" show-overflow-tooltip />
        <el-table-column label="操作" width="330" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text type="primary" @click="openChannels(row)">
              支付配置
            </el-button>
            <el-button size="small" text @click="openEdit(row)">编辑</el-button>
            <el-button size="small" text @click="handleResetKey(row)">
              重置密钥
            </el-button>
            <el-button size="small" text type="warning" @click="handleToggle(row)">
              {{ row.status === "enabled" ? "停用" : "启用" }}
            </el-button>
            <el-button size="small" text type="danger" @click="handleDelete(row)">
              删除
            </el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="total > limit"
        v-model:current-page="page"
        :page-size="limit"
        :total="total"
        layout="prev, pager, next, total"
        class="mt-4 justify-end"
        @current-change="loadList"
      />
    </el-card>

    <el-dialog v-model="dialogVisible" :title="dialogTitle" width="560px">
      <el-form label-width="120px">
        <el-form-item label="终端编码">
          <el-input v-model="form.terminalCode" placeholder="如 default-terminal / term-a" />
        </el-form-item>
        <el-form-item label="终端名称">
          <el-input v-model="form.terminalName" placeholder="请输入终端名称" />
        </el-form-item>
        <el-form-item label="分配顺序">
          <el-input-number v-model="form.dispatchPriority" :min="1" class="w-full" />
        </el-form-item>
        <el-form-item label="启用状态">
          <el-select v-model="form.status" class="w-full">
            <el-option label="启用" value="enabled" />
            <el-option label="停用" value="disabled" />
          </el-select>
        </el-form-item>
        <el-form-item label="监控密钥">
          <el-input v-model="form.monitorKey" placeholder="留空则后端自动生成" />
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

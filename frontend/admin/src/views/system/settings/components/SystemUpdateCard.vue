<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref } from "vue";
import { ElMessageBox } from "element-plus";

import {
  checkUpdate,
  getUpdateRecovery,
  getUpdateStatus,
  preflightUpdate,
  startUpdate,
  type UpdateCheckResponse
} from "@/api/admin/update";
import { message } from "@/utils/message";

import {
  canStartUpdate,
  normalizePreflightChecks,
  updateBadgeType,
  type PreflightCheck
} from "../updateState";

const release = ref<UpdateCheckResponse | null>(null);
const checks = ref<PreflightCheck[]>([]);
const recovery = ref<Record<string, unknown> | null>(null);
const runtimeStatus = ref<Record<string, unknown> | null>(null);
const checking = ref(false);
const preflighting = ref(false);
const updating = ref(false);
const loadingRecovery = ref(false);
const preflightOk = ref(false);
let statusPollingTimer: number | null = null;

const status = computed(() => release.value?.status ?? "unknown");
const canRunUpdate = computed(() =>
  canStartUpdate(status.value, preflightOk.value, updating.value)
);
const alertType = computed((): "success" | "warning" | "info" | "error" => {
  if (status.value === "up_to_date") return "success";
  if (status.value === "update_available") return "warning";
  if (status.value === "check_failed") return "error";
  return "info";
});
const statusTitle = computed(() => {
  if (!release.value) return "尚未检查";
  if (status.value === "update_available") return "发现可用更新";
  if (status.value === "up_to_date") return "当前已是最新版";
  if (status.value === "ahead") return "本地版本高于远程";
  if (status.value === "check_failed") return "检查失败";
  return release.value.message || "未知状态";
});
const releaseNotes = computed(() => {
  const body = String(release.value?.body ?? "").trim();
  if (body.length <= 900) return body;
  return `${body.slice(0, 900)}...`;
});
const zipSizeText = computed(() => formatSize(release.value?.assets?.zip?.size));
const recoveryMessage = computed(() => String(recovery.value?.message ?? ""));
const runtimeMessage = computed(() => String(runtimeStatus.value?.message ?? ""));

const errorMessage = (error: any, fallback: string) =>
  String(error?.msg ?? error?.message ?? fallback);

const resetPreflight = () => {
  checks.value = [];
  preflightOk.value = false;
};

const handleCheck = async () => {
  try {
    checking.value = true;
    resetPreflight();
    const res = await checkUpdate();

    if (res.code !== 1) {
      message(res.msg || "更新检查失败", { type: "error" });
      return;
    }

    release.value = res.data;
    if (res.data.status === "update_available") {
      message(`发现新版本 ${res.data.latest_version ?? ""}`, {
        type: "warning"
      });
      return;
    }

    message(res.data.message || "更新检查完成", {
      type: res.data.status === "check_failed" ? "error" : "success"
    });
  } catch (error: any) {
    message(errorMessage(error, "更新检查失败"), { type: "error" });
  } finally {
    checking.value = false;
  }
};

const handlePreflight = async () => {
  if (!release.value) {
    message("请先检查更新", { type: "warning" });
    return;
  }

  try {
    preflighting.value = true;
    const res = await preflightUpdate(release.value);

    if (res.code !== 1) {
      message(res.msg || "环境预检失败", { type: "error" });
      return;
    }

    checks.value = normalizePreflightChecks(res.data?.checks);
    preflightOk.value =
      (res.data as any)?.can_update === true || (res.data as any)?.ok === true;
    message(preflightOk.value ? "环境预检通过" : "预检未通过，请先处理失败项", {
      type: preflightOk.value ? "success" : "error"
    });
  } catch (error: any) {
    message(errorMessage(error, "环境预检失败"), { type: "error" });
  } finally {
    preflighting.value = false;
  }
};

const handleStart = async () => {
  if (!release.value || !canRunUpdate.value) {
    message("请先完成更新检查和环境预检", { type: "warning" });
    return;
  }

  const confirmed = await ElMessageBox.confirm(
    "自动更新会先备份当前程序文件，再覆盖新版文件并执行数据库迁移。执行期间请勿刷新或关闭页面。",
    "确认开始更新",
    {
      type: "warning",
      confirmButtonText: "开始更新",
      cancelButtonText: "取消"
    }
  ).catch(() => false);

  if (!confirmed) return;

  try {
    updating.value = true;
    runtimeStatus.value = {
      stage: "download",
      message: "更新任务已开始，正在等待服务器反馈"
    };
    startStatusPolling();
    const res = await startUpdate(release.value);

    if (res.code !== 1) {
      message(res.msg || "自动更新失败", { type: "error" });
      await loadRecovery();
      return;
    }

    message("更新完成，建议刷新后台页面加载新版本资源", { type: "success" });
    runtimeStatus.value = {
      stage: "complete",
      message: "更新完成，建议刷新页面"
    };
    recovery.value = null;
    preflightOk.value = false;
  } catch (error: any) {
    message(errorMessage(error, "自动更新失败"), { type: "error" });
    await loadRecovery();
  } finally {
    stopStatusPolling();
    await loadStatus();
    updating.value = false;
  }
};

const loadRecovery = async () => {
  try {
    loadingRecovery.value = true;
    const res = await getUpdateRecovery();
    const data = res.code === 1 ? res.data : null;
    recovery.value = data && Object.keys(data).length > 0 ? data : null;
  } catch {
    recovery.value = null;
  } finally {
    loadingRecovery.value = false;
  }
};

const loadStatus = async () => {
  try {
    const res = await getUpdateStatus();
    const data = res.code === 1 ? res.data : null;
    runtimeStatus.value = data && Object.keys(data).length > 0 ? data : null;
  } catch {
    runtimeStatus.value = null;
  }
};

const startStatusPolling = () => {
  stopStatusPolling();
  void loadStatus();
  statusPollingTimer = window.setInterval(() => {
    void loadStatus();
  }, 2000);
};

const stopStatusPolling = () => {
  if (statusPollingTimer !== null) {
    window.clearInterval(statusPollingTimer);
    statusPollingTimer = null;
  }
};

const formatSize = (value: unknown) => {
  const size = Number(value ?? 0);
  if (!Number.isFinite(size) || size <= 0) return "未知大小";
  if (size < 1024 * 1024) return `${(size / 1024).toFixed(1)} KB`;
  return `${(size / 1024 / 1024).toFixed(2)} MB`;
};

const formatDate = (value?: string) => {
  if (!value) return "未知时间";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return value;
  return date.toLocaleString();
};

onMounted(() => {
  void loadStatus();
  void loadRecovery();
});

onUnmounted(() => {
  stopStatusPolling();
});
</script>

<template>
  <el-card shadow="hover" class="overflow-hidden">
    <template #header>
      <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <div class="text-base font-medium">程序自动更新</div>
            <el-tag :type="updateBadgeType(status)" effect="light">
              {{ statusTitle }}
            </el-tag>
          </div>
          <div class="text-sm text-gray-500">
            从 GitHub Release 检测版本、校验发布包、备份文件并执行数据库迁移。
          </div>
        </div>

        <div class="flex flex-wrap gap-2">
          <el-button :loading="checking" @click="handleCheck">
            检查更新
          </el-button>
          <el-button
            type="primary"
            plain
            :disabled="status !== 'update_available'"
            :loading="preflighting"
            @click="handlePreflight"
          >
            环境预检
          </el-button>
          <el-button
            type="danger"
            :disabled="!canRunUpdate"
            :loading="updating"
            @click="handleStart"
          >
            立即更新
          </el-button>
        </div>
      </div>
    </template>

    <div class="space-y-4">
      <el-alert
        v-if="!release"
        type="info"
        :closable="false"
        title="建议先点击“检查更新”。只有检测到正式 GitHub Release 且预检全部通过后，才允许执行自动更新。"
      />

      <el-alert
        v-else
        :type="alertType"
        :closable="false"
        :title="release.message || statusTitle"
        show-icon
      />

      <div
        v-if="release"
        class="grid gap-3 rounded-lg bg-slate-50 p-4 text-sm text-slate-700 sm:grid-cols-2 lg:grid-cols-4"
      >
        <div>
          <div class="text-xs text-gray-500">当前版本</div>
          <div class="font-medium">v{{ release.current_version || "-" }}</div>
        </div>
        <div>
          <div class="text-xs text-gray-500">最新版本</div>
          <div class="font-medium">v{{ release.latest_version || "-" }}</div>
        </div>
        <div>
          <div class="text-xs text-gray-500">安装包大小</div>
          <div class="font-medium">{{ zipSizeText }}</div>
        </div>
        <div>
          <div class="text-xs text-gray-500">发布时间</div>
          <div class="font-medium">{{ formatDate(release.published_at) }}</div>
        </div>
      </div>

      <div v-if="checks.length > 0" class="space-y-3">
        <div class="flex items-center justify-between">
          <div class="text-sm font-medium">预检结果</div>
          <el-tag :type="preflightOk ? 'success' : 'danger'">
            {{ preflightOk ? "全部通过" : "存在阻断项" }}
          </el-tag>
        </div>

        <div class="grid gap-2 md:grid-cols-2">
          <div
            v-for="item in checks"
            :key="item.label"
            class="flex items-start gap-3 rounded-lg border border-slate-100 p-3"
          >
            <el-tag :type="item.ok ? 'success' : 'danger'" size="small">
              {{ item.ok ? "通过" : "失败" }}
            </el-tag>
            <div class="min-w-0">
              <div class="text-sm font-medium text-slate-700">
                {{ item.label }}
              </div>
              <div class="mt-1 text-xs leading-5 text-gray-500">
                {{ item.message || "无附加信息" }}
              </div>
            </div>
          </div>
        </div>
      </div>

      <el-alert
        v-if="runtimeMessage"
        type="info"
        :closable="false"
        show-icon
        :title="runtimeMessage"
      />

      <el-alert
        v-if="recoveryMessage"
        type="error"
        :closable="false"
        show-icon
        title="上次自动更新失败"
      >
        <template #default>
          <div class="space-y-1">
            <div>{{ recoveryMessage }}</div>
            <div v-if="recovery?.backup_path" class="text-xs">
              备份文件：{{ recovery.backup_path }}
            </div>
          </div>
        </template>
      </el-alert>

      <div v-if="releaseNotes" class="rounded-lg border border-slate-100 p-4">
        <div class="mb-2 text-sm font-medium">Release 说明</div>
        <pre class="max-h-48 overflow-auto whitespace-pre-wrap text-xs leading-5 text-gray-600">{{ releaseNotes }}</pre>
      </div>

      <div class="flex flex-wrap items-center justify-between gap-3 text-xs text-gray-500">
        <span>
          自动更新会保留 .env、runtime/ 和运行状态目录；数据库更新前仍建议先做一次手动备份。
        </span>
        <div class="flex gap-2">
          <el-button
            link
            type="primary"
            :loading="loadingRecovery"
            @click="loadRecovery"
          >
            查看失败记录
          </el-button>
          <el-link
            v-if="release?.release_url"
            type="primary"
            :href="release.release_url"
            target="_blank"
          >
            打开 GitHub Release
          </el-link>
        </div>
      </div>
    </div>
  </el-card>
</template>

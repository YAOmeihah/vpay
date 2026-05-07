<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { getDashboardStats } from "@/api/admin/dashboard";
import StatCard from "@/components/admin/StatCard.vue";
import {
  buildDashboardOperationSummary,
  mapDashboardStats
} from "@/utils/adminLegacy";

defineOptions({ name: "Dashboard" });

const loading = ref(true);
const stats = ref<any>({});
const loadError = ref("");
const viewStats = computed(() => mapDashboardStats(stats.value));
const operationSummary = computed(() =>
  buildDashboardOperationSummary(viewStats.value)
);

const loadStats = async () => {
  try {
    loading.value = true;
    loadError.value = "";
    const res = await getDashboardStats();
    if (res.code === 1) {
      stats.value = res.data;
    } else {
      loadError.value = res.msg || "控制台数据加载失败";
    }
  } catch (error: any) {
    loadError.value = error?.msg || error?.message || "控制台数据加载失败";
  } finally {
    loading.value = false;
  }
};

onMounted(loadStats);
</script>

<template>
  <div class="dashboard-page p-4">
    <div v-if="loadError" class="mb-4 flex flex-wrap items-center gap-2">
      <el-alert
        :title="loadError"
        type="error"
        show-icon
        :closable="false"
        class="dashboard-error"
      />
      <el-button type="danger" text @click="loadStats">重新加载</el-button>
    </div>

    <el-row v-loading="loading" :gutter="16" class="dashboard-stat-grid">
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="今日总订单"
          :value="viewStats.todayOrder"
          icon="ri:file-list-line"
          tone="primary"
        />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="今日成功订单"
          :value="viewStats.todaySuccessOrder"
          icon="ri:checkbox-circle-line"
          tone="success"
        />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="今日失败订单"
          :value="viewStats.todayCloseOrder"
          icon="ri:close-circle-line"
          tone="danger"
        />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="今日收入"
          :value="viewStats.todayMoney"
          icon="ri:money-cny-circle-line"
          tone="warning"
        />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="总收入"
          :value="viewStats.countMoney"
          icon="ri:money-cny-box-line"
          tone="primary"
        />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard
          title="总成功订单"
          :value="viewStats.countOrder"
          icon="ri:file-list-3-line"
          tone="success"
        />
      </el-col>
    </el-row>

    <el-card class="mt-4" shadow="never">
      <template #header>
        <div class="flex items-center justify-between">
          <span>今日运营摘要</span>
          <el-tag :type="operationSummary.statusType">
            {{ operationSummary.statusText }}
          </el-tag>
        </div>
      </template>
      <el-row :gutter="16">
        <el-col :xs="24" :md="8">
          <div class="dashboard-summary-item">
            <span>成功率</span>
            <strong>{{ operationSummary.successRate }}</strong>
            <el-progress
              :percentage="operationSummary.successPercentage"
              :status="operationSummary.progressStatus"
            />
          </div>
        </el-col>
        <el-col :xs="24" :md="8">
          <div class="dashboard-summary-item">
            <span>未成功订单</span>
            <strong>{{ operationSummary.unfinishedOrderCount }}</strong>
            <small>今日总订单 - 今日成功订单</small>
          </div>
        </el-col>
        <el-col :xs="24" :md="8">
          <div class="dashboard-summary-item">
            <span>处理建议</span>
            <p>{{ operationSummary.actionText }}</p>
          </div>
        </el-col>
      </el-row>
    </el-card>

    <el-card class="mt-4" shadow="hover">
      <template #header><span>系统信息</span></template>
      <el-descriptions
        class="dashboard-desktop-system admin-desktop-only"
        :column="2"
        border
      >
        <el-descriptions-item label="PHP 版本">{{
          stats.PHP_VERSION
        }}</el-descriptions-item>
        <el-descriptions-item label="操作系统">{{
          stats.PHP_OS
        }}</el-descriptions-item>
        <el-descriptions-item label="服务器">{{
          stats.SERVER
        }}</el-descriptions-item>
        <el-descriptions-item label="MySQL">{{
          stats.MySql
        }}</el-descriptions-item>
        <el-descriptions-item label="ThinkPHP">{{
          stats.Thinkphp
        }}</el-descriptions-item>
        <el-descriptions-item label="运行时间">{{
          stats.RunTime
        }}</el-descriptions-item>
        <el-descriptions-item label="版本">{{
          stats.ver
        }}</el-descriptions-item>
        <el-descriptions-item label="GD 库">{{
          stats.gd
        }}</el-descriptions-item>
      </el-descriptions>

      <div class="dashboard-mobile-system admin-mobile-only">
        <div class="dashboard-mobile-system__item">
          <span>PHP 版本</span>
          <strong>{{ stats.PHP_VERSION || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>操作系统</span>
          <strong>{{ stats.PHP_OS || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>服务器</span>
          <strong>{{ stats.SERVER || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>MySQL</span>
          <strong>{{ stats.MySql || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>ThinkPHP</span>
          <strong>{{ stats.Thinkphp || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>运行时间</span>
          <strong>{{ stats.RunTime || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>版本</span>
          <strong>{{ stats.ver || "无" }}</strong>
        </div>
        <div class="dashboard-mobile-system__item">
          <span>GD 库</span>
          <strong>{{ stats.gd || "无" }}</strong>
        </div>
      </div>
    </el-card>
  </div>
</template>

<style scoped>
.dashboard-error {
  flex: 1 1 280px;
}

.dashboard-summary-item {
  min-height: 112px;
  padding: 14px 16px;
  border: 1px solid var(--el-border-color-lighter);
  border-radius: 6px;
  background: var(--el-bg-color-page);
}

.dashboard-summary-item span {
  display: block;
  margin-bottom: 8px;
  font-size: 13px;
  color: var(--el-text-color-secondary);
}

.dashboard-summary-item strong {
  display: block;
  margin-bottom: 10px;
  font-size: 26px;
  line-height: 1.1;
  color: var(--el-text-color-primary);
}

.dashboard-summary-item small,
.dashboard-summary-item p {
  margin: 0;
  font-size: 13px;
  line-height: 1.6;
  color: var(--el-text-color-secondary);
}

.dashboard-mobile-system {
  display: grid;
  gap: 10px;
}

.dashboard-mobile-system__item {
  min-width: 0;
  padding-bottom: 10px;
  border-bottom: 1px solid var(--el-border-color-lighter);
}

.dashboard-mobile-system__item:last-child {
  padding-bottom: 0;
  border-bottom: 0;
}

.dashboard-mobile-system__item span {
  display: block;
  margin-bottom: 4px;
  font-size: 12px;
  color: var(--el-text-color-secondary);
}

.dashboard-mobile-system__item strong {
  display: block;
  overflow-wrap: anywhere;
  font-size: 14px;
  font-weight: 500;
  color: var(--el-text-color-primary);
}

@media (max-width: 768px) {
  .dashboard-page :deep(.el-card__header) {
    padding: 12px;
  }

  .dashboard-page :deep(.el-card__body) {
    padding: 12px;
  }

  .dashboard-summary-item {
    min-height: auto;
  }

  .dashboard-summary-item strong {
    font-size: 22px;
  }
}
</style>

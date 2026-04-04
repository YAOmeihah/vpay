<script setup lang="ts">
import { computed, ref, onMounted } from "vue";
import { getDashboardStats } from "@/api/admin/dashboard";
import StatCard from "@/components/admin/StatCard.vue";
import { mapDashboardStats } from "@/utils/adminLegacy";

defineOptions({ name: "Dashboard" });

const loading = ref(true);
const stats = ref<any>({});
const viewStats = computed(() => mapDashboardStats(stats.value));

const loadStats = async () => {
  try {
    loading.value = true;
    const res = await getDashboardStats();
    if (res.code === 1) stats.value = res.data;
  } finally {
    loading.value = false;
  }
};

onMounted(loadStats);
</script>

<template>
  <div class="p-4">
    <el-row :gutter="16" v-loading="loading">
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="今日总订单" :value="viewStats.todayOrder" icon="ri:file-list-line" tone="primary" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="今日成功订单" :value="viewStats.todaySuccessOrder" icon="ri:checkbox-circle-line" tone="success" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="今日失败订单" :value="viewStats.todayCloseOrder" icon="ri:close-circle-line" tone="danger" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="今日收入" :value="viewStats.todayMoney" icon="ri:money-cny-circle-line" tone="warning" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="总收入" :value="viewStats.countMoney" icon="ri:money-cny-box-line" tone="primary" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="总成功订单" :value="viewStats.countOrder" icon="ri:file-list-3-line" tone="success" />
      </el-col>
    </el-row>

    <el-card class="mt-4" shadow="hover">
      <template #header><span>系统信息</span></template>
      <el-descriptions :column="2" border>
        <el-descriptions-item label="PHP 版本">{{ stats.PHP_VERSION }}</el-descriptions-item>
        <el-descriptions-item label="操作系统">{{ stats.PHP_OS }}</el-descriptions-item>
        <el-descriptions-item label="服务器">{{ stats.SERVER }}</el-descriptions-item>
        <el-descriptions-item label="MySQL">{{ stats.MySql }}</el-descriptions-item>
        <el-descriptions-item label="ThinkPHP">{{ stats.Thinkphp }}</el-descriptions-item>
        <el-descriptions-item label="运行时间">{{ stats.RunTime }}</el-descriptions-item>
        <el-descriptions-item label="版本">{{ stats.ver }}</el-descriptions-item>
        <el-descriptions-item label="GD 库">{{ stats.gd }}</el-descriptions-item>
      </el-descriptions>
    </el-card>
  </div>
</template>

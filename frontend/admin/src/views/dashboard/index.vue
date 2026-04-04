<script setup lang="ts">
import { ref, onMounted } from "vue";
import { getDashboardStats } from "@/api/admin/dashboard";
import StatCard from "@/components/admin/StatCard.vue";

defineOptions({ name: "Dashboard" });

const loading = ref(true);
const stats = ref<any>({});

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
        <StatCard title="今日订单" :value="stats.today_order ?? 0" icon="ri:file-list-line" tone="primary" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="今日金额" :value="stats.today_money ?? '0.00'" icon="ri:money-cny-circle-line" tone="success" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="总订单" :value="stats.order ?? 0" icon="ri:file-list-3-line" tone="warning" />
      </el-col>
      <el-col :xs="24" :sm="12" :md="6">
        <StatCard title="总金额" :value="stats.money ?? '0.00'" icon="ri:money-cny-box-line" tone="danger" />
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

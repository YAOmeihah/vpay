<script setup lang="ts">
defineProps<{
  title: string;
  value: string | number;
  icon: string;
  tone: string;
}>();
</script>

<template>
  <el-card shadow="hover" class="stat-card">
    <div class="stat-card__body">
      <IconifyIconOnline :icon="icon" class="stat-card__icon" />
      <div>
        <div class="stat-card__value">{{ value }}</div>
        <div class="stat-card__title">{{ title }}</div>
      </div>
    </div>
  </el-card>
</template>

<style scoped>
.stat-card__body {
  display: flex;
  align-items: center;
  gap: 1rem;
}
.stat-card__icon {
  font-size: 2.5rem;
  color: var(--el-color-primary);
}
.stat-card__value {
  font-size: 1.5rem;
  font-weight: bold;
  color: var(--el-text-color-primary);
}
.stat-card__title {
  font-size: 0.875rem;
  color: var(--el-text-color-secondary);
}
</style>

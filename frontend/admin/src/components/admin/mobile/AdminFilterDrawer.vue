<script setup lang="ts">
defineOptions({ name: "AdminFilterDrawer" });

defineProps<{
  modelValue: boolean;
  title?: string;
}>();

defineEmits<{
  "update:modelValue": [value: boolean];
  apply: [];
  reset: [];
}>();
</script>

<template>
  <el-drawer
    :model-value="modelValue"
    :title="title || '筛选'"
    direction="btt"
    size="78%"
    class="admin-filter-drawer"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <div class="admin-filter-drawer__body">
      <slot />
    </div>
    <template #footer>
      <div class="admin-filter-drawer__footer">
        <el-button @click="$emit('reset')">重置</el-button>
        <el-button type="primary" @click="$emit('apply')">应用筛选</el-button>
      </div>
    </template>
  </el-drawer>
</template>

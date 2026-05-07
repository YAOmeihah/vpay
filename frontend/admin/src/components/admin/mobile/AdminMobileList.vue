<script setup lang="ts">
defineOptions({ name: "AdminMobileList" });

defineProps<{
  loading?: boolean;
  empty?: boolean;
  emptyText?: string;
}>();
</script>

<template>
  <div v-loading="loading" class="admin-mobile-list">
    <el-empty
      v-if="empty && !loading"
      :description="emptyText || '暂无数据'"
      :image-size="96"
    />
    <div v-else class="admin-mobile-list__items">
      <slot />
    </div>
  </div>
</template>

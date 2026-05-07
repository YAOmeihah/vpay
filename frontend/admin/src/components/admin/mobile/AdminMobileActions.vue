<script setup lang="ts">
import type { AdminMobileMoreAction, AdminMobileMoreActionType } from "./types";

defineOptions({ name: "AdminMobileActions" });

defineProps<{
  primaryText: string;
  primaryType?: AdminMobileMoreActionType;
  primaryDisabled?: boolean;
  primaryLoading?: boolean;
  moreActions?: AdminMobileMoreAction[];
}>();

defineEmits<{
  primary: [];
  more: [command: string];
}>();
</script>

<template>
  <div class="admin-mobile-actions">
    <el-button
      class="admin-mobile-actions__primary"
      :type="primaryType || 'primary'"
      :disabled="primaryDisabled"
      :loading="primaryLoading"
      @click="$emit('primary')"
    >
      {{ primaryText }}
    </el-button>
    <el-dropdown
      v-if="moreActions?.length"
      trigger="click"
      @command="command => $emit('more', String(command))"
    >
      <el-button class="admin-mobile-actions__more">更多</el-button>
      <template #dropdown>
        <el-dropdown-menu>
          <el-dropdown-item
            v-for="action in moreActions"
            :key="action.command"
            :command="action.command"
            :disabled="action.disabled || action.loading"
          >
            <span
              class="admin-mobile-actions__menu-item"
              :class="`admin-mobile-actions__menu-item--${action.type || 'info'}`"
            >
              {{ action.loading ? "处理中..." : action.label }}
            </span>
          </el-dropdown-item>
        </el-dropdown-menu>
      </template>
    </el-dropdown>
  </div>
</template>

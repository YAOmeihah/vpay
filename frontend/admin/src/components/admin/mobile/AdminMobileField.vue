<script setup lang="ts">
defineOptions({ name: "AdminMobileField" });

withDefaults(
  defineProps<{
    label: string;
    value?: string | number | null;
    truncate?: boolean;
  }>(),
  {
    truncate: false
  }
);
</script>

<template>
  <div class="admin-mobile-field">
    <span class="admin-mobile-field__label">{{ label }}</span>
    <span
      class="admin-mobile-field__value"
      :class="{ 'admin-mobile-field__value--truncate': truncate }"
    >
      <slot>{{ value ?? "无" }}</slot>
    </span>
  </div>
</template>

<script setup lang="ts">
defineOptions({ name: "AdminMobileRecordCard" });

defineProps<{
  title: string;
  subtitle?: string;
}>();
</script>

<template>
  <article class="admin-mobile-record-card">
    <header class="admin-mobile-record-card__header">
      <div class="admin-mobile-record-card__title">
        <strong>{{ title }}</strong>
        <small v-if="subtitle">{{ subtitle }}</small>
      </div>
      <slot name="status" />
    </header>
    <div class="admin-mobile-record-card__body">
      <slot />
    </div>
    <footer v-if="$slots.actions" class="admin-mobile-record-card__actions">
      <slot name="actions" />
    </footer>
  </article>
</template>

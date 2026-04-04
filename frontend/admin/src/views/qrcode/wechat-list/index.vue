<script setup lang="ts">
import QrList from "@/components/admin/QrList.vue";
defineOptions({ name: "WechatQrcodeList" });
</script>

<template>
  <QrList :type="1" title="微信二维码管理" />
</template>

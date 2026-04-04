<script setup lang="ts">
import QrBatchUploader from "@/components/admin/QrBatchUploader.vue";
defineOptions({ name: "WechatQrcodeAdd" });
</script>

<template>
  <QrBatchUploader
    :type="1"
    title="添加微信二维码"
    scan-hint="选择微信有金额二维码后逐条确认金额并保存"
  />
</template>

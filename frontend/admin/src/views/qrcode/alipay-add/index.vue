<script setup lang="ts">
import QrBatchUploader from "@/components/admin/QrBatchUploader.vue";
defineOptions({ name: "AlipayQrcodeAdd" });
</script>

<template>
  <QrBatchUploader
    :type="2"
    title="添加支付宝二维码"
    scan-hint="选择支付宝有金额二维码后逐条确认金额并保存"
  />
</template>

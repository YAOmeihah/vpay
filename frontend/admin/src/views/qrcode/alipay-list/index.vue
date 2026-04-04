<script setup lang="ts">
import QrList from "@/components/admin/QrList.vue";
defineOptions({ name: "AlipayQrcodeList" });
</script>

<template>
  <QrList :type="2" title="支付宝二维码管理" />
</template>

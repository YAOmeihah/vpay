<script setup lang="ts">
defineProps<{
  modelValue: boolean;
  order: any;
}>();

defineEmits<{
  "update:modelValue": [val: boolean];
}>();

type TagType = "primary" | "success" | "warning" | "danger" | "info";
const STATE_MAP: Record<number, { label: string; type: TagType }> = {
  2: { label: "通知失败", type: "warning" },
  1: { label: "完成", type: "success" },
  0: { label: "待支付", type: "info" },
  [-1]: { label: "过期", type: "danger" }
};

const TYPE_MAP: Record<number, string> = { 1: "微信", 2: "支付宝" };
</script>

<template>
  <el-dialog
    :model-value="modelValue"
    title="订单详情"
    width="600px"
    @update:model-value="$emit('update:modelValue', $event)"
  >
    <el-descriptions v-if="order" :column="1" border>
      <el-descriptions-item label="云端订单编号">{{ order.order_id }}</el-descriptions-item>
      <el-descriptions-item label="商户订单编号">{{ order.pay_id }}</el-descriptions-item>
      <el-descriptions-item label="商户编号">{{ order.mch_id }}</el-descriptions-item>
      <el-descriptions-item label="支付方式">{{ TYPE_MAP[order.type] ?? order.type }}</el-descriptions-item>
      <el-descriptions-item label="订单金额">{{ order.price }}</el-descriptions-item>
      <el-descriptions-item label="实际金额">{{ order.really_price }}</el-descriptions-item>
      <el-descriptions-item label="状态">
        <el-tag :type="STATE_MAP[order.state]?.type ?? 'info'">
          {{ STATE_MAP[order.state]?.label ?? order.state }}
        </el-tag>
      </el-descriptions-item>
      <el-descriptions-item label="自定义参数">{{ order.param || "无" }}</el-descriptions-item>
      <el-descriptions-item label="创建时间">{{ order.create_date }}</el-descriptions-item>
      <el-descriptions-item label="支付时间">{{ order.pay_date || "—" }}</el-descriptions-item>
      <el-descriptions-item label="关闭时间">{{ order.close_date || "—" }}</el-descriptions-item>
    </el-descriptions>
    <template #footer>
      <el-button @click="$emit('update:modelValue', false)">关闭</el-button>
    </template>
  </el-dialog>
</template>

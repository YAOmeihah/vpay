<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { message } from "@/utils/message";
import { ElMessageBox } from "element-plus";
import {
  getOrders,
  deleteOrder,
  repairOrder,
  deleteExpiredOrders,
  deleteOldOrders
} from "@/api/admin/orders";
import OrderDetailDialog from "@/components/admin/OrderDetailDialog.vue";
import { formatUnixTimestamp, normalizePagedList } from "@/utils/adminLegacy";
import { resolveRepairAction } from "./orderActions";

defineOptions({ name: "OrderList" });

type TagType = "primary" | "success" | "warning" | "danger" | "info";
const STATE_MAP: Record<number, { label: string; type: TagType }> = {
  2: { label: "通知失败", type: "warning" },
  1: { label: "完成", type: "success" },
  0: { label: "待支付", type: "info" },
  [-1]: { label: "过期", type: "danger" }
};

const TYPE_MAP: Record<number, string> = { 1: "微信", 2: "支付宝" };

const formatTerminalOwnership = (row: any) => {
  const name = String(row.terminal_snapshot ?? "").trim();
  const code = String(row.terminal_code ?? "").trim();

  if (name !== "" && code !== "") {
    return `${name} / ${code}`;
  }

  return name || code || "-";
};

const loading = ref(false);
const list = ref<any[]>([]);
const total = ref(0);

const filters = reactive({ type: "", state: "", page: 1 });
const limit = 15;

const detailVisible = ref(false);
const selectedOrder = ref<any>(null);

const loadList = async () => {
  try {
    loading.value = true;
    const res = await getOrders({
      page: filters.page,
      limit,
      type: filters.type || undefined,
      state: filters.state || undefined
    });
    if (res.code === 1) {
      const { items, total: count } = normalizePagedList(res);
      list.value = items;
      total.value = count;
    } else {
      list.value = [];
      total.value = 0;
      message(res.msg || "订单列表加载失败", { type: "error" });
    }
  } catch (error: any) {
    list.value = [];
    total.value = 0;
    message(error?.msg || error?.message || "订单列表加载失败", {
      type: "error"
    });
  } finally {
    loading.value = false;
  }
};

const onSearch = () => {
  filters.page = 1;
  loadList();
};

const openDetail = (row: any) => {
  selectedOrder.value = row;
  detailVisible.value = true;
};

const handleDelete = async (row: any) => {
  await ElMessageBox.confirm("确认删除该订单？", "提示", { type: "warning" });
  const res = await deleteOrder({ id: row.id });
  if (res.code === 1) {
    message("删除成功", { type: "success" });
    loadList();
  } else {
    message(res.msg || "删除失败", { type: "error" });
  }
};

const handleRepair = async (row: any) => {
  const action = resolveRepairAction(Number(row.state));
  if (!action) {
    return;
  }

  await ElMessageBox.confirm(action.confirmMessage, "提示", { type: "warning" });
  const res = await repairOrder({ id: row.id });
  if (res.code === 1) {
    message(action.successMessage, { type: "success" });
    loadList();
  } else if (res.code === -2 && res.data) {
    try {
      await ElMessageBox.confirm(
        action.notifyErrorMessage,
        "提示",
        {
          confirmButtonText: "查看",
          cancelButtonText: "取消",
          type: "warning"
        }
      );
      await ElMessageBox.alert(String(res.data), "通知返回数据", {
        dangerouslyUseHTMLString: false
      });
    } catch {}
  } else {
    message(res.msg || action.failureMessage, { type: "error" });
  }
};

const handleDeleteExpired = async () => {
  await ElMessageBox.confirm("确认删除所有过期订单？", "提示", { type: "warning" });
  const res = await deleteExpiredOrders();
  message(res.code === 1 ? "操作成功" : res.msg || "操作失败", {
    type: res.code === 1 ? "success" : "error"
  });
  if (res.code === 1) loadList();
};

const handleDeleteOld = async () => {
  await ElMessageBox.confirm("确认删除七天前的订单？此操作不可恢复", "提示", { type: "warning" });
  const res = await deleteOldOrders();
  message(res.code === 1 ? "操作成功" : res.msg || "操作失败", {
    type: res.code === 1 ? "success" : "error"
  });
  if (res.code === 1) loadList();
};

onMounted(loadList);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header>
        <div class="flex items-center justify-between">
          <span>订单列表</span>
          <div class="flex gap-2">
            <el-button type="warning" size="small" @click="handleDeleteExpired">
              删除所有过期订单
            </el-button>
            <el-button type="danger" size="small" @click="handleDeleteOld">
              删除七天前订单
            </el-button>
          </div>
        </div>
      </template>

      <!-- 过滤栏 -->
      <div class="flex gap-3 mb-4">
        <el-select
          v-model="filters.type"
          placeholder="支付类型"
          clearable
          style="width: 130px"
          @change="onSearch"
        >
          <el-option label="微信" value="1" />
          <el-option label="支付宝" value="2" />
        </el-select>
        <el-select
          v-model="filters.state"
          placeholder="订单状态"
          clearable
          style="width: 130px"
          @change="onSearch"
        >
          <el-option label="过期" value="-1" />
          <el-option label="待支付" value="0" />
          <el-option label="完成" value="1" />
          <el-option label="通知失败" value="2" />
        </el-select>
        <el-button type="primary" @click="onSearch">搜索</el-button>
      </div>

      <!-- 表格 -->
      <el-table :data="list" v-loading="loading" border empty-text="暂无订单数据">
        <el-table-column label="创建时间" width="180">
          <template #default="{ row }">
            {{ formatUnixTimestamp(row.create_date) }}
          </template>
        </el-table-column>
        <el-table-column label="商户订单号" prop="pay_id" min-width="160" show-overflow-tooltip />
        <el-table-column label="云端订单号" prop="order_id" min-width="160" show-overflow-tooltip />
        <el-table-column label="所属终端" min-width="220" show-overflow-tooltip>
          <template #default="{ row }">
            {{ formatTerminalOwnership(row) }}
          </template>
        </el-table-column>
        <el-table-column label="类型" width="80">
          <template #default="{ row }">{{ TYPE_MAP[row.type] ?? row.type }}</template>
        </el-table-column>
        <el-table-column label="订单金额" prop="price" width="100" />
        <el-table-column label="实际金额" prop="really_price" width="100" />
        <el-table-column label="状态" width="100">
          <template #default="{ row }">
            <el-tag :type="STATE_MAP[row.state]?.type ?? 'info'">
              {{ STATE_MAP[row.state]?.label ?? row.state }}
            </el-tag>
          </template>
        </el-table-column>
        <el-table-column label="操作" width="180" fixed="right">
          <template #default="{ row }">
            <el-button size="small" text @click="openDetail(row)">详情</el-button>
            <el-button
              v-if="resolveRepairAction(row.state)"
              size="small"
              text
              :type="row.state === 0 ? 'warning' : 'primary'"
              @click="handleRepair(row)"
            >
              {{ resolveRepairAction(row.state)?.label }}
            </el-button>
            <el-button size="small" text type="danger" @click="handleDelete(row)">删除</el-button>
          </template>
        </el-table-column>
      </el-table>

      <el-pagination
        v-if="total > limit"
        v-model:current-page="filters.page"
        :page-size="limit"
        :total="total"
        layout="prev, pager, next, total"
        class="mt-4 justify-end"
        @current-change="loadList"
      />
    </el-card>

    <OrderDetailDialog v-model="detailVisible" :order="selectedOrder" />
  </div>
</template>

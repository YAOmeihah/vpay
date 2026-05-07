<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { copyTextToClipboard } from "@pureadmin/utils";
import { message } from "@/utils/message";
import { ElMessageBox } from "element-plus";
import {
  AdminFilterDrawer,
  AdminMobileActions,
  AdminMobileField,
  AdminMobileList,
  AdminMobileRecordCard,
  type AdminMobileMoreAction
} from "@/components/admin/mobile";
import {
  getOrders,
  deleteOrder,
  repairOrder,
  deleteExpiredOrders,
  deleteOldOrders
} from "@/api/admin/orders";
import OrderDetailDialog from "@/components/admin/OrderDetailDialog.vue";
import { formatUnixTimestamp, normalizePagedList } from "@/utils/adminLegacy";
import {
  buildDeleteExpiredOrdersConfirmMessage,
  buildDeleteOldOrdersConfirmMessage,
  buildDeleteOrderConfirmMessage,
  buildRepairConfirmMessage,
  resolveRepairAction
} from "./orderActions";
import { resolveOrderCopyText, type OrderCopyField } from "./orderCopy";
import {
  applyOrderStatePreset,
  buildOrderQueryParams,
  createDefaultOrderFilters,
  ORDER_STATE_PRESETS
} from "./orderFilters";

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

const filters = reactive(createDefaultOrderFilters());
const limit = 15;
const orderStatePresets = ORDER_STATE_PRESETS;

const detailVisible = ref(false);
const selectedOrder = ref<any>(null);
const mobileFilterVisible = ref(false);
const rowActionLoading = reactive<Record<string, boolean>>({});
const bulkActionLoading = reactive({ expired: false, old: false });

const rowActionKey = (action: string, row: any) => {
  return `${action}:${String(row.id ?? "")}`;
};

const isRowActionLoading = (action: string, row: any) => {
  return Boolean(rowActionLoading[rowActionKey(action, row)]);
};

const setRowActionLoading = (action: string, row: any, value: boolean) => {
  rowActionLoading[rowActionKey(action, row)] = value;
};

const loadList = async () => {
  try {
    loading.value = true;
    const res = await getOrders(buildOrderQueryParams(filters, limit));
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

const onResetFilters = () => {
  Object.assign(filters, createDefaultOrderFilters());
  loadList();
};

const applyStatePreset = (state: string) => {
  applyOrderStatePreset(filters, state);
  loadList();
};

const copyOrderField = (row: any, field: OrderCopyField) => {
  const text = resolveOrderCopyText(row, field);
  if (!text) {
    message("暂无可复制内容", { type: "warning" });
    return;
  }

  const success = copyTextToClipboard(text);
  message(success ? "已复制" : "复制失败", {
    type: success ? "success" : "error"
  });
};

const openDetail = (row: any) => {
  selectedOrder.value = row;
  detailVisible.value = true;
};

const handleDelete = async (row: any) => {
  if (isRowActionLoading("delete", row)) {
    return;
  }

  try {
    await ElMessageBox.confirm(buildDeleteOrderConfirmMessage(row), "提示", {
      type: "warning",
      confirmButtonText: "删除",
      confirmButtonClass: "el-button--danger"
    });
    setRowActionLoading("delete", row, true);
    const res = await deleteOrder({ id: row.id });
    if (res.code === 1) {
      message("删除成功", { type: "success" });
      await loadList();
    } else {
      message(res.msg || "删除失败", { type: "error" });
    }
  } catch (error: any) {
    if (error !== "cancel" && error !== "close") {
      message(error?.msg || error?.message || "删除失败", { type: "error" });
    }
  } finally {
    setRowActionLoading("delete", row, false);
  }
};

const handleRepair = async (row: any) => {
  if (isRowActionLoading("repair", row)) {
    return;
  }

  const action = resolveRepairAction(Number(row.state));
  if (!action) {
    return;
  }

  try {
    await ElMessageBox.confirm(buildRepairConfirmMessage(action, row), "提示", {
      type: "warning"
    });
    setRowActionLoading("repair", row, true);
    const res = await repairOrder({ id: row.id });
    if (res.code === 1) {
      message(action.successMessage, { type: "success" });
      await loadList();
    } else if (res.code === -2 && res.data) {
      try {
        await ElMessageBox.confirm(action.notifyErrorMessage, "提示", {
          confirmButtonText: "查看",
          cancelButtonText: "取消",
          type: "warning"
        });
        await ElMessageBox.alert(String(res.data), "通知返回数据", {
          dangerouslyUseHTMLString: false
        });
      } catch {}
    } else {
      message(res.msg || action.failureMessage, { type: "error" });
    }
  } catch (error: any) {
    if (error !== "cancel" && error !== "close") {
      message(error?.msg || error?.message || action.failureMessage, {
        type: "error"
      });
    }
  } finally {
    setRowActionLoading("repair", row, false);
  }
};

const handleDeleteExpired = async () => {
  if (bulkActionLoading.expired) {
    return;
  }

  try {
    await ElMessageBox.confirm(
      buildDeleteExpiredOrdersConfirmMessage(),
      "提示",
      {
        type: "warning",
        confirmButtonText: "删除",
        confirmButtonClass: "el-button--danger"
      }
    );
    bulkActionLoading.expired = true;
    const res = await deleteExpiredOrders();
    message(res.code === 1 ? "操作成功" : res.msg || "操作失败", {
      type: res.code === 1 ? "success" : "error"
    });
    if (res.code === 1) await loadList();
  } catch (error: any) {
    if (error !== "cancel" && error !== "close") {
      message(error?.msg || error?.message || "操作失败", { type: "error" });
    }
  } finally {
    bulkActionLoading.expired = false;
  }
};

const handleDeleteOld = async () => {
  if (bulkActionLoading.old) {
    return;
  }

  try {
    await ElMessageBox.confirm(buildDeleteOldOrdersConfirmMessage(), "提示", {
      type: "warning",
      confirmButtonText: "删除",
      confirmButtonClass: "el-button--danger"
    });
    bulkActionLoading.old = true;
    const res = await deleteOldOrders();
    message(res.code === 1 ? "操作成功" : res.msg || "操作失败", {
      type: res.code === 1 ? "success" : "error"
    });
    if (res.code === 1) await loadList();
  } catch (error: any) {
    if (error !== "cancel" && error !== "close") {
      message(error?.msg || error?.message || "操作失败", { type: "error" });
    }
  } finally {
    bulkActionLoading.old = false;
  }
};

const getOrderTitle = (row: any) =>
  String(row.pay_id || row.order_id || "未知订单");

const getOrderSubtitle = (row: any) =>
  row.order_id ? `云端订单号：${row.order_id}` : "暂无云端订单号";

const getMobileOrderActions = (row: any): AdminMobileMoreAction[] => {
  const actions: AdminMobileMoreAction[] = [];

  if (row.pay_id)
    actions.push({ label: "复制商户订单号", command: "copyPayId" });
  if (row.order_id) {
    actions.push({ label: "复制云端订单号", command: "copyOrderId" });
  }
  if (row.price || row.really_price) {
    actions.push({ label: "复制金额", command: "copyAmount" });
  }

  const repairAction = resolveRepairAction(row.state);
  if (repairAction) {
    actions.push({
      label: repairAction.label,
      command: "repair",
      type: row.state === 0 ? "warning" : "primary",
      disabled:
        isRowActionLoading("repair", row) || isRowActionLoading("delete", row),
      loading: isRowActionLoading("repair", row)
    });
  }

  actions.push({
    label: "删除",
    command: "delete",
    type: "danger",
    disabled:
      isRowActionLoading("repair", row) || isRowActionLoading("delete", row),
    loading: isRowActionLoading("delete", row)
  });

  return actions;
};

const handleMobileOrderMore = (row: any, command: string) => {
  if (command === "copyPayId") return copyOrderField(row, "payId");
  if (command === "copyOrderId") return copyOrderField(row, "orderId");
  if (command === "copyAmount") return copyOrderField(row, "amount");
  if (command === "repair") return handleRepair(row);
  if (command === "delete") return handleDelete(row);
};

const applyMobileFilters = () => {
  mobileFilterVisible.value = false;
  onSearch();
};

onMounted(loadList);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header>
        <div class="orders-card-header">
          <span class="orders-card-title">订单列表</span>
          <div class="orders-bulk-actions">
            <el-button
              type="warning"
              size="small"
              :loading="bulkActionLoading.expired"
              :disabled="bulkActionLoading.expired || bulkActionLoading.old"
              @click="handleDeleteExpired"
            >
              删除所有过期订单
            </el-button>
            <el-button
              type="danger"
              size="small"
              :loading="bulkActionLoading.old"
              :disabled="bulkActionLoading.expired || bulkActionLoading.old"
              @click="handleDeleteOld"
            >
              删除七天前订单
            </el-button>
          </div>
        </div>
      </template>

      <div class="mb-3 flex flex-wrap items-center gap-2">
        <span class="orders-filter-label">状态快捷筛选</span>
        <el-button
          v-for="preset in orderStatePresets"
          :key="preset.value || 'all'"
          size="small"
          :type="filters.state === preset.value ? 'primary' : ''"
          :plain="filters.state !== preset.value"
          @click="applyStatePreset(preset.value)"
        >
          {{ preset.label }}
        </el-button>
      </div>

      <div class="orders-mobile-filter admin-mobile-only">
        <el-input
          v-model="filters.keyword"
          clearable
          placeholder="订单号 / 金额 / 终端"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
        <el-button type="primary" @click="mobileFilterVisible = true">
          筛选
        </el-button>
      </div>

      <!-- 过滤栏 -->
      <div class="admin-desktop-only mb-4 flex flex-wrap items-center gap-3">
        <el-input
          v-model="filters.keyword"
          clearable
          placeholder="订单号 / 支付 ID"
          style="width: 220px"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
        <el-input
          v-model="filters.amount"
          clearable
          placeholder="订单金额"
          style="width: 140px"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
        <el-date-picker
          v-model="filters.dateRange"
          type="datetimerange"
          start-placeholder="开始时间"
          end-placeholder="结束时间"
          range-separator="至"
          clearable
          style="width: 360px"
          @change="onSearch"
        />
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
        <el-input
          v-model="filters.terminalId"
          clearable
          placeholder="终端 ID"
          style="width: 120px"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
        <el-input
          v-model="filters.channelId"
          clearable
          placeholder="通道 ID"
          style="width: 120px"
          @keyup.enter="onSearch"
          @clear="onSearch"
        />
        <el-button type="primary" @click="onSearch">搜索</el-button>
        <el-button @click="onResetFilters">重置</el-button>
      </div>

      <!-- 表格 -->
      <div class="orders-table-wrap orders-desktop-table admin-desktop-only">
        <el-table
          v-loading="loading"
          :data="list"
          border
          empty-text="暂无订单数据"
        >
          <el-table-column label="创建时间" width="180">
            <template #default="{ row }">
              {{ formatUnixTimestamp(row.create_date) }}
            </template>
          </el-table-column>
          <el-table-column
            label="商户订单号"
            min-width="190"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              <span>{{ row.pay_id }}</span>
              <el-button
                v-if="row.pay_id"
                class="ml-1"
                size="small"
                text
                @click="copyOrderField(row, 'payId')"
              >
                复制
              </el-button>
            </template>
          </el-table-column>
          <el-table-column
            label="云端订单号"
            min-width="190"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              <span>{{ row.order_id }}</span>
              <el-button
                v-if="row.order_id"
                class="ml-1"
                size="small"
                text
                @click="copyOrderField(row, 'orderId')"
              >
                复制
              </el-button>
            </template>
          </el-table-column>
          <el-table-column
            label="所属终端"
            min-width="220"
            show-overflow-tooltip
          >
            <template #default="{ row }">
              {{ formatTerminalOwnership(row) }}
            </template>
          </el-table-column>
          <el-table-column label="类型" width="80">
            <template #default="{ row }">{{
              TYPE_MAP[row.type] ?? row.type
            }}</template>
          </el-table-column>
          <el-table-column label="订单金额" prop="price" width="100" />
          <el-table-column label="实际金额" width="130">
            <template #default="{ row }">
              <span>{{ row.really_price }}</span>
              <el-button
                v-if="row.price || row.really_price"
                class="ml-1"
                size="small"
                text
                @click="copyOrderField(row, 'amount')"
              >
                复制
              </el-button>
            </template>
          </el-table-column>
          <el-table-column label="状态" width="100">
            <template #default="{ row }">
              <el-tag :type="STATE_MAP[row.state]?.type ?? 'info'">
                {{ STATE_MAP[row.state]?.label ?? row.state }}
              </el-tag>
            </template>
          </el-table-column>
          <el-table-column label="操作" width="180" fixed="right">
            <template #default="{ row }">
              <el-button size="small" text @click="openDetail(row)"
                >详情</el-button
              >
              <el-button
                v-if="resolveRepairAction(row.state)"
                size="small"
                text
                :type="row.state === 0 ? 'warning' : 'primary'"
                :loading="isRowActionLoading('repair', row)"
                :disabled="
                  isRowActionLoading('repair', row) ||
                  isRowActionLoading('delete', row)
                "
                @click="handleRepair(row)"
              >
                {{ resolveRepairAction(row.state)?.label }}
              </el-button>
              <el-button
                size="small"
                text
                type="danger"
                :loading="isRowActionLoading('delete', row)"
                :disabled="
                  isRowActionLoading('repair', row) ||
                  isRowActionLoading('delete', row)
                "
                @click="handleDelete(row)"
                >删除</el-button
              >
            </template>
          </el-table-column>
        </el-table>
      </div>

      <AdminMobileList
        class="orders-mobile-list admin-mobile-only"
        :loading="loading"
        :empty="list.length === 0"
        empty-text="暂无订单数据"
      >
        <AdminMobileRecordCard
          v-for="row in list"
          :key="row.id || row.order_id || row.pay_id"
          :title="getOrderTitle(row)"
          :subtitle="getOrderSubtitle(row)"
        >
          <template #status>
            <el-tag :type="STATE_MAP[row.state]?.type ?? 'info'">
              {{ STATE_MAP[row.state]?.label ?? row.state }}
            </el-tag>
          </template>
          <AdminMobileField label="创建时间">
            {{ formatUnixTimestamp(row.create_date) }}
          </AdminMobileField>
          <AdminMobileField label="类型">
            {{ TYPE_MAP[row.type] ?? row.type }}
          </AdminMobileField>
          <AdminMobileField label="订单金额" :value="row.price" />
          <AdminMobileField label="实际金额" :value="row.really_price" />
          <AdminMobileField label="所属终端" truncate>
            {{ formatTerminalOwnership(row) }}
          </AdminMobileField>
          <AdminMobileField label="状态">
            {{ STATE_MAP[row.state]?.label ?? row.state }}
          </AdminMobileField>
          <template #actions>
            <AdminMobileActions
              primary-text="详情"
              :more-actions="getMobileOrderActions(row)"
              @primary="openDetail(row)"
              @more="command => handleMobileOrderMore(row, command)"
            />
          </template>
        </AdminMobileRecordCard>
      </AdminMobileList>

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

    <OrderDetailDialog
      v-model="detailVisible"
      :order="selectedOrder"
      @copy="copyOrderField"
    />

    <AdminFilterDrawer
      v-model="mobileFilterVisible"
      title="订单筛选"
      @apply="applyMobileFilters"
      @reset="onResetFilters"
    >
      <el-form label-position="top">
        <el-form-item label="订单类型">
          <el-select v-model="filters.type" clearable placeholder="全部类型">
            <el-option label="微信" value="1" />
            <el-option label="支付宝" value="2" />
          </el-select>
        </el-form-item>
        <el-form-item label="订单状态">
          <el-select v-model="filters.state" clearable placeholder="全部状态">
            <el-option
              v-for="preset in orderStatePresets.filter(
                item => item.value !== ''
              )"
              :key="preset.value"
              :label="preset.label"
              :value="preset.value"
            />
          </el-select>
        </el-form-item>
        <el-form-item label="订单金额">
          <el-input v-model="filters.amount" clearable placeholder="输入金额" />
        </el-form-item>
        <el-form-item label="终端ID">
          <el-input
            v-model="filters.terminalId"
            clearable
            placeholder="输入终端ID"
          />
        </el-form-item>
        <el-form-item label="通道ID">
          <el-input
            v-model="filters.channelId"
            clearable
            placeholder="输入通道ID"
          />
        </el-form-item>
      </el-form>
    </AdminFilterDrawer>
  </div>
</template>

<style scoped>
.orders-filter-label {
  font-size: 13px;
  color: var(--el-text-color-secondary);
}

.orders-card-header {
  display: flex;
  gap: 12px;
  align-items: center;
  justify-content: space-between;
}

.orders-card-title {
  flex: 0 0 auto;
}

.orders-bulk-actions {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: flex-end;
}

.orders-table-wrap {
  width: 100%;
  overflow-x: auto;
}

.orders-table-wrap :deep(.el-table) {
  min-width: 1180px;
}

.orders-mobile-filter {
  display: grid;
  grid-template-columns: minmax(0, 1fr) 76px;
  gap: 8px;
  margin-bottom: 12px;
}

.orders-mobile-filter .el-button {
  min-height: 40px;
}

@media (max-width: 768px) {
  .orders-card-header {
    flex-direction: column;
    align-items: flex-start;
  }

  .orders-card-title {
    width: 100%;
  }

  .orders-bulk-actions {
    width: 100%;
    justify-content: flex-start;
  }

  .orders-table-wrap {
    margin-right: -12px;
    margin-left: -12px;
    padding-right: 12px;
    padding-left: 12px;
  }
}
</style>

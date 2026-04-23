<script setup lang="ts">
import { computed, reactive, ref } from "vue";

import { createPaymentTestOrder } from "@/api/admin/paymentLab";

defineOptions({ name: "PaymentLab" });

const form = reactive({
  type: 1,
  price: "0.10",
  payId: buildPayId(),
  param: "VPay Payment Lab",
  notifyUrl: "",
  returnUrl: ""
});

const loading = ref(false);
const message = ref("");

const payTypes = [
  { label: "微信支付", value: 1, accent: "#22c55e" },
  { label: "支付宝", value: 2, accent: "#38bdf8" }
];

const activeType = computed(() => payTypes.find(item => item.value === form.type) ?? payTypes[0]);

function buildPayId() {
  return `TEST-${Date.now()}`;
}

function resetPayId() {
  form.payId = buildPayId();
}

function setMessage(text: string) {
  message.value = text;
}

async function submitOrder() {
  setMessage("");
  loading.value = true;

  try {
    const response = await createPaymentTestOrder({ ...form });
    if (response.code !== 1) {
      setMessage(response.msg || "测试订单创建失败");
      return;
    }

    const payPageUrl = String(response.data?.payPageUrl ?? "").trim();
    if (!payPageUrl) {
      setMessage("未生成原支付页地址，请检查后台返回");
      return;
    }

    window.location.href = payPageUrl;
  } catch (error) {
    setMessage(error instanceof Error ? error.message : "网络请求失败");
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <main class="payment-lab-shell">
    <section class="lab-hero">
      <div>
        <p class="eyebrow">VPay Payment Lab</p>
        <h1>发起后直接进入原系统支付页</h1>
        <p class="hero-copy">
          这里不再单独渲染测试二维码。订单创建成功后，会直接跳转到现有
          <code>/payPage/pay.html</code>
          ，由原支付页继续展示二维码、金额提醒、倒计时和支付轮询。
        </p>
      </div>
      <div class="hero-status">
        <span class="pulse-dot" />
        <span>原支付页模式</span>
      </div>
    </section>

    <section class="lab-grid">
      <form class="lab-card launch-panel" @submit.prevent="submitOrder">
        <div class="section-heading">
          <span>01</span>
          <h2>发起测试订单</h2>
        </div>

        <div class="type-switch" role="radiogroup" aria-label="支付类型">
          <button
            v-for="item in payTypes"
            :key="item.value"
            type="button"
            class="type-button"
            :class="{ active: form.type === item.value }"
            :style="{ '--accent': item.accent }"
            @click="form.type = item.value"
          >
            <span class="type-mark" />
            {{ item.label }}
          </button>
        </div>

        <label>
          <span>测试金额</span>
          <input v-model="form.price" inputmode="decimal" placeholder="0.10" />
        </label>

        <label>
          <span>商户订单号</span>
          <div class="inline-field">
            <input v-model="form.payId" placeholder="TEST-..." />
            <button type="button" @click="resetPayId">重置</button>
          </div>
        </label>

        <label>
          <span>附加参数</span>
          <input v-model="form.param" placeholder="VPay Payment Lab" />
        </label>

        <label>
          <span>异步回调地址</span>
          <input v-model="form.notifyUrl" placeholder="留空使用内置测试回调" />
        </label>

        <label>
          <span>同步跳转地址</span>
          <input v-model="form.returnUrl" placeholder="留空使用内置同步回跳" />
        </label>

        <p v-if="message" class="message-line">{{ message }}</p>

        <button class="primary-action" type="submit" :disabled="loading">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h12m0 0-5-5m5 5-5 5" />
          </svg>
          {{ loading ? "创建中..." : `发起并进入${activeType.label}页` }}
        </button>
      </form>

      <section class="lab-card flow-panel">
        <div class="section-heading">
          <span>02</span>
          <h2>真实链路</h2>
        </div>

        <ol class="flow-list">
          <li>测试台调用后台安全接口创建订单，不暴露签名密钥。</li>
          <li>后台返回原支付页地址，当前页面立即跳转到 <code>pay.html</code>。</li>
          <li>原支付页使用现有 <code>getOrder</code> 与 <code>checkOrder</code> 流程完成展示与轮询。</li>
          <li>支付成功后按你填写的 <code>returnUrl</code> 或内置回跳地址继续后续流程。</li>
        </ol>

        <div class="info-block">
          <strong>适合验证</strong>
          <p>自定义金额、金额递增、防撞单、终端分配、支付页 UX、同步跳转与异步通知。</p>
        </div>

        <div class="info-block muted">
          <strong>当前取舍</strong>
          <p>独立测试台不再维护二维码和状态面板，避免和正式支付页形成两套展示逻辑。</p>
        </div>
      </section>
    </section>
  </main>
</template>

<style scoped>
@import url("https://fonts.googleapis.com/css2?family=Space+Mono:wght@400;700&family=Syncopate:wght@400;700&display=swap");

.payment-lab-shell {
  min-height: 100vh;
  padding: 40px;
  color: #f8fafc;
  background:
    radial-gradient(circle at 20% 10%, rgba(34, 197, 94, 0.2), transparent 30%),
    radial-gradient(circle at 80% 0%, rgba(56, 189, 248, 0.14), transparent 28%),
    linear-gradient(135deg, #020617 0%, #0f172a 45%, #111827 100%);
  font-family: "Space Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
}

.lab-hero {
  display: grid;
  grid-template-columns: minmax(0, 1fr) auto;
  gap: 24px;
  align-items: end;
  max-width: 1320px;
  margin: 0 auto 28px;
}

.eyebrow,
.section-heading span {
  color: #22c55e;
  font-size: 12px;
  font-weight: 700;
  letter-spacing: 0.24em;
  text-transform: uppercase;
}

h1 {
  max-width: 980px;
  margin: 10px 0 14px;
  font-family: "Syncopate", "Space Mono", sans-serif;
  font-size: clamp(34px, 5vw, 72px);
  line-height: 0.95;
}

.hero-copy {
  max-width: 760px;
  color: #cbd5e1;
  font-size: 16px;
  line-height: 1.8;
}

.hero-copy code,
.flow-list code {
  padding: 2px 8px;
  border-radius: 999px;
  background: rgba(15, 23, 42, 0.6);
  color: #d1fae5;
}

.hero-status,
.lab-card {
  border: 1px solid rgba(148, 163, 184, 0.22);
  background: rgba(15, 23, 42, 0.78);
  box-shadow: 0 24px 80px rgba(0, 0, 0, 0.3);
  backdrop-filter: blur(18px);
}

.hero-status {
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 14px 18px;
  border-radius: 999px;
  color: #d1fae5;
}

.pulse-dot {
  width: 10px;
  height: 10px;
  border-radius: 999px;
  background: #22c55e;
  box-shadow: 0 0 0 8px rgba(34, 197, 94, 0.14);
}

.lab-grid {
  display: grid;
  grid-template-columns: minmax(360px, 0.95fr) minmax(360px, 1.05fr);
  gap: 18px;
  max-width: 1320px;
  margin: 0 auto;
}

.lab-card {
  border-radius: 30px;
  padding: 24px;
}

.section-heading {
  display: flex;
  align-items: center;
  gap: 12px;
  margin-bottom: 22px;
}

.section-heading h2 {
  margin: 0;
  font-size: 18px;
}

label {
  display: grid;
  gap: 8px;
  margin-bottom: 16px;
  color: #dbeafe;
  font-size: 13px;
}

input {
  width: 100%;
  border: 1px solid rgba(148, 163, 184, 0.26);
  border-radius: 16px;
  padding: 13px 14px;
  color: #f8fafc;
  background: rgba(2, 6, 23, 0.58);
  outline: none;
  transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus {
  border-color: #22c55e;
  box-shadow: 0 0 0 4px rgba(34, 197, 94, 0.16);
}

.inline-field {
  display: grid;
  grid-template-columns: 1fr auto;
  gap: 8px;
}

button {
  cursor: pointer;
  border: 0;
  border-radius: 16px;
  color: #f8fafc;
  background: rgba(51, 65, 85, 0.8);
  transition:
    background 0.2s ease,
    color 0.2s ease,
    border-color 0.2s ease,
    transform 0.2s ease;
}

button:hover {
  background: rgba(34, 197, 94, 0.88);
  color: #04111d;
  transform: translateY(-1px);
}

button:focus-visible {
  outline: 3px solid rgba(34, 197, 94, 0.45);
  outline-offset: 3px;
}

button:disabled {
  cursor: wait;
  opacity: 0.7;
  transform: none;
}

.inline-field button {
  padding: 0 14px;
  font-size: 13px;
}

.type-switch {
  display: grid;
  grid-template-columns: repeat(2, 1fr);
  gap: 10px;
  margin-bottom: 18px;
}

.type-button {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 14px;
  border: 1px solid rgba(148, 163, 184, 0.22);
}

.type-button.active {
  border-color: var(--accent);
  background: color-mix(in srgb, var(--accent) 18%, rgba(15, 23, 42, 0.88));
}

.type-mark {
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--accent);
}

.message-line {
  margin: 4px 0 16px;
  color: #fbbf24;
  line-height: 1.7;
}

.primary-action {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  width: 100%;
  margin-top: 8px;
  padding: 16px 18px;
  color: #04111d;
  background: #22c55e;
  font-weight: 700;
}

.primary-action svg {
  width: 22px;
  height: 22px;
  fill: none;
  stroke: currentColor;
  stroke-width: 1.8;
  stroke-linecap: round;
  stroke-linejoin: round;
}

.flow-list {
  display: grid;
  gap: 14px;
  margin: 0;
  padding-left: 22px;
  color: #dbeafe;
  line-height: 1.8;
}

.info-block {
  margin-top: 22px;
  padding: 18px 18px 16px;
  border: 1px solid rgba(34, 197, 94, 0.16);
  border-radius: 22px;
  background: rgba(2, 6, 23, 0.44);
}

.info-block strong {
  display: block;
  margin-bottom: 10px;
  color: #f8fafc;
  font-size: 14px;
}

.info-block p {
  margin: 0;
  color: #cbd5e1;
  line-height: 1.8;
}

.info-block.muted {
  border-color: rgba(148, 163, 184, 0.14);
}

@media (max-width: 960px) {
  .lab-grid,
  .lab-hero {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 780px) {
  .payment-lab-shell {
    padding: 20px;
  }

  h1 {
    font-size: 36px;
  }
}

@media (prefers-reduced-motion: reduce) {
  *,
  *::before,
  *::after {
    scroll-behavior: auto !important;
    transition-duration: 0.01ms !important;
    animation-duration: 0.01ms !important;
  }
}
</style>

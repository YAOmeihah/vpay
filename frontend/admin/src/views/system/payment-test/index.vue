<script setup lang="ts">
defineOptions({ name: "PaymentLabLauncher" });

const openPaymentLab = () => {
  const target = `${window.location.origin}${window.location.pathname}#/payment-lab`;
  window.open(target, "_blank", "noopener,noreferrer");
};
</script>

<template>
  <div class="payment-lab-launcher">
    <div class="launcher-card">
      <div class="launcher-copy">
        <p class="launcher-eyebrow">VPay Payment Lab</p>
        <h1>打开独立支付测试台</h1>
        <p>
          测试台会在新页面中打开，不复用后台管理台的左侧菜单和页签，用于完整验证下单、二维码、轮询和回调捕获链路。
        </p>
        <button type="button" class="launch-button" @click="openPaymentLab">
          <span>打开支付测试台</span>
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M5 12h12m0 0-5-5m5 5-5 5" />
          </svg>
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.payment-lab-launcher {
  min-height: calc(100vh - 180px);
  padding: 24px;
  background:
    radial-gradient(circle at 16% 18%, rgba(34, 197, 94, 0.12), transparent 28%),
    radial-gradient(circle at 90% 4%, rgba(14, 165, 233, 0.12), transparent 24%),
    #eef2f7;
}

.launcher-card {
  min-height: 280px;
  border: 1px solid rgba(15, 23, 42, 0.08);
  border-radius: 28px;
  padding: clamp(28px, 5vw, 56px);
  background:
    linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 250, 252, 0.9)),
    repeating-linear-gradient(45deg, rgba(15, 23, 42, 0.035) 0 1px, transparent 1px 18px);
  box-shadow: 0 24px 80px rgba(15, 23, 42, 0.1);
}

.launcher-copy {
  max-width: 760px;
}

.launcher-eyebrow {
  margin: 0 0 10px;
  color: #047857;
  font-size: 12px;
  font-weight: 800;
  letter-spacing: 0.28em;
  text-transform: uppercase;
}

.launcher-copy h1 {
  margin: 0;
  color: #020617;
  font-size: clamp(30px, 4vw, 54px);
  font-weight: 900;
  line-height: 1.05;
}

.launcher-copy p:not(.launcher-eyebrow) {
  max-width: 660px;
  margin: 14px 0 24px;
  color: #334155;
  font-size: 16px;
  line-height: 1.8;
}

.launch-button {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  min-height: 48px;
  border: 0;
  border-radius: 999px;
  padding: 0 24px;
  color: #04111d;
  background: #22c55e;
  box-shadow: 0 14px 30px rgba(34, 197, 94, 0.28);
  font-size: 15px;
  font-weight: 900;
  cursor: pointer;
  transition: background 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
}

.launch-button:hover {
  background: #16a34a;
  box-shadow: 0 18px 40px rgba(22, 163, 74, 0.32);
  transform: translateY(-1px);
}

.launch-button:focus-visible {
  outline: 4px solid rgba(34, 197, 94, 0.28);
  outline-offset: 4px;
}

.launch-button svg {
  width: 20px;
  height: 20px;
  fill: none;
  stroke: currentColor;
  stroke-width: 2;
  stroke-linecap: round;
  stroke-linejoin: round;
}

@media (max-width: 860px) {
  .launcher-card {
    min-height: 220px;
  }
}

@media (prefers-reduced-motion: reduce) {
  .launch-button {
    transition: none;
  }

  .launch-button:hover {
    transform: none;
  }
}
</style>

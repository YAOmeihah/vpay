<script setup lang="ts">
import { ref, reactive, onMounted } from "vue";
import { message } from "@/utils/message";
import { getSettings, saveSettings, generateRsaKeys } from "@/api/admin/settings";
import type { FormInstance } from "element-plus";

defineOptions({ name: "SystemSettings" });

const loading = ref(false);
const formRef = ref<FormInstance>();
const formData = reactive({
  user: "",
  pass: "",
  notifyUrl: "",
  returnUrl: "",
  key: "",
  close: "0",
  payQf: "0",
  wxpay: "0",
  zfbpay: "0",
  epay_enabled: "0",
  epay_pid: "",
  epay_name: "",
  epay_key: "",
  epay_private_key: "",
  epay_public_key: ""
});

const rules = {
  user: [{ required: true, message: "请输入管理员账号", trigger: "blur" }],
  notifyUrl: [{ required: true, message: "请输入异步通知地址", trigger: "blur" }],
  returnUrl: [{ required: true, message: "请输入同步跳转地址", trigger: "blur" }],
  key: [{ required: true, message: "请输入通讯密钥", trigger: "blur" }]
};

const loadSettings = async () => {
  try {
    loading.value = true;
    const res = await getSettings();
    if (res.code === 1) Object.assign(formData, res.data);
  } finally {
    loading.value = false;
  }
};

const handleSave = async (formEl: FormInstance | undefined) => {
  if (!formEl) return;
  await formEl.validate(async valid => {
    if (!valid) return;
    try {
      loading.value = true;
      const res = await saveSettings({ ...formData });
      message(res.code === 1 ? "保存成功" : res.msg || "保存失败", {
        type: res.code === 1 ? "success" : "error"
      });
    } finally {
      loading.value = false;
    }
  });
};

const handleGenerateKeys = async () => {
  try {
    loading.value = true;
    const res = await generateRsaKeys();
    if (res.code === 1) {
      formData.epay_private_key = res.data.private_key;
      formData.epay_public_key = res.data.public_key;
      message("生成成功", { type: "success" });
    } else {
      message(res.msg || "生成失败", { type: "error" });
    }
  } finally {
    loading.value = false;
  }
};

onMounted(loadSettings);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header><span>系统设置</span></template>
      <el-form ref="formRef" :model="formData" :rules="rules" label-width="140px" v-loading="loading">
        <el-divider content-position="left">基本设置</el-divider>
        <el-form-item label="管理员账号" prop="user">
          <el-input v-model="formData.user" placeholder="请输入管理员账号" />
        </el-form-item>
        <el-form-item label="管理员密码">
          <el-input v-model="formData.pass" type="password" placeholder="留空则不修改" show-password />
        </el-form-item>
        <el-form-item label="异步通知地址" prop="notifyUrl">
          <el-input v-model="formData.notifyUrl" placeholder="请输入异步通知地址" />
        </el-form-item>
        <el-form-item label="同步跳转地址" prop="returnUrl">
          <el-input v-model="formData.returnUrl" placeholder="请输入同步跳转地址" />
        </el-form-item>
        <el-form-item label="通讯密钥" prop="key">
          <el-input v-model="formData.key" placeholder="请输入通讯密钥" />
        </el-form-item>
        <el-form-item label="系统状态">
          <el-radio-group v-model="formData.close">
            <el-radio label="0">开启</el-radio>
            <el-radio label="1">关闭</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="支付轮询">
          <el-radio-group v-model="formData.payQf">
            <el-radio label="0">关闭</el-radio>
            <el-radio label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-divider content-position="left">支付方式</el-divider>
        <el-form-item label="微信支付">
          <el-radio-group v-model="formData.wxpay">
            <el-radio label="0">关闭</el-radio>
            <el-radio label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="支付宝支付">
          <el-radio-group v-model="formData.zfbpay">
            <el-radio label="0">关闭</el-radio>
            <el-radio label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>

        <el-divider content-position="left">易支付配置</el-divider>
        <el-form-item label="易支付">
          <el-radio-group v-model="formData.epay_enabled">
            <el-radio label="0">关闭</el-radio>
            <el-radio label="1">开启</el-radio>
          </el-radio-group>
        </el-form-item>
        <el-form-item label="商户ID">
          <el-input v-model="formData.epay_pid" placeholder="请输入易支付商户ID" />
        </el-form-item>
        <el-form-item label="商户名称">
          <el-input v-model="formData.epay_name" placeholder="请输入易支付商户名称" />
        </el-form-item>
        <el-form-item label="商户密钥">
          <el-input v-model="formData.epay_key" placeholder="请输入易支付商户密钥" />
        </el-form-item>
        <el-form-item label="RSA 私钥">
          <el-input v-model="formData.epay_private_key" type="textarea" :rows="4" placeholder="RSA 私钥" />
        </el-form-item>
        <el-form-item label="RSA 公钥">
          <el-input v-model="formData.epay_public_key" type="textarea" :rows="4" placeholder="RSA 公钥" />
        </el-form-item>
        <el-form-item>
          <el-button @click="handleGenerateKeys" :loading="loading">生成 RSA 密钥对</el-button>
        </el-form-item>

        <el-form-item>
          <el-button type="primary" @click="handleSave(formRef)" :loading="loading">保存设置</el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

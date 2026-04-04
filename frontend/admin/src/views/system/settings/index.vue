<script setup lang="ts">
import { computed, reactive, ref, onMounted } from "vue";
import { message } from "@/utils/message";
import { getSettings, saveSettings, generateRsaKeys } from "@/api/admin/settings";
import type { FormInstance, UploadFile } from "element-plus";
import {
  buildQrcodePreviewUrl,
  generateMd5LikeKey
} from "@/utils/adminLegacy";
import { decodeQrFromFile } from "@/utils/qrcode";

defineOptions({ name: "SystemSettings" });

const loading = ref(false);
const formRef = ref<FormInstance>();
const formData = reactive({
  user: "",
  pass: "",
  notifyUrl: "",
  returnUrl: "",
  key: "",
  close: "",
  payQf: "1",
  wxpay: "",
  zfbpay: "",
  epay_enabled: "0",
  epay_pid: "",
  epay_name: "",
  epay_key: "",
  epay_private_key: "",
  epay_public_key: ""
});

const wxpayPreviewUrl = computed(() => buildQrcodePreviewUrl(formData.wxpay));
const zfbpayPreviewUrl = computed(() => buildQrcodePreviewUrl(formData.zfbpay));

const rules = {
  user: [{ required: true, message: "请输入管理员账号", trigger: "blur" }],
  notifyUrl: [{ required: true, message: "请输入异步通知地址", trigger: "blur" }],
  returnUrl: [{ required: true, message: "请输入同步跳转地址", trigger: "blur" }],
  key: [{ required: true, message: "请输入通讯密钥", trigger: "blur" }],
  close: [{ required: true, message: "请输入创建的订单几分钟后失效", trigger: "blur" }],
  wxpay: [{ required: true, message: "请上传微信无金额的收款二维码", trigger: "change" }],
  zfbpay: [{ required: true, message: "请上传支付宝无金额的收款二维码", trigger: "change" }]
};

const loadSettings = async () => {
  try {
    loading.value = true;
    const res = await getSettings();
    if (res.code === 1) {
      Object.assign(formData, {
        ...res.data,
        pass: "",
        epay_key: "",
        epay_private_key: ""
      });
    }
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
      if (res.code === 1) {
        await loadSettings();
        message("保存成功", { type: "success" });
      } else {
        message(res.msg || "保存失败", { type: "error" });
      }
    } finally {
      loading.value = false;
    }
  });
};

const handleGenerateMd5Key = () => {
  formData.epay_key = generateMd5LikeKey();
  message("MD5密钥已生成，请点击保存", { type: "success" });
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

const handleQrcodeChange = async (
  field: "wxpay" | "zfbpay",
  uploadFile: UploadFile
) => {
  const file = uploadFile.raw;
  if (!file) return;

  const decoded = await decodeQrFromFile(file);
  if (!decoded) {
    message("处理失败，可以尝试手动填写二维码内容", { type: "error" });
    return;
  }

  formData[field] = decoded;
  message("处理成功", { type: "success" });
};

onMounted(loadSettings);
</script>

<template>
  <div class="p-4">
    <el-card shadow="hover">
      <template #header><span>系统设置</span></template>

      <el-form
        ref="formRef"
        :model="formData"
        :rules="rules"
        label-width="140px"
        v-loading="loading"
      >
        <el-form-item label="后台账号" prop="user">
          <el-input v-model="formData.user" placeholder="请输入管理员账号" />
        </el-form-item>

        <el-form-item label="后台密码" prop="pass">
          <el-input
            v-model="formData.pass"
            type="password"
            placeholder="请输入管理员密码"
            show-password
          />
        </el-form-item>

        <el-form-item label="订单有效期" prop="close">
          <el-input v-model="formData.close" type="number" placeholder="请输入创建的订单几分钟后失效" />
        </el-form-item>

        <el-form-item label="异步回调" prop="notifyUrl">
          <el-input v-model="formData.notifyUrl" placeholder="请输入异步回调地址" />
        </el-form-item>

        <el-form-item label="同步回调" prop="returnUrl">
          <el-input v-model="formData.returnUrl" placeholder="请输入支付完成后跳转地址" />
        </el-form-item>

        <el-form-item label="通讯密钥" prop="key">
          <el-input v-model="formData.key" placeholder="请输入通讯密钥" />
        </el-form-item>

        <el-form-item label="区分方式" prop="payQf">
          <el-select v-model="formData.payQf" class="w-full">
            <el-option label="金额递增" value="1" />
            <el-option label="金额递减" value="2" />
          </el-select>
        </el-form-item>

        <el-form-item label="微信码" prop="wxpay">
          <div class="w-full">
            <el-upload
              :auto-upload="false"
              :show-file-list="false"
              accept="image/*"
              :on-change="file => handleQrcodeChange('wxpay', file)"
            >
              <el-button type="primary">上传收款二维码</el-button>
            </el-upload>
            <p class="mt-2 text-sm text-gray-500">此处上传的是无金额的收款二维码</p>
            <el-image
              v-if="wxpayPreviewUrl"
              :src="wxpayPreviewUrl"
              class="mt-3 qr-preview"
              fit="contain"
            />
          </div>
        </el-form-item>

        <el-form-item label="支付宝码" prop="zfbpay">
          <div class="w-full">
            <el-upload
              :auto-upload="false"
              :show-file-list="false"
              accept="image/*"
              :on-change="file => handleQrcodeChange('zfbpay', file)"
            >
              <el-button type="primary">上传收款二维码</el-button>
            </el-upload>
            <p class="mt-2 text-sm text-gray-500">此处上传的是无金额的收款二维码</p>
            <el-image
              v-if="zfbpayPreviewUrl"
              :src="zfbpayPreviewUrl"
              class="mt-3 qr-preview"
              fit="contain"
            />
          </div>
        </el-form-item>

        <el-divider content-position="left">易支付兼容</el-divider>

        <el-form-item label="启用状态">
          <el-select v-model="formData.epay_enabled" class="w-full">
            <el-option label="关闭" value="0" />
            <el-option label="开启" value="1" />
          </el-select>
        </el-form-item>

        <el-form-item label="商户ID(pid)">
          <el-input v-model="formData.epay_pid" placeholder="易支付商户号" />
        </el-form-item>

        <el-form-item label="商户名称">
          <el-input
            v-model="formData.epay_name"
            placeholder="订单显示名称，默认：订单支付"
          />
        </el-form-item>

        <el-form-item label="MD5密钥(v1)">
          <div class="w-full flex gap-2">
            <el-input
              v-model="formData.epay_key"
              placeholder="留空则不更新，用于 v1 MD5 签名"
            />
            <el-button @click="handleGenerateMd5Key">自动生成</el-button>
          </div>
        </el-form-item>

        <el-form-item label="RSA密钥(v2)">
          <el-button @click="handleGenerateKeys" :loading="loading">
            自动生成RSA密钥对
          </el-button>
        </el-form-item>

        <el-form-item label="RSA私钥">
          <el-input
            v-model="formData.epay_private_key"
            type="textarea"
            :rows="4"
            placeholder="留空则不更新，PEM格式RSA私钥，用于 v2 签名"
          />
        </el-form-item>

        <el-form-item label="RSA公钥">
          <el-input
            v-model="formData.epay_public_key"
            type="textarea"
            :rows="4"
            placeholder="留空则不更新，PEM格式RSA公钥，用于 v2 验签"
          />
        </el-form-item>

        <el-form-item class="justify-end">
          <el-button type="primary" @click="handleSave(formRef)" :loading="loading">
            保存
          </el-button>
        </el-form-item>
      </el-form>
    </el-card>
  </div>
</template>

<style scoped>
.qr-preview {
  width: 200px;
  height: 200px;
}
</style>

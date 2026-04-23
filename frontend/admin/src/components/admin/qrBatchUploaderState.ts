export interface QrRow {
  file: File;
  previewUrl: string;
  decodedUrl: string;
  price: string;
  status: "pending" | "ok" | "error";
  errMsg: string;
}

export type UploadFileLike = {
  raw?: File;
};

type BuildPendingQrRowDeps = {
  createPreviewUrl: (file: File) => string;
  decodeQr: (file: File) => Promise<string>;
};

type BuildPendingQrRowResult = {
  row: QrRow | null;
  warning: string;
};

export async function buildPendingQrRow(
  uploadFile: UploadFileLike,
  deps: BuildPendingQrRowDeps
): Promise<BuildPendingQrRowResult> {
  const file = uploadFile.raw;
  if (!file) {
    return {
      row: null,
      warning: "所选文件无效，请重新选择"
    };
  }

  let decodedUrl = "";
  let warning = "";

  try {
    decodedUrl = await deps.decodeQr(file);
    if (!decodedUrl) {
      warning = "二维码解析失败，可手动填写地址";
    }
  } catch {
    warning = "二维码解析失败，可手动填写地址";
  }

  return {
    row: {
      file,
      previewUrl: deps.createPreviewUrl(file),
      decodedUrl,
      price: "",
      status: "pending",
      errMsg: ""
    },
    warning
  };
}

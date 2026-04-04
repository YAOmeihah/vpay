import { decodeQrcodeImage } from "@/api/admin/qrcode";

function readFileAsDataUrl(file: File): Promise<string> {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(String(reader.result ?? ""));
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(file);
  });
}

async function detectQrWithBrowser(file: File): Promise<string> {
  const BarcodeDetectorCtor = (window as any).BarcodeDetector;
  if (!BarcodeDetectorCtor) return "";

  const detector = new BarcodeDetectorCtor({
    formats: ["qr_code"]
  });
  const blobUrl = URL.createObjectURL(file);

  try {
    const image = new Image();
    await new Promise((resolve, reject) => {
      image.onload = resolve;
      image.onerror = reject;
      image.src = blobUrl;
    });
    const matches = await detector.detect(image);
    return String(matches?.[0]?.rawValue ?? "").trim();
  } catch {
    return "";
  } finally {
    URL.revokeObjectURL(blobUrl);
  }
}

export async function decodeQrFromFile(file: File): Promise<string> {
  const browserDecoded = await detectQrWithBrowser(file);
  if (browserDecoded) return browserDecoded;

  const dataUrl = await readFileAsDataUrl(file);
  const base64 = dataUrl.includes(",") ? dataUrl.split(",")[1] : "";
  if (!base64) return "";

  try {
    const response = await decodeQrcodeImage({ base64 });
    if (response.code === 1) {
      return String(response.data ?? "").trim();
    }
  } catch {}

  return "";
}

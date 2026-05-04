<?php
declare(strict_types=1);

namespace app\controller\admin;

use app\BaseController;
use app\model\PayQrcode;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode as ChillerlanQRCode;
use chillerlan\QRCode\QROptions;

class Qrcode extends BaseController
{
    use \app\controller\trait\ApiResponse;

    /**
     * 添加支付二维码
     */
    public function addPayQrcode()
    {
        PayQrcode::create([
            "channel_id" => ($this->request->param("channelId", $this->request->param("channel_id")) !== null)
                ? (int) $this->request->param("channelId", $this->request->param("channel_id"))
                : null,
            "type" => (int)$this->request->param("type"),
            "pay_url" => $this->request->param("pay_url"),
            "price" => (float)$this->request->param("price"),
        ]);

        return $this->success();
    }

    /**
     * 获取支付二维码列表
     */
    public function getPayQrcodes()
    {
        $page = (int)$this->request->param("page", 1);
        $size = (int)$this->request->param("limit", 10);
        $type = $this->request->param("type");
        $channelId = $this->request->param("channelId", $this->request->param("channel_id"));

        $query = PayQrcode::where("type", (int)$type);
        if ($channelId !== null && $channelId !== '') {
            $query = $query->where("channel_id", (int) $channelId);
        }
        $count = $query->count();
        $array = $query->order("id", "desc")
            ->page($page, $size)
            ->select()
            ->toArray();

        return json([
            "code" => 1,
            "msg" => "获取成功",
            "data" => $array,
            "count" => $count
        ]);
    }

    /**
     * 删除支付二维码
     */
    public function delPayQrcode()
    {
        PayQrcode::where("id", (int)$this->request->param("id"))->delete();
        return $this->success();
    }

    /**
     * 解码二维码图片
     */
    public function decodeQrcode()
    {
        $base64 = (string)$this->request->param("base64", "");
        if ($base64 === "") {
            return $this->error("图片数据不能为空");
        }

        $imageBlob = base64_decode($base64, true);
        if ($imageBlob === false || $imageBlob === "") {
            return $this->error("图片数据无效");
        }

        try {
            $options = new QROptions([
                'readerUseImagickIfAvailable' => true,
            ]);
            $decoded = trim((string)(new ChillerlanQRCode($options))->readFromBlob($imageBlob));

            if ($decoded === "") {
                return $this->error("二维码识别失败", -2);
            }

            return $this->success($decoded);
        } catch (\Throwable $e) {
            return $this->error("二维码识别失败", -2);
        }
    }

    /**
     * 生成二维码
     */
    public function enQrcode()
    {
        $url = $this->request->param('url', '');
        if (empty($url)) {
            return $this->error("URL参数不能为空");
        }

        try {
            $options = new QROptions([
                'outputType' => QROutputInterface::GDIMAGE_PNG,
                'outputBase64' => false,
                'scale' => 5,
                'addQuietzone' => true,
                'quietzoneSize' => 2,
            ]);
            $imageBlob = (string)(new ChillerlanQRCode($options))->render($url);

            return response($imageBlob, 200, [
                'Content-Type' => 'image/png',
                'Content-Length' => strlen($imageBlob)
            ]);
        } catch (\Throwable $e) {
            return $this->error("二维码生成失败: " . $e->getMessage());
        }
    }
}

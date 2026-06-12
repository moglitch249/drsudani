<?php
/**
 * WooCommerce REST API Helper
 */

require_once __DIR__ . '/config.php';

class WooCommerceAPI
{
    private $baseUrl;
    private $key;
    private $secret;

    public function __construct()
    {
        $this->baseUrl = rtrim(WC_API_URL, '/') . '/wp-json/wc/v3';
        $this->key = WC_KEY;
        $this->secret = WC_SECRET;
    }

    private function request(string $endpoint, array $params = [])
    {
        $url = $this->baseUrl . $endpoint;
        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->key . ':' . $this->secret,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => true,  // مهم في الإنتاج: يمنع هجمات MITM
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 400) {
            return false;
        }

        return json_decode($response, true) ?? false;
    }

    private function putRequest(string $endpoint, array $data = [])
    {
        $url = $this->baseUrl . $endpoint;
        $jsonData = json_encode($data);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_POSTFIELDS => $jsonData,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD => $this->key . ':' . $this->secret,
            CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
            CURLOPT_SSL_VERIFYPEER => true,  // مهم في الإنتاج: يمنع هجمات MITM
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json'
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log("WC API CURL Error: $curlError");
            return ['__error' => "cURL Error: $curlError", '__code' => 0];
        }

        if ($httpCode >= 400) {
            $decoded = json_decode($response, true);
            $errMsg  = $decoded['message'] ?? $response;
            error_log("WC API PUT Error [$httpCode]: $errMsg");
            return ['__error' => $errMsg, '__code' => $httpCode];
        }

        return json_decode($response, true) ?? false;
    }

    /**
     * جلب الطلبات من ووكوميرس
     */
    public function getOrders(array $params = [])
    {
        $defaults = ['per_page' => 50, 'orderby' => 'date', 'order' => 'desc'];
        return $this->request('/orders', array_merge($defaults, $params));
    }

    /**
     * جلب العملاء من ووردبريس
     */
    public function getCustomers(array $params = [])
    {
        $defaults = ['per_page' => 100, 'orderby' => 'registered_date', 'order' => 'desc'];
        return $this->request('/customers', array_merge($defaults, $params));
    }

    /**
     * جلب طلب واحد بالمعرف
     */
    public function getOrder(int $orderId)
    {
        return $this->request("/orders/{$orderId}");
    }

    /**
     * تحديث طلب في ووكوميرس
     */
    public function updateOrder(int $orderId, array $data)
    {
        return $this->putRequest("/orders/{$orderId}", $data);
    }

    /**
     * جلب المنتجات من ووكوميرس
     */
    public function getProducts(array $params = [])
    {
        $defaults = ['per_page' => 100, 'orderby' => 'date', 'order' => 'desc', 'status' => 'publish'];
        return $this->request('/products', array_merge($defaults, $params));
    }

    /**
     * تحديث منتج في ووكوميرس
     */
    public function updateProduct(int $productId, array $data)
    {
        return $this->putRequest("/products/{$productId}", $data);
    }
}

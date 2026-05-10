<?php
/**
 * SmmPanelApi.php
 * Reusable API wrapper for cheapestsmmpanels.com (and compatible SMM panels).
 * 
 * Usage:
 *   $api = new SmmPanelApi($api_url, $api_key);
 *   $balance = $api->balance();
 *   $services = $api->services();
 *   $order = $api->order(['service' => 1, 'link' => '...', 'quantity' => 100]);
 */

class SmmPanelApi
{
    /** @var string API endpoint URL */
    public string $api_url;

    /** @var string API key */
    public string $api_key;

    /** @var int|null Last cURL error code */
    public ?int $last_error_code = null;

    /** @var string|null Last cURL error message */
    public ?string $last_error_msg = null;

    public function __construct(string $api_url, string $api_key)
    {
        $this->api_url = rtrim($api_url, '/');
        $this->api_key = $api_key;
    }

    // -------------------------------------------------------------------------
    // PUBLIC API METHODS
    // -------------------------------------------------------------------------

    /** Get account balance */
    public function balance(): ?object
    {
        return $this->request(['action' => 'balance']);
    }

    /** Get all available services */
    public function services(): ?array
    {
        $result = $this->request(['action' => 'services']);
        return is_array($result) ? $result : null;
    }

    /**
     * Place a new order.
     * 
     * @param array $data  Must include: service, link, quantity (and any extras like runs/interval)
     */
    public function order(array $data): ?object
    {
        return $this->request(array_merge(['action' => 'add'], $data));
    }

    /**
     * Get status of a single order.
     * 
     * @param int|string $order_id  The SMM panel's order ID (not your local ID)
     */
    public function status($order_id): ?object
    {
        return $this->request([
            'action' => 'status',
            'order'  => $order_id,
        ]);
    }

    /**
     * Get status of multiple orders.
     * 
     * @param array $order_ids  Array of SMM panel order IDs
     */
    public function multiStatus(array $order_ids): ?array
    {
        $result = $this->request([
            'action' => 'status',
            'orders' => implode(',', $order_ids),
        ]);
        return is_array($result) ? $result : null;
    }

    /**
     * Request refill for a single order.
     */
    public function refill(int $order_id): ?object
    {
        return $this->request([
            'action' => 'refill',
            'order'  => $order_id,
        ]);
    }

    /**
     * Request refill for multiple orders.
     */
    public function multiRefill(array $order_ids): ?array
    {
        $result = $this->request([
            'action' => 'refill',
            'orders' => implode(',', $order_ids),
        ]);
        return is_array($result) ? $result : null;
    }

    /**
     * Get refill status.
     */
    public function refillStatus(int $refill_id): ?object
    {
        return $this->request([
            'action' => 'refill_status',
            'refill' => $refill_id,
        ]);
    }

    /**
     * Get multiple refill statuses.
     */
    public function multiRefillStatus(array $refill_ids): ?array
    {
        $result = $this->request([
            'action'  => 'refill_status',
            'refills' => implode(',', $refill_ids),
        ]);
        return is_array($result) ? $result : null;
    }

    /**
     * Cancel orders.
     */
    public function cancel(array $order_ids): ?array
    {
        $result = $this->request([
            'action' => 'cancel',
            'orders' => implode(',', $order_ids),
        ]);
        return is_array($result) ? $result : null;
    }

    // -------------------------------------------------------------------------
    // INTERNAL HTTP LAYER
    // -------------------------------------------------------------------------

    /**
     * Make a POST request to the SMM panel API.
     * 
     * @param array $params  Parameters to send (key is added automatically)
     * @return object|array|null  Decoded JSON, or null on failure
     */
    private function request(array $params)
    {
        $params['key'] = $this->api_key;

        // Build URL-encoded body
        $body = http_build_query($params);

        $ch = curl_init($this->api_url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HEADER         => false,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'JZStore-SMMClient/1.0',
        ]);

        $raw = curl_exec($ch);
        $this->last_error_code = curl_errno($ch);
        $this->last_error_msg  = curl_error($ch);
        curl_close($ch);

        if ($this->last_error_code !== 0 || $raw === false || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, false); // stdClass for objects
        if ($decoded === null) {
            // Try associative (some panels return arrays)
            $decoded = json_decode($raw, true);
        }

        return $decoded;
    }
}

<?php
class OrderModel extends Model {
    public function create($type, $item) {
        if (in_array($type, ['pro','pro-users'], true) && in_array($item, ['monthly','yearly'], true)) {
            $audience = $type === 'pro' ? 'artist' : 'user';
            $term = $item === 'monthly' ? 'month' : 'yearly';
            $price = config("pro-$audience-$term-price", $item === 'monthly' ? 5 : 55);
        } elseif ($type === 'track') {
            $price = $this->C->model('track')->findTrack($item)['price'] ?? null;
        } elseif ($type === 'album') {
            $price = $this->C->model('track')->findPlaylist($item)['price'] ?? null;
        } elseif ($type === 'video' && moduleExists('video')) {
            $price = $this->C->model('video::video')->find($item)['price'] ?? null;
        } else {
            $price = null;
        }
        if (!is_numeric($price) || $price <= 0) { http_response_code(400); exit('Invalid purchase'); }
        $id = bin2hex(random_bytes(16));
        $amount = number_format(formatMoneyTotal($price),2,'.','');
        $this->db->query("INSERT INTO checkout_orders (id,userid,item_type,item_id,base_price,amount,currency,created_at) VALUES(?,?,?,?,?,?,?,?)",
            $id, $this->C->model('user')->authId, $type, $item, $price, $amount, $this->C->model('user')->getUserCurrency(), time());
        $_SESSION['checkout_order'] = $id;
        return $amount;
    }

    public function current($type, $item, $provider) {
        $order = $this->db->query("SELECT * FROM checkout_orders WHERE id=? AND userid=? AND item_type=? AND item_id=? AND state='pending' AND created_at>?",
            $_SESSION['checkout_order'] ?? '', $this->C->model('user')->authId, $type, $item,time()-3600)->fetch(PDO::FETCH_ASSOC);
        if (!$order || ($order['provider'] && $order['provider'] !== $provider)) { http_response_code(409); exit('Checkout expired'); }
        if (!$order['provider']) {
            $supported = ($provider === 'paypal' && userCurrencySupportPaypal()) || ($provider === 'stripe' && userCurrencySupportStripe()) || ($provider === 'mollie' && userCurrencySupportMollie()) || $provider === 'bank';
            if (!$supported) {
                $order['amount'] = $order['base_price'];
                $order['currency'] = config('currency-converter-source','USD');
            }
            $this->db->query("UPDATE checkout_orders SET amount=?,currency=?,provider=? WHERE id=? AND provider IS NULL",
                $order['amount'],$order['currency'],$provider,$order['id']);
        }
        return $order;
    }

    public function fulfill($order, $provider, $reference, $paid, $currency) {
        if (!$reference || !is_numeric($paid) || number_format($paid,2,'.','') !== number_format($order['amount'],2,'.','') || strtoupper($currency) !== strtoupper($order['currency'])) {
            http_response_code(409); exit('Payment mismatch');
        }
        $this->db->query('START TRANSACTION');
        try {
            $changed = $this->db->query("UPDATE checkout_orders SET state='paid',settlement_id=? WHERE id=? AND provider=? AND state='pending'", $reference,$order['id'],$provider);
            if ($changed->rowCount()) {
                $user = $this->C->model('user')->authUser;
                $this->C->model('admin')->addTransaction([
                    'amount'=>$order['base_price'],'type'=>$order['item_type'],'type_id'=>$order['item_id'],
                    'sale_id'=>$reference,'userid'=>$user['id'],'email'=>$user['email'],'name'=>$user['full_name'],'country'=>$user['country'],
                ]);
                Hook::getInstance()->fire('payment.success', null, [$order['item_type'],$order['item_id']]);
            }
            $this->db->query('COMMIT');
        } catch (Throwable $e) {
            $this->db->query('ROLLBACK');
            throw $e;
        }
    }
}

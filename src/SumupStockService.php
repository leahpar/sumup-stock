<?php

if (!class_exists('Transaction')) {
    require_once 'Transaction.php';
}

class SumupStockService
{
    public function __construct(
        private readonly string $SK,
        private readonly string $api
    ) {}

    private function get($url, $params)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api.$url.'?'. http_build_query($params));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Authorization: Bearer ' . $this->SK,
            'Accept: application/json'
        ));
        $json = curl_exec($ch);
        curl_close($ch);

        return json_decode($json);
    }

    public function getTransactions(string $date_start, string $date_end)
    {
        $data = $this->get(
            "/financials/transactions",
            [
                'start_date' => $date_start,
                'end_date' => $date_end,
            ]
        );

        if (isset($data->error_message)) {
            throw new \Exception($data->error_message);
        }

        $transactions = [];
        foreach ($data as $tr) {

            // On ne prend pas les transactions de remboursements
            if ($tr->type == 'REFUND') continue;

            $transaction = new Transaction(
                id: $tr->id,
                amount: $tr->amount,
                timestamp: $tr->timestamp,
            );
            $details = $this->get(
                "/transactions",
                ['id' => $tr->id]
            );

            foreach ($details->events as $event) {
                if ($event->type == 'REFUND') {
                    $transaction->refund += $event->amount;
                }
            }

            $transaction->products = $details->products ?? [];
            $transactions[] = $transaction;

            //echo '<pre>';
            //echo "====================================\n";
            //var_dump($tr, $details);
            //echo '</pre>';
        }

        return $transactions;
    }

    public function mapTransactions2ProductsIds(array $transactions)
    {
        /** @var Transaction $t */
        foreach ($transactions as $t) {
            foreach ($t->products as &$p) {
                $products = wc_get_products(['reference-sumup' => $p->name]);
                if (count($products) == 1) {
                    $p->wc_product_id = $products[0]->get_id();
                }
                else {
                    $p->wc_product_id = null;
                }
            }
        }
    }

    public function mapTransactions2ordersIds(array $transactions)
    {
        /** @var Transaction $t */
        foreach ($transactions as $t) {
            $t->wc_order = $this->getOrder($t->id);
        }
    }

    public function import(array $transactions, array $post): int
    {
        $cpt = 0;
        $toImport = $post['toImport'] ?? [];
        /** @var Transaction $t */
        foreach ($transactions as $t) {

            // Pas à importer
            if (!in_array($t->id, $toImport)) continue;

            // Déjà importé
            if ($t->wc_order) continue;

            // Pas importable
            if (!$t->hasAllProducts()) continue;

            // On importe !
            $t->wc_order = $this->newOrder($t);
            $cpt++;
        }
        return $cpt;
    }

    public function getOrder(string $sumupTrId): ?WC_Order
    {
        $order = wc_get_orders([
            'meta_key' => 'sumup_transaction',
            'meta_value' => $sumupTrId
            //'sumup_transaction' => $sumupTrId
        ]);
        return $order[0] ?? null;
    }

    public function newOrder(Transaction $transaction): WC_Order
    {
        // https://rudrastyh.com/woocommerce/create-orders-programmatically.html
        /** @var WC_Order $order */
        $order = wc_create_order();

        foreach ($transaction->products as $p) {
            $order->add_product(
                wc_get_product($p->wc_product_id),
                $p->quantity,
                [
                    'subtotal' => $p->price * $p->quantity,
                    'total' => $p->price * $p->quantity,
                ]
            );
        }

        $order->set_date_created($transaction->date);
        $order->set_date_paid($transaction->date);
        $order->set_date_completed($transaction->date);

        $order->add_meta_data('sumup_transaction', $transaction->id);

        $order->calculate_totals();
        $order->set_status('wc-completed');
        $order->save();

        // Remboursement
        if ($transaction->refund > 0) {
            $refund = wc_create_refund([
                'amount' => $transaction->refund,
                'order_id' => $order->get_id(),
                'date_created' => $transaction->date->format('Y-m-d H:i:s'),
            ]);
            $refund->save();
        }

        return $order;
    }

}

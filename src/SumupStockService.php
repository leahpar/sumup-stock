<?php

class SumupStockService
{
    public function __construct(
        private readonly string $SK,
        private readonly string $api
    ) {}

    private function get($url, $params) {

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->api . $url . '?' . http_build_query($params));
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
        $transactions = $this->get(
            "/financials/transactions",
            [
                'start_date' => $date_start,
                'end_date' => $date_end,
            ]
        );

        foreach ($transactions as $transaction) {
            $transaction->date = (new \DateTime($transaction->timestamp));
            $details = $this->get(
                "/transactions",
                ['id' => $transaction->id]
            );
            $transaction->products = $details->products;
        }

        return $transactions;
    }

    public function flatternTransactions(array $transactions)
    {
        $data = [];
        foreach ($transactions as $t) {
            $data[] = [
                'date' => (new \DateTime($t->timestamp))->format('d/m/Y H:i:s'),
                'amount' => $t->amount,
                //'name' => $t->products[0]->name,
                //'quantity' => $t->products[0]->quantity,
                //'price' => $t->products[0]->price_with_vat,
                'products' => $t->products,
            ];
        }
        return $data;
    }

    public function mapTransactions2ProductsIds(array &$transactions)
    {
        foreach ($transactions as &$t) {
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

    public function import(array $transactions)
    {
        foreach ($transactions as $t) {
            foreach ($t->products as $p) {
                if ($p->wc_product_id) {
                    $product = wc_get_product($p->wc_product_id);
                    $product->set_stock_quantity($product->get_stock_quantity() - $p->quantity);
                    $product->save();
                }
            }
        }
    }

}

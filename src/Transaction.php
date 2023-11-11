<?php

class Transaction
{

    public array $products = [];
    public ?WC_Order $wc_order = null;
    public \DateTime $date;
    public int $refund = 0;
    public ?string $type = null;

    public function __construct(
        public readonly string $id,
        public readonly string $code,
        public readonly float $amount,
        public readonly string $timestamp,
    ) {
        $this->date = (new WC_DateTime($this->timestamp));
    }

    public function hasAllProducts(): bool
    {
        foreach ($this->products as $p) {
            if (!$p->wc_product_id) {
                return false;
            }
        }
        return true;
    }

    public function getNbProducts(): int
    {
        return array_reduce($this->products, fn($a, $p) => $a + $p->quantity, 0);
    }

    public function isImportEnabled(): bool
    {
        return $this->wc_order === null
            && $this->hasAllProducts()
            && count($this->products) > 0;
    }

    public function setTypePayment(?string $type, ?object $card): void
    {
        if ($type == "POS" && $card) {
            $this->type = 'CB';
        }
        elseif ($type == "CASH") {
            $this->type = 'CASH';
        }
    }

}

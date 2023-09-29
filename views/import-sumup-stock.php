<div id="ssi">

<h1>SumupStock Importer</h1>

<style>
    #ssi {
        padding: 1em;
    }
    #ssi table {
        border-collapse: collapse;
    }
    #ssi table, #ssi th, #ssi td {
        border: 1px solid black;
    }
    #ssi th, #ssi td {
        padding: 0.5em;
    }
    #ssi th {
        background-color: #ababab;
    }
    #ssi > form {
        border: 1px solid #ccc;
        padding: 1em;
        margin-bottom: 1em;
    }
</style>

<form method="post">
    <button name="modifier" value="-1">&lt;</button>
    <input type="date" name="sumup_date" value="<?= $date ?>">
    <button name="modifier" value="+1">&gt;</button>
    <input type="submit" value="Précharger">
</form>

<?php if ($data !== null): ?>

    <form method="post">

        <h2>Transactions du <?= (new \DateTIme($date))->format("d/m/Y") ?></h2>

        <?php if ($message): ?>
            <h3><?= $message ?></h3>
        <?php endif ?>

        <table>
            <thead>
                <tr>
                    <th>
                        <input type=checkbox
                               onclick="document.querySelectorAll('input[name=\'toImport[]\']:not(:disabled)').forEach(e => e.checked = this.checked)"
                               checked>
                    </th>
                    <th>Date</th>
                    <th>Commande</th>
                    <th>Montant</th>
                    <th>Référence</th>
                    <th>Produit</th>
                    <th>Quantité</th>
                    <th>Prix</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($data)): ?>
                    <tr>
                        <td colspan="99">Aucune transaction</td>
                    </tr>
                <?php endif ?>

                <?php /** @var Transaction $transaction */ ?>
                <?php foreach ($data as $transaction): ?>
                    <tr>
                    <?php $rowspan = 'rowspan="'.count($transaction->products).'"' ?>
                        <td <?= $rowspan?>>
                            <input type="checkbox" name="toImport[]" value="<?= $transaction->id ?>"
                                <?php if ($transaction->wc_order) : echo "disabled" ?>
                                <?php elseif (!$transaction->hasAllProducts()): echo "disabled" ?>
                                <?php else: echo "checked" ?>
                                <?php endif ?>
                            >
                        </td>
                        <td <?= $rowspan?>>
                            <?= $transaction->date->format('d/m/Y H:i:s') ?>
                            
                        </td>
                        <td <?= $rowspan?>>
                            <?php if ($transaction->wc_order): ?>
                                <?php $url = $transaction->wc_order->get_edit_order_url() ?>
                                <a href="<?= $url ?>"
                                   class="order-view"
                                   target="_blank">
                                    <strong>#<?= $transaction->wc_order->get_id() ?></strong>
                                </a>
                            <?php endif ?>
                        </td>
                        <td <?= $rowspan?>>
                            <?= $transaction->amount ?> €
                            
                        </td>
                        <?php $first = true ?>
                        <?php foreach ($transaction->products as $product): ?>
                            <?php if (!$first): ?>
                                <tr>
                            <?php endif ?>
                            <td><?= $product->name ?></td>
                            <td><?= $product->wc_product_id ? "✅" : "⚠️" ?></td>
                            <td><?= $product->quantity ?></td>
                            <td><?= $product->price_with_vat ?> €</td>
                            <?php if (!$first): ?>
                                </tr>
                            <?php endif ?>
                            <?php $first = false ?>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
            <tfoot>
                <tr>
                    <th></th>
                    <th><?= count($data) ?> transactions</th>
                    <th><?= array_reduce($data, fn($a, $r) => $a + ($r->wc_order != null), 0)?> commandes</th>
                    <th><?= array_reduce($data, fn($a, $r) => $a + $r->amount, 0)?> €</th>
                    <th><?= array_reduce($data, fn($a, $r) => $a + $r->getNbProducts(), 0)?> Produits</th>
                    <th></th>
                    <th></th>
                    <th></th>
                </tr>
            </tfoot>
        </table>

<!--        --><?php //if ($action != 'import'): ?>
        <p>
            <input type="hidden" name="sumup_date" value="<?= $date ?>">
            <input type="hidden" name="action" value="import">
            <input type="submit" value="Importer les commandes sélectionnées" onclick="return confirm('Êtes-vous sûr ?')">
        </p>
<!--        --><?php //endif ?>

    </form>

<?php endif ?>

</div>

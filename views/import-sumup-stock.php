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
    <input type="date" name="sumup_date" value="<?= $date ?>">
    <input type="submit" value="Précharger">
</form>

<?php if ($data !== null): ?>

    <form method="post">

        <h2>Import du <?= $date ?></h2>

        <?php if ($message): ?>
            <h3><?= $message ?></h3>
        <?php endif ?>

        <table>
            <thead>
                <tr>
                    <th>Date</th>
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

                <?php foreach ($data as $row): ?>
                    <tr>
                        <td rowspan="<?= count($row->products) ?>"><?= $row->date->format('d/m/Y H:i:s') ?></td>
                        <td rowspan="<?= count($row->products) ?>"><?= $row->amount ?></td>
                        <?php $first = true ?>
                        <?php foreach ($row->products as $product): ?>
                            <?php if (!$first): ?>
                                <tr>
                            <?php endif ?>
                            <td><?= $product->name ?></td>
                            <td><?= $product->wc_product_id ? "✅" : "⚠️" ?></td>
                            <td><?= $product->quantity ?></td>
                            <td><?= $product->price_with_vat ?></td>
                            <?php if (!$first): ?>
                                </tr>
                            <?php endif ?>
                            <?php $first = false ?>
                        <?php endforeach ?>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>

        <?php if ($action != 'import'): ?>
        <p>
            <input type="hidden" name="sumup_date" value="<?= $date ?>">
            <input type="hidden" name="action" value="import">
            <input type="submit" value="Mettre à jour les stocks" onclick="return confirm('Êtes-vous sûr ?')">
        </p>
        <?php endif ?>

    </form>

<?php endif ?>

</div>

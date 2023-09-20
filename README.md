# Sumup Stock

Plugin de MAJ des stocks WooCommerce depuis Sumup

## Utilisation

### Paramétrage

Renseigner la clé API Sumup dans le menu `Réglages` > `SumupStock`


### Référence Sumup

Indiquer la référence Sumup dans l'attribut' "Référence Sumup" de la fiche produit WooCommerce.

![img.png](img.png)


### Mise à jour des stocks

1. Aller sur la page de l'outil dans le menu `Outils` > `SumupStock Importer`
2. Renseigner la date du jour à importer
![img_1.png](img_1.png)
3. Cliquer sur le bouton `Précharger`
4. Les transactions du jour sont affichées, en indiquant si le produit est référencé sur WooCommerce.
![img_2.png](img_2.png)
5. Cliquer sur le bouton `Mettre à jour les stocks`
6. Enjoy !
7. 

## Améliorations possibles

- [ ] Ajouter des cases à cocher pour choisir les produits à mettre à jour
- [ ] Gestion de l'historique des transactions (pour ne pas importer 2 fois la même transaction)


## Documentation

### Woocommerce dev

Search : https://github.com/woocommerce/woocommerce/wiki/wc_get_products-and-WC_Product_Query

Product : https://github.com/woocommerce/woocommerce/blob/3611d4643791bad87a0d3e6e73e031bb80447417/plugins/woocommerce/includes/wc-product-functions.php

Stock : https://github.com/woocommerce/woocommerce/blob/3611d4643791bad87a0d3e6e73e031bb80447417/plugins/woocommerce/includes/wc-stock-functions.php


### Woocommerce Plugin

https://github.com/woocommerce/woocommerce/tree/trunk/docs/extension-development


### Wordpress plugin

DB : https://codex.wordpress.org/Creating_Tables_with_Plugins


### Woocommerce REST API

https://woocommerce.com/document/woocommerce-rest-api/

https://github.com/woocommerce/woocommerce/blob/trunk/docs/rest-api/getting-started.md


### Sumup API

https://developer.sumup.com/docs/online-payments/introduction/register-app/

Oauth : https://developer.sumup.com/docs/api/generate-a-token/

https://developer.sumup.com/docs/api/list-financial-transactions/


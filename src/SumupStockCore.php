<?php

if (!class_exists('RationalOptionPages')) {
    require_once 'RationalOptionPages.php';
}

class SumupStockCore
{
    private array $options;

    public function init()
    {
        // Gestion options et page de configuration du plugin
        $this->options();

        // Check options
        if (empty($this->options['sumup_api_key'])) {
            $msg = "SumupStock : La clé API n'est pas renseignée ";
            $msg .= "(<a href='".admin_url('options-general.php?page=sumupstock')."'>ici</a>)";
            add_action('admin_notices', function() use ($msg) {
                ?>
                <div class="notice notice-warning">
                    <p><?= $msg ?></p>
                </div>
                <?php
            });
        }
        if ($this->options['sumup_cron_enabled'] !== false && empty($this->options['sumup_cron_mail_cr'])) {
            $msg = "SumupStock : L'email n'est pas renseigné ";
            $msg .= "(<a href='".admin_url('options-general.php?page=sumupstock')."'>ici</a>)";
            add_action('admin_notices', function() use ($msg) {
                ?>
                <div class="notice notice-warning">
                    <p><?= $msg ?></p>
                </div>
                <?php
            });
        }

        // Menu admin
        add_action('admin_menu', [$this, 'adminMenuEntry']);

        // Hook de mise à jour du plugin
        add_filter('site_transient_update_plugins', [$this, 'sumup_stock_push_update']);
        add_action('upgrader_process_complete', [$this, 'sumup_stock_after_update'], 10, 2);

        // Hook de mise à jour automatique (CRON)
        add_action('sumup_stock_cron_auto_import', [$this, 'sumup_stock_cron_auto_import']);
    }

    public static function notif($type, $message)
    {
        ?>
        <div class="notice notice-<?= $type ?>">
            <p><?= $message ?></p>
        </div>
        <?php
    }

    /**
     * Paramétrage du plugin
     * https://github.com/jeremyHixon/RationalOptionPages
     */
    public function options()
    {
        $pages = [
            'sumup-stock' => [
                'page_title' => "SumupStock",
                'parent_slug' => 'options-general.php',
                'sections' => [
                    'general' => [
                        'title' => " ",
                        'fields' => [
                            'sumup_api_key' => [
                                'id' => 'sumup_api_key',
                                'title' => "SumUp API Secret Key",
                            ],
                            'sumup_cron_enabled' => [
                                'id' => 'sumup_cron_enabled',
                                'title' => "Activation de l'import automatique",
                                'type' => 'checkbox',
                            ],
                            'sumup_cron_mail_cr' => [
                                'id' => 'sumup_cron_mail_cr',
                                'title' => "Mail destinataire compte rendu import automatique",
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $options_page = new RationalOptionPages($pages);
        $this->options = get_option('sumup-stock', []);
    }

    public function adminMenuEntry()
    {
        add_submenu_page(
            'tools.php',
            'SumupStock Importer',
            'SumupStock Importer',
            'manage_options',
            'sumup-stock-importer',
            [$this, 'adminPageContent'],
            //'dashicons-database-import',
            //75
        );
    }

    public function adminPageContent() {
        $data = null;
        $message = null;
        $action = $_POST['action'] ?? null;
        $date = $_POST['sumup_date'] ?? $_GET['sumup_date'] ?? date("Y-m-d");

        if ($_POST['modifier']??false) {
            $date = (new \DateTime($date))->modify($_POST['modifier'] . ' day')->format('Y-m-d');
        }

        if (!class_exists('SumupStockService')) {
            require_once 'SumupStockService.php';
        }

        $data = [];
        try {

            $t = microtime(true);
            $sumup = new SumupStockService(
                SK: $this->options['sumup_api_key'],
                api: "https://api.sumup.com/v0.1/me"
            );
            $transactions = $sumup->getTransactions(
                date_start: $date,
                date_end: $date,
                filterOutWcOrders: $action == 'import' // Import, OSEF des commandes déjà importées
            );
            $timing['sumup_api'] = microtime(true) - $t;

            $t = microtime(true);
            $sumup->mapTransactions2ProductsIds($transactions);
            $sumup->mapTransactions2ordersIds($transactions);
            $timing['wp_queries'] = microtime(true) - $t;

            if ($action == 'import') {
                $cpt = $sumup->import($transactions, $_POST);
                $message = "$cpt commandes importées";
            }

        }
        catch (\Exception $e) {
            SumupStockCore::notif("error", $e->getMessage());
        }

        include __DIR__ . '/../views/import-sumup-stock.php';
    }

    /**
     * Import automatique (CRON)
     */
    public function sumup_stock_cron_auto_import()
    {
        $date = date("Y-m-d");
        //var_dump("sumup_stock_cron_auto_import", $date);

        // Import désactivé
        if ($this->options['sumup_cron_enabled'] === false) {
            //var_dump("Import désactivé");
            return;
        }

        if (!class_exists('SumupStockService')) {
            require_once 'SumupStockService.php';
        }

        try {
            $sumup = new SumupStockService(
                SK: $this->options['sumup_api_key'],
                api: "https://api.sumup.com/v0.1/me"
            );
            $transactions = $sumup->getTransactions(
                date_start: $date,
                date_end: $date,
                filterOutWcOrders: true // Import auto, OSEF des commandes déjà importées
            );

            $sumup->mapTransactions2ProductsIds($transactions);
            $sumup->mapTransactions2ordersIds($transactions);

            $post['toImport'] = array_filter(
                    array_map(
                       fn(Transaction $t) => $t->isImportEnabled() ? $t->id : null,
                        $transactions,
                    )
                );
            $commandesImported = $sumup->import($transactions, $post);

            $transactionsNotImported = array_filter(
                $transactions,
                fn(Transaction $t) => !$t->isImportEnabled() && $t->wc_order === null,
            );

            if ($commandesImported > 0 || count($transactionsNotImported) > 0) {
                $message = "Import automatique SumupStock\n\n";
                $message .= "$commandesImported commandes crées\n\n";

                $message .= count($transactionsNotImported)." transaction(s) non importée(s) :\n";
                /** @var Transaction $t */
                foreach ($transactionsNotImported as $t) {
                    $message .= " - ". $t->date->format('d/m/Y H:i:s');
                    $message .= " " . str_pad($t->amount, 4, " ", STR_PAD_LEFT) . " €";
                    foreach ($t->products as $p) {
                        $message .= " | " . "$p->name";
                    }
                    if (count($t->products) == 0) {
                        $message .= " | " . "Aucun produit !";
                    }
                    $message .= "\n";
                }
                $message .= "\n\n";
                $message .= "https://alambicsducoq.fr/wp-admin/tools.php?page=sumup-stock-importer&sumup_date=$date";
                $message .= "\n\n";
                $res = wp_mail(
                    $this->options['sumup_cron_mail_cr'],
                    "SumupStock - Transactions non importées",
                    $message,
                );
                //var_dump(["res" => $res, $message]);
            }

        }
        catch (\Exception $e) {
            $res = wp_mail(
                $this->options['sumup_api_mail_cr'],
                "SumupStock - Erreur import automatique",
                $e->getMessage(),
            );
            //var_dump(["res2" => $res, $message]);
        }
    }

    /**
     * Check d'une nouvelle version du plugin
     */
    function sumup_stock_push_update($transient)
    {
        if (empty($transient->checked)) {
            return $transient;
        }

        if (!$remote = get_transient('sumup_stock_push_update')) {

            // info.json is the file with the actual plugin information on your server
            $remote = wp_remote_get(SUMUP_STOCK_JSON_URL, [
                    'timeout' => 10,
                    'headers' => [
                        'Accept' => 'application/json'
                    ]]
            );

            if (!is_wp_error($remote)
                && isset($remote['response']['code'])
                && $remote['response']['code'] == 200
                && !empty($remote['body'])
            ) {
                set_transient('sumup_stock_push_update', $remote, 3600);
            }
        }

        // Infos plugin local
        $pluginData = get_plugin_data(__DIR__ . '/../sumup-stock.php', false, false);

        if ($remote) {

            $remote = json_decode($remote['body']);

            // your installed plugin version should be on the line below! You can obtain it dynamically of course
            if ($remote
                && version_compare($pluginData['Version'] ?? 0, $remote->version, '<')
                && version_compare($remote->requires, get_bloginfo('version'), '<')) {
                $res = new stdClass();
                $res->slug = 'sumup-stock';
                $res->plugin = 'sumup-stock/sumup-stock.php';
                $res->new_version = $remote->version;
                $res->tested = $remote->tested;
                $res->package = $remote->download_url;
                $transient->response[$res->plugin] = $res;
                //$transient->checked[$res->plugin] = $remote->version;
            }

        }
        return $transient;
    }

    function sumup_stock_after_update($upgrader_object, $options)
    {
        if ($options['action'] == 'update' && $options['type'] === 'plugin') {
            // just clean the cache when new plugin version is installed
            delete_transient('sumup_stock_push_update');
        }
    }

}




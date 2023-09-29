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

        // Menu admin
        add_action('admin_menu', [$this, 'adminMenuEntry']);


        // Hook de mise à jour du plugin
        add_filter('site_transient_update_plugins', [$this, 'sumup_stock_push_update']);
        add_action('upgrader_process_complete', [$this, 'sumup_stock_after_update'], 10, 2);
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
                            //'product_reference_field' => [
                            //    'id' => 'product_reference_field',
                            //    'title' => "Nom de l'attribut produit contenant la référence SumUp",
                            //],
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
        $date = $_POST['sumup_date'] ?? date("Y-m-d");

        if ($_POST['modifier']??false) {
            $date = (new \DateTime($date))->modify($_POST['modifier'] . ' day')->format('Y-m-d');
        }

        if (!class_exists('SumupStockService')) {
            require_once 'SumupStockService.php';
        }

        $sumup = new SumupStockService(
            SK: $this->options['sumup_api_key'],
            api: "https://api.sumup.com/v0.1/me"
        );
        $transactions = $sumup->getTransactions(
            date_start: $date,
            date_end:   $date
        );

        $sumup->mapTransactions2ProductsIds($transactions);
        $sumup->mapTransactions2ordersIds($transactions);

        $data = $transactions;

        if ($action == 'import') {
            $cpt = $sumup->import($data, $_POST);
            $message = "$cpt commandes importées";
        }

        include __DIR__ . '/../views/import-sumup-stock.php';
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




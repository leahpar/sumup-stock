<?php

class SumupStockActivator
{
    public static function activate()
    {
        if (!wp_next_scheduled('sumup_stock_cron_auto_import')) {
            $time = strtotime('now');
            wp_schedule_event($time, 'hourly', 'sumup_stock_cron_auto_import', [], true);
        }
    }

    public static function deactivate()
    {
        wp_clear_scheduled_hook('sumup_stock_cron_auto_import');
    }

}

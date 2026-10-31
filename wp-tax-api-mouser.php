<?php declare(strict_types=1);

/**
 * Plugin Name:       Taxmod Mouser
 * Description:       Mouser for the Taxonomy Modeller: reads order mails from Taxmod Mail and searches parts over the Mouser API for the purchasing page.
 * Version:           0.1.0
 * Requires at least: 6.9
 * Requires PHP:      8.2
 * Author:            Stefan Fambach (SteFam), Claude (Anthropic AI)
 * Author URI:        https://www.fambach.net
 * License:           PolyForm Noncommercial 1.0.0
 * License URI:       https://polyformproject.org/licenses/noncommercial/1.0.0/
 * Text Domain:       taxapimouser
 * GitHub Plugin URI: sfambach/wp_tax_api_mouser
 * Primary Branch:    main
 *
 * @see https://github.com/sfambach/wp-taxonomy-modeler/blob/main/docs/pakete/import/module-schnitt.md
 */

if (! defined('ABSPATH')) {
    exit;
}

$taxapimouserAutoloader = __DIR__ . '/vendor/autoload.php';

if (! is_readable($taxapimouserAutoloader)) {
    add_action('admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Taxmod Mouser:</strong> '
            . esc_html__('run "composer install" in the plugin folder — the autoloader is missing.', 'taxapimouser')
            . '</p></div>';
    });

    return;
}

require_once $taxapimouserAutoloader;

TaxApiMouser\WordPress\Plugin::boot();

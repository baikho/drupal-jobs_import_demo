<?php

declare(strict_types=1);

namespace Drupal\jobs_import_demo\Plugin\migrate\source;

use Drupal\jobs_import_demo\Service\FeedEndpoint;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate_plus\DataParserPluginInterface;
use Drupal\migrate_plus\DataParserPluginManager;
use Drupal\migrate_plus\Plugin\migrate\source\Url;

/**
 * Migrate Plus URL source for the jobs XML feed (simple_xml parser in YAML).
 *
 * Feed URL is the demo route serving fixtures/job_feed.xml. For protected HTTP feeds,
 * add an `authentication` section to the migration source in YAML (see Migrate Plus).
 *
 * @MigrateSource(
 *   id = "job_feed_url"
 * )
 */
final class JobFeedUrl extends Url {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    MigrationInterface $migration,
    DataParserPluginManager $parserPluginManager,
    private readonly FeedEndpoint $feedEndpoint,
  ) {
    $configuration['urls'] = [];
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration, $parserPluginManager);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
    ?MigrationInterface $migration = NULL,
  ): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('plugin.manager.migrate_plus.data_parser'),
      $container->get('jobs_import_demo.feed_endpoint'),
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function initializeIterator(): DataParserPluginInterface {
    $url = $this->feedEndpoint->getJobFeedUrl();
    $this->configuration['urls'] = $url === '' ? [] : [$url];
    return parent::initializeIterator();
  }

}

<?php

namespace Wpce\ThemeConfig;

use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Used for enqueueing styles and scripts (with helper for providing localization).
 * Contains helpers for core wp enqueue functions.
 */
class Enqueue {

  private $assetsLocation;
  private $releaseVersion;

  private $adminScripts = [];
  private $adminStyles = [];
  private $publicScripts = [];
  private $publicStyles = [];
  private $sharedScripts = [];
  private $sharedStyles = [];

  /**
   * @param string $assetsLocation path to directory with assets
   * @param string|bool|null $releaseVersion files version for cache busting purposes
   * @return  void
   */
  public function __construct(string $assetsLocation = '', $releaseVersion = false) {
    $this->assetsLocation = rtrim($assetsLocation, '/');
    $this->releaseVersion = $releaseVersion;
  }

  /**
   * Wrapper for wp_enqueue_script.
   * Contains built-in option to pass localization array.
   *
   * @param string $handle script handle, must be unique
   * @param string $pathToFile
   * path to file to load. Path set in constructor as assetsLocation is auto added at the beginning,
   * except for cases when a http(s) url is provided.
   * @param array $dependencies an array of script dependencies
   * @param array $localizeObject array to be passed to wp_localize_script
   * @param bool $inFooter should the script be placed in footer?
   * @return void
   */
  public function enqueueScript(string $handle, string $pathToFile, array $dependencies = [], array $localizeObject = [], bool $inFooter = true) {
    if (!str_contains($pathToFile, 'http://') && !str_contains($pathToFile, 'https://')) {
      $pathToFile = $this->assetsLocation.'/'.ltrim($pathToFile, '/');
    }
    wp_enqueue_script($handle, $pathToFile, $dependencies, $this->releaseVersion, $inFooter);
    if (!empty($localizeObject)) {
      wp_localize_script($handle, $handle, $localizeObject);
    }
  }

  public function enqueueStyle(string $handle, string $pathToFile, array $dependencies = [], string $media = 'all') {
    if (!str_contains($pathToFile, 'http://') && !str_contains($pathToFile, 'https://')) {
      $pathToFile = $this->assetsLocation.'/'.ltrim($pathToFile, '/');
    }
    wp_enqueue_style($handle, $pathToFile, $dependencies, $this->releaseVersion, $media);
  }

  public function setForQueueing(array $options) {
    $optionsResolver = new OptionsResolver;
    $optionsResolver->setDefaults([
      'admin' => function (OptionsResolver $resolver) {
        $this->configureQueueingTypeOptions($resolver);
      },
      'public' => function (OptionsResolver $resolver) {
        $this->configureQueueingTypeOptions($resolver);
      },
      'shared' => function (OptionsResolver $resolver) {
        $this->configureQueueingTypeOptions($resolver);
      },
    ]);
    $options = $optionsResolver->resolve($options);
    $this->adminScripts = $options['admin']['scripts'] ?? [];
    $this->adminStyles = $options['admin']['styles'] ?? [];
    $this->publicScripts = $options['public']['scripts'] ?? [];
    $this->publicStyles = $options['public']['styles'] ?? [];
    $this->sharedScripts = $options['shared']['scripts'] ?? [];
    $this->sharedStyles = $options['shared']['styles'] ?? [];
  }

  private function configureQueueingTypeOptions(OptionsResolver $resolver) {
    $resolver
      ->define('scripts')
      ->allowedTypes('array[]')
      ->allowedValues(static function(array &$elements): bool {
        $subResolver = new OptionsResolver;
        self::configureScriptsTypeOptions($subResolver);
        $elements = array_map([$subResolver, 'resolve'], $elements);
        return true;
      });

    $resolver
      ->define('styles')
      ->allowedTypes('array[]')
      ->allowedValues(static function(array &$elements): bool {
        $subResolver = new OptionsResolver;
        self::configureScriptsTypeOptions($subResolver);
        $elements = array_map([$subResolver, 'resolve'], $elements);
        return true;
      });
  }

  static function configureScriptsTypeOptions(OptionsResolver $resolver) {
    $resolver
      ->define('0') // handle
      ->required()
      ->allowedTypes('string');

    $resolver
      ->define('1') // pathToFile
      ->required()
      ->allowedTypes('string');

    $resolver
      ->define('2') // dependencies
      ->allowedTypes('array');

    $resolver
      ->define('3') // localizeObject
      ->allowedTypes('array');

    $resolver
      ->define('4') // inFooter
      ->allowedTypes('boolean');
  }

  static function configureStylesTypeOptions(OptionsResolver $resolver) {
    $resolver
      ->define('0') // handle
      ->required()
      ->allowedTypes('string');

    $resolver
      ->define('1') // pathToFile
      ->required()
      ->allowedTypes('string');

    $resolver
      ->define('2') // dependencies
      ->allowedTypes('array');

    $resolver
      ->define('3') // media
      ->allowedTypes('string');
  }

  private function enqueueFromArray(array $arraysOfArgs, string $method) {
    foreach($arraysOfArgs as $args) {
      call_user_func_array([$this, $method], $args);
    }
  }

  public function enqueueAdminStylesAndScripts() {
    $this->enqueueFromArray($this->adminScripts, 'enqueueScript');
    $this->enqueueFromArray($this->adminStyles, 'enqueueStyle');
    $this->enqueueSharedStylesAndScripts();
  }

  public function enqueuePublicStylesAndScripts() {
    $this->enqueueFromArray($this->publicScripts, 'enqueueScript');
    $this->enqueueFromArray($this->publicStyles, 'enqueueStyle');
    $this->enqueueSharedStylesAndScripts();
  }

  private function enqueueSharedStylesAndScripts() {
    $this->enqueueFromArray($this->sharedScripts, 'enqueueScript');
    $this->enqueueFromArray($this->sharedStyles, 'enqueueStyle');
  }

  public function enqueueAllOnInit() {
    add_action('admin_head', [$this, 'enqueueAdminStylesAndScripts']);
    add_action('wp_enqueue_scripts', [$this, 'enqueuePublicStylesAndScripts'], 20);
  }
}

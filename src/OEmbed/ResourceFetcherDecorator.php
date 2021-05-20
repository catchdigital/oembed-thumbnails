<?php

namespace Drupal\oembed_thumbnails\OEmbed;

use Drupal\Core\Cache\CacheBackendInterface;
use GuzzleHttp\ClientInterface;
use Drupal\media\OEmbed\ResourceFetcherInterface;
use Drupal\media\OEmbed\ProviderRepositoryInterface;
use Drupal\media\OEmbed\ResourceFetcher;
use Drupal\media\OEmbed\Resource;

/**
 * Decorates OEmbed RosourceFetcher service.
 */
class ResourceFetcherDecorator implements ResourceFetcherInterface  {

  /**
   * Constructs a ResourceFetcher object.
   *
   * @param Drupal\media\OEmbed\ResourceFetcher $decorated
   *   The Decorated service.
   * @param \GuzzleHttp\ClientInterface $http_client
   *   The HTTP client.
   * @param \Drupal\media\OEmbed\ProviderRepositoryInterface $providers
   *   The oEmbed provider repository service.
   * @param \Drupal\Core\Cache\CacheBackendInterface $cache_backend
   *   (optional) The cache backend.
   */
  public function __construct(ResourceFetcher $decorated, ClientInterface $http_client, ProviderRepositoryInterface $providers, CacheBackendInterface $cache_backend = NULL) {
    $this->decorated = $decorated;
  }

  /**
   * {@inheritdoc}
   */
  public function fetchResource($url) {
    $data = $this->decorated->fetchResource($url);

    if ($data->getType() == 'video') {
      $provider = $data->getProvider()->getName() ?? NULL;
      switch (strtolower($provider)) {
        case 'youtube':
          $thumbnail_url = $data->getThumbnailUrl();
          $thumbnail_uri = str_replace('hqdefault', 'maxresdefault', $thumbnail_url->getUri());
          break;
        case 'vimeo':
          $thumbnail_url = $data->getThumbnailUrl()->getUri();
          // $thumbnail_uri = str_replace('hqdefault', 'maxresdefault', $thumbnail_url->getUri());
          $url = pathinfo($thumbnail_url);
          $original_name = $url['filename'];
          $original_name_sections = explode('_', $original_name);
          $current_resolution = end($original_name_sections);
          $new_name = str_replace($current_resolution, '1080', $original_name);
          $thumbnail_uri = str_replace($original_name, $new_name, $thumbnail_url);
          break;
        default:
          break;
      }

      // If thumbnail doesn't exists, return original object.
      $headers = get_headers($thumbnail_uri);
      if (strpos($headers[0], '404 Not Found') > 0) {
        return $data;
      }

      // Get size of new image.
      $thumb_size = getimagesize($thumbnail_uri);
      $thumbnail_width = $thumb_size[0];
      $thumbnail_height = $thumb_size[1];

      // Create a new object with new thumbnails.
      $data = Resource::video(
        $data->getHtml(),
        $data->getWidth(),
        $data->getHeight(),
        $data->getProvider(),
        $data->getTitle(),
        $data->getAuthorName(),
        $data->getAuthorUrl(),
        $data->getCacheMaxAge(),
        $thumbnail_uri ?? $data->getThumbnailUrl(),
        $thumbnail_width ?? $data->getThumbnailWidth(),
        $thumbnail_height ?? $data->getThumbnailHeight(),
      );
    }

    return $data;
  }

  /**
   * {@inheritdoc}
   */
  public function __call($method, $args) {
    return call_user_func_array(array($this->decorated, $method), $args);
  }
}

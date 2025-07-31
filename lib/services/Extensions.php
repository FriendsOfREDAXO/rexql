<?php

namespace FriendsOfRedaxo\RexQL\Services;

/**
 * rexQL - GraphQL API for REDAXO CMS
 * 
 * @var rex_addon $this
 * @psalm-scope-this rex_addon
 */

use FriendsOfRedaxo\RexQL\Cache;
use FriendsOfRedaxo\RexQL\Webhook;
use rex_extension;

class Extensions
{

  public static function registerWebhookEps(): void
  {
    $extensionPoints = [
      'ART_ADDED',
      'ART_DELETED',
      'ART_MOVED',
      'ART_STATUS',
      'ART_UPDATED',
      'ART_SLICES_COPY',
      'SLICE_ADDED',
      'SLICE_UPDATE',
      'SLICE_MOVE',
      'SLICE_DELETE',
      'SLICE_STATUS',
      'CAT_ADDED',
      'CAT_DELETED',
      'CAT_MOVED',
      'CAT_STATUS',
      'CAT_UPDATED',
      'CLANG_ADDED',
      'CLANG_DELETED',
      'CLANG_UPDATED',
      'CACHE_DELETED',
      'REX_FORM_SAVED',
      'REX_YFORM_SAVED',
      'YFORM_DATA_ADDED',
      'YFORM_DATA_DELETED',
      'YFORM_DATA_UPDATED',
    ];

    foreach ($extensionPoints as $extensionPoint) {

      rex_extension::register($extensionPoint, function ($ep) {
        // Send webhook
        switch ($ep->getName()) {
          case 'CLANG_ADDED':
          case 'CLANG_DELETED':
          case 'CLANG_UPDATED':
          case 'CACHE_DELETED':
          case 'REX_FORM_SAVED':
            Cache::invalidate();
            break;
          default:
            Cache::invalidate('query');
            break;
        }
        $params = $ep->getParams();
        $params['subject'] = $ep->getSubject();
        $params['extension_point'] = $ep->getName();
        Webhook::send($params);
      }, rex_extension::LATE);
    }
  }
}

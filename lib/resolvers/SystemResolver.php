<?php

namespace FriendsOfRedaxo\RexQL\Resolver;

use rex;
use rex_addon;
use rex_clang;
use rex_config;

class SystemResolver extends ResolverBase
{
  public function getData(): array|null
  {
    $addonStructureIsAvailable = rex_addon::get('structure')->isAvailable();
    $addonStructureConfig = rex_config::get('structure');
    $yrewrite_domain_data = null;
    $allLanguages = [];

    $yrewrite = rex_addon::get('yrewrite');
    if ($yrewrite->isAvailable() && $yrewrite->isInstalled() && isset($this->args['host'])) {
      \rex_yrewrite::init();
      $url = parse_url($this->args['host']);
      $domainName = $url['host'] ?? '';
      $domainName .= isset($url['port']) ? ':' . $url['port'] : '';
      $this->log('rexQL: Resolver: yrewrite is available, fetching domain data for name: ' . $domainName);
      $yrewrite_domain_data = \rex_yrewrite::getDomainByName($domainName);
      $allLanguages = $yrewrite_domain_data->getClangs();
    }

    $langs = [];
    if (!count($allLanguages)) {
      $allLanguages = rex_clang::getAllIds();
    }
    foreach ($allLanguages as $clang) {
      $rex_clang = rex_clang::get($clang);
      $langs[] = [
        'id' => $rex_clang->getId(),
        'name' => $rex_clang->getName(),
        'code' => $rex_clang->getCode(),
      ];
    }
    $lang = array_filter($langs, function ($lang) {
      return $lang['id'] === rex_clang::getCurrentId();
    });
    $data = [
      'server' =>  rex::getServer(),
      'serverName' => rex::getServerName(),
      'errorEmail' => rex::getErrorEmail(),
      'version' => rex::getVersion(),
      'startArticleId' => $addonStructureIsAvailable ? $addonStructureConfig['start_article_id'] : 1,
      'notFoundArticleId' => $addonStructureIsAvailable ? $addonStructureConfig['notfound_article_id'] : 1,
      'defaultTemplateId' => $addonStructureIsAvailable ? rex_config::get('structure/content', 'default_template_id', 1) : 1,
      'domainHost' => $yrewrite_domain_data ? $yrewrite_domain_data->getHost() : '',
      'domainUrl' => $yrewrite_domain_data ? $yrewrite_domain_data->getUrl() : '',
      'domainStartId' => $yrewrite_domain_data ? $yrewrite_domain_data->getStartId() : 1,
      'domainNotfoundId' => $yrewrite_domain_data ? $yrewrite_domain_data->getNotfoundId() : 1,
      'domainLanguages' => $langs,
      'domainDefaultLanguage' => $lang[0],
      'startClangHidden' => $yrewrite_domain_data ? $yrewrite_domain_data->isStartClangHidden() : false,

    ];
    return $data;
  }
}

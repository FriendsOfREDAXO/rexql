<?php

/**
 * GraphQL Playground Seite
 */

$addon = rex_addon::get('rexql');

if (!$addon->getConfig('endpoint_enabled', false)) {
  echo rex_view::warning(rex_i18n::msg('rexql_endpoint_not_enabled'));
  return;
}

$endpointUrl = FriendsOfRedaxo\RexQL\Utility::getEndpointUrl();

$content = '';
$example = '
# Beispiel-Queries:
{
  article(id:1) {
    id
    name
    slug
    slices {
      id
      module {
        id
      }
    }
  }

  config (namespace:"developer", key:"actions") {
    key
  }

  languages {
    id
    name
  }

  media(id:1) {
    filename
  }

  mediaCategory(id: 1) {
    name
  }

  module(id:1) {
    name
  }

  navigation {
    id
    name
  }

  routes {
    name
  }

  template (id:1) {
    name
    articles {
      name
    }
  }

  wildcard(wildcard:"test", clangId: 1) {
    clangId
    wildcard
  }
}';

// GraphQL Playground Interface
$playgroundHtml = '
<div class="rexql-playground" data-endpoint-url="' . $endpointUrl . '">
  <div class="rex-ql-playground-cols">
    <div class="rexql-query-container">
      <div>
          <h4>GraphQL Query</h4>

          <div class="rexql-editor-wrap">
            <div id="graphql-editor"></div>
            <textarea id="graphql-query" class="hidden form-control" rows="15" placeholder="Geben Sie hier Ihre GraphQL Query ein..." autocapitalize="off" autocorrect="off" spellcheck="false">' . $example . '</textarea>

            <div class="form-group rexql-playground-actions">
              <input type="text" id="api-key-input" class="form-control" placeholder="Ihr API-Schlüssel">
              <div class="btn-group">
                <button id="execute-query" class="btn btn-primary">Query ausführen</button>
                <button id="clear-result" class="btn btn-default">Ergebnis löschen</button>
              </div>
            </div>
          </div>
      
      </div>
    </div>
    <div class="rexql-result-container">
      <h4>Ergebnis</h4>
      <pre class="result" id="query-result">
  Führen Sie eine Query aus, um Ergebnisse zu sehen...
      </pre>
    </div>
  </div>
  <div class="rexql-schema-container">
    <h4><span>Schema</span> <span class="sdl badge">SDL</span></h4>
    <div id="graphql-schema"></div>
  </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'GraphQL Playground');
$fragment->setVar('body', $playgroundHtml, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;

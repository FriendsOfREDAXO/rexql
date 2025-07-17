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

// GraphQL Playground Interface
$playgroundHtml = '
<div class="rexql-playground" data-endpoint-url="' . $endpointUrl . '">
    <div class="row">
        <div class="col-md-6">
            <h4>GraphQL Query</h4>
            <textarea id="graphql-query" class="form-control" rows="15" placeholder="Geben Sie hier Ihre GraphQL Query ein..." autocapitalize="off" autocorrect="off" spellcheck="false">
# Beispiel-Queries:

# Core-Tabellen abfragen (Listen):
{
  articles(limit: 5) {
    id
    name
    createdate
  }
  
}

# YForm-Tabellen abfragen (Listen):
# {
#   rexYfNewsList(limit: 10) {
#     id
#     name
#     topic
#     description
#   }
# }

# Einzelne Datensätze (benötigen ID):
# {
#   rexArticle(id: 1) {
#     id
#     name
#   }
#   
#   rexYfNews(id: 1) {
#     id
#     name
#     topic
#   }
# }
            </textarea>
            <div class="form-group rexql-form-group-spacing">
                <label for="api-key-input">API-Schlüssel:</label>
                <input type="text" id="api-key-input" class="form-control" placeholder="Ihr API-Schlüssel">
            </div>
            
            <button id="execute-query" class="btn btn-primary">Query ausführen</button>
            <button id="clear-result" class="btn btn-default">Ergebnis löschen</button>
            <button id="introspect" class="btn btn-info">Schema abfragen</button>
        </div>
        
        <div class="col-md-6">
            <h4>Ergebnis</h4>
            <pre class="result" id="query-result">
Führen Sie eine Query aus, um Ergebnisse zu sehen...
            </pre>
        </div>
    </div>
</div>
';

$fragment = new rex_fragment();
$fragment->setVar('title', 'GraphQL Playground');
$fragment->setVar('body', $playgroundHtml, false);
$content .= $fragment->parse('core/page/section.php');

echo $content;

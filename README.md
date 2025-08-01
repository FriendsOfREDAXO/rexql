# rexQL - GraphQL API für REDAXO CMS

**Version 1.0** - Eine vollständige, erweiterbare GraphQL-API für REDAXO CMS mit SDL-basierter Schema-Definition.

## 🎯 Hauptfeatures

- 🧩 **SDL-basierte Schema-Erweiterung** - Definiere GraphQL-Schemas mit SDL-Dateien
- 🔄 **Unbegrenzte Query-Verschachtelung** - 1:n und n:1 Beziehungen automatisch aufgelöst
- 📊 **Automatische YForm-Integration** - Alle YForm-Tabellen werden automatisch als GraphQL-Typen verfügbar
- 🔗 **Intelligente Slug-Generierung** - Automatische URL-Generierung über das URL-Addon
- 🌐 **Public Headless CMS** - Direkte API-Nutzung ohne Backend-Authentifizierung
- 🔒 **Granulare Berechtigungen** - Typ-basierte Zugriffskontrolle mit automatischer Schema-Generierung
- 📡 **Webhooks** - Cache-Invalidierung und externe Benachrichtigungen
- ⚡ **Intelligentes Caching** - Schema- und Query-Caching für optimale Performance
- 🎯 **Erweiterte GraphQL Playground** - CodeMirror-Integration mit Autovervollständigung
- 📈 **Detaillierte Statistiken** - Query-Logging und Performance-Monitoring
- 🔒 **CORS & Domain-Beschränkungen** - Sichere API-Nutzung in Frontend-Anwendungen

## 🚀 Installation & Schnellstart

### 1. Installation

Installieren Sie das Addon über den REDAXO Installer oder manuell:

#### Manuelle Installation

Lade das Addon von GitHub herunter und entpacke es in den `src/addons/rexql` Ordner deines REDAXO-Projekts.

#### Abhängigkeiten installieren

Installiere die Abhängigkeiten im `src/addons/rexql` Ordner mit Composer:

```bash
cd src/addons/rexql
composer install
```

Aktiviere das Addon im REDAXO Backend.

### 2. Minimale Konfiguration

1. **rexQL → Konfiguration**:
   - ✅ API-Endpoint aktivieren
   - ✅ CORS-Origins für deine Domain(s) eintragen

2. **rexQL → Berechtigungen**:
   - Erstelle einen API-Key ODER deaktiviere die Authentifizierung für öffentliche APIs
   - Wähle Berechtigung für gewünschte Typen (z.B. `article`, `media`)

3. **Testen**:
   - Öffne **rexQL → Playground**
   - Führe eine Test-Query aus:

```graphql
{
  articles(limit: 3) {
    id
    name
    createdate
  }
}
```

### Backend-Tools (nur im REDAXO Backend verfügbar)

- **GraphQL Playground** - Interaktives Query-Tool mit Schema-Explorer
- **Berechtigungen verwalten** - API-Keys und Typ-Berechtigungen konfigurieren
- **Konfiguration** - CORS, Caching und Sicherheitseinstellungen

## 📡 API-Endpoints

### Haupt-Endpoint

```
POST /index.php?rex-api-call=rexql
```

### Kurz-URL (mit .htaccess/.nginx Regel)

```
POST /api/rexql/
```

**Beispiel .htaccess-Regel:**

```apache
RewriteRule ^api/rexql/?$ index.php?rex-api-call=rexql [L,QSA]
```

**Beispiel Nginx-Regel:**

```nginx
location /api/rexql {
    rewrite ^/api/rexql(.*) /index.php?rex-api-call=rexql$1 last;
}
```

## 🧩 Schema-Erweiterung mit SDL

Die wichtigste Neuerung in v1.0 ist die SDL-basierte Schema-Definition. Erweitere die GraphQL-API über REDAXO Extension Points:

### Basis-SDL Schema

Das Core-Schema in `data/schema.graphql` definiert alle REDAXO Core-Typen:

```graphql
type Query {
  # Artikel-Queries
  article(id: ID!, clangId: Int, ctypeId: Int): article
  articles(
    clangId: Int
    categoryId: Int
    status: Boolean
    limit: Int
  ): [article]

  # Media-Queries
  media(id: ID!): media
  medias(categoryId: Int): [media]

  # System-Informationen
  system(host: String): system!

  # Navigation
  navigation(
    categoryId: Int
    clangId: Int
    depth: Int
    nested: Boolean
  ): [navigationItem]
}
```

### Custom Schema-Erweiterung

Erweitere das Schema über den `REXQL_EXTEND` Extension Point.
Der ExtensionPoint sollte einen Array zurückgeben, der die `sdl` und `rootResolvers` enthält.
Desweitern kann man mit `$ep->getParams()` auf die Parameter des ExtensionPoints zugreifen, welcher den aktuellen Kontext sowie das Addon selbst enthält.

```php
<?php
// In deinem Addon's boot.php

rex_extension::register('REXQL_EXTEND', function (rex_extension_point $ep) {
    $extensions = $ep->getSubject();

    // Erweitere das SDL-Schema
    $extensions['sdl'] .= '
        extend type Query {
            customData(filter: String): [CustomType]
        }

        type CustomType {
            id: ID!
            title: String
            content: String
            publishedAt: String
        }
    ';

    // Registriere Custom Resolver
    $extensions['rootResolvers']['query']['customData'] = function($root, $args) {
        // Deine Custom Logic hier
        return [
            ['id' => 1, 'title' => 'Test', 'content' => 'Beispiel'],
        ];
    };

    return $extensions;
});
```

### Custom Resolver mit ResolverBase

Für komplexere Anforderungen erweitere die `ResolverBase` Klasse:

```php
<?php

use FriendsOfRedaxo\RexQL\Resolver\ResolverBase;

class CustomResolver extends ResolverBase
{
    public function getData(): array|null
    {
        $this->table = 'custom_table';

        // Automatische Relation-Definition
        $this->relations = [
            'rex_media' => [
                'alias' => 'image',
                'type' => 'hasOne',
                'localKey' => 'image_id',
                'foreignKey' => 'id',
            ]
        ];

        // Field Resolver für berechnete Felder
        $this->fieldResolvers = [
            $this->table => [
                'fullUrl' => function($row): string {
                    return rex::getServer() . $row['custom_table_path'];
                }
            ]
        ];

        $results = $this->query();
        return $this->typeName === 'customList' ? $results : $results[0] ?? null;
    }
}
```

```php
// Schema-Registrierung
rex_extension::register('REXQL_EXTEND', function (rex_extension_point $ep) {
    $extensions = $ep->getSubject();

    $extensions['sdl'] .= '
        extend type Query {
            customItem(id: ID!): CustomItem
            customList(limit: Int): [CustomItem]
        }

        type CustomItem {
            id: ID!
            name: String
            fullUrl: String
            image: media
        }
    ';

    $resolver = new CustomResolver();
    $extensions['rootResolvers']['query']['customItem'] = $resolver->resolve();
    $extensions['rootResolvers']['query']['customList'] = $resolver->resolve();

    return $extensions;
});
```

Selbstverständlich kann man auch komplexere Logik in den Resolvern implementieren, wie z.B. Datenbankabfragen, externe API-Calls oder komplexe Berechnungen.
Wenn man komplett eigene Resolver-Klassen erstellen möchte, dann hilft ev. folgendes:

- das Interface `FriendsOfRedaxo\RexQL\Resolver\Interface`, welches die Methoden `getData()` und `getTypeName()` definiert.
- die Klasse `FriendsOfRedaxo\RexQL\Resolver\ResolverBase`, die bereits viele nützliche Methoden und Eigenschaften bereitstellt, wie z.B. `query()`, `checkPermissions()`, `log()`, `error()` und `getFields()`.
- die Bibliothek `webonyx/graphql-php`, die `rexql` integriert. Darin spezielle die Klasse `GraphQL\Type\Definition\ResolveInfo`, die Informationen über die GraphQL-Query enthält, wie z.B. die angeforderten Felder und Argumente.
- [GraphQL.org](https://graphql.org/learn/) für allgemeine GraphQL-Konzepte und Best Practices.

## 📊 Automatische YForm-Integration

Alle YForm-Tabellen werden automatisch als GraphQL-Typen verfügbar gemacht:

### Automatische Schema-Generierung

```graphql
# YForm-Tabelle "rex_news" wird automatisch zu:

extend type Query {
  rexNewsDataset(id: ID!, slugNamespace: String): rexNews
  rexNewsCollection(
    status: Boolean
    where: String
    orderBy: String
    offset: Int
    limit: Int
    slugNamespace: String
  ): [rexNews]
}

type rexNews {
  id: ID
  slug: String # Automatisch generiert via URL-Addon
  title: String
  content: String
  publishDate: String
  status: String
  author: rexUser # YForm-Relationen werden automatisch aufgelöst
}
```

### YForm-Query Beispiele

```graphql
# Einzelnen Datensatz abrufen
{
  rexNewsDataset(id: 1) {
    id
    title
    content
    slug
    author {
      name
      email
    }
  }
}

# Collection mit Filterung
{
  rexNewsCollection(
    status: true
    where: "publish_date > '2024-01-01'"
    orderBy: "publish_date DESC"
    limit: 10
  ) {
    id
    title
    slug
    publishDate
  }
}
```

### Slug-Generierung

Wenn das URL-Addon installiert ist, generiert rexQL automatisch Slugs:

```graphql
{
  rexNewsDataset(id: 1, slugNamespace: "news") {
    id
    title
    slug # Automatisch: "/news/mein-artikel-titel"
  }
}
```

## 🔄 Unbegrenzte Query-Verschachtelung

Das v1.0 Resolver-System löst automatisch 1:n und n:1 Beziehungen auf:

```graphql
{
  article(id: 1) {
    id
    name
    template {
      id
      name
    }
    slices {
      id
      value1
      module {
        id
        name
      }
    }
  }
}
```

### Automatische Relation-Definition

```php
// In deinem Custom Resolver
$this->relations = [
    'rex_article_slice' => [
        'alias' => 'slices',
        'type' => 'hasMany',
        'localKey' => 'id',
        'foreignKey' => 'article_id',
        'relations' => [
            'rex_module' => [
                'alias' => 'module',
                'type' => 'hasOne',
                'localKey' => 'module_id',
                'foreignKey' => 'id',
            ]
        ]
    ]
];
```

## 📡 Webhooks

Webhooks ermöglichen Cache-Invalidierung und externe Benachrichtigungen:

### Webhook-Konfiguration

1. **rexQL → Webhooks**
2. Webhook-URL hinzufügen: `https://ihre-app.de/api/webhook`
3. Events auswählen: `article_update`, `media_update`, etc.

### Webhook-Payload

```json
{
  "event": "article_update",
  "timestamp": "2024-08-01T10:00:00Z",
  "data": {
    "id": 1,
    "table": "rex_article",
    "action": "update"
  }
}
```

### Webhook-Handler Beispiel

```javascript
// In deiner Frontend-App
app.post('/api/webhook', (req, res) => {
  const { event, data } = req.body

  if (event === 'article_update') {
    // Cache invalidieren
    cache.del(`article:${data.id}`)

    // Static Site Regeneration triggern
    regeneratePage(`/articles/${data.id}`)
  }

  res.json({ success: true })
})
```

## 🔒 Berechtigungen & Sicherheit

### Typ-basierte Berechtigungen

Berechtigungen werden automatisch für alle Schema-Typen generiert:

**Verfügbare Berechtigungen:**

- `article` - Artikel-Zugriff
- `media` - Medien-Zugriff
- `template` - Template-Zugriff
- `rexNews` - YForm-Tabelle (automatisch generiert)
- `system` - System-Informationen

### API-Key Konfiguration

1. **rexQL → Berechtigungen → Hinzufügen**
2. **Domain-Beschränkungen:** `ihre-domain.de,localhost`
3. **Berechtigungen auswählen:** `article`, `media`, `rexNews`
4. **API-Key kopieren:** `rexql_abc123...`

### Sichere Frontend-Integration

```javascript
// Domain-beschränkter API-Key (Frontend-sicher)
const client = new GraphQLClient('/api/rexql/', {
  headers: {
    'X-API-KEY': 'rexql_abc123...',
    'Content-Type': 'application/json'
  }
})

const { data } = await client.request(
  `
  query GetArticles($limit: Int) {
    articles(limit: $limit) {
      id
      name
      slug
    }
  }
`,
  { limit: 10 }
)
```

### Proxy-Modus (für sensible Daten)

```javascript
// Für Backend-authentifizierte Requests
const proxyClient = new GraphQLClient('/index.php?rex-api-call=proxy', {
  headers: {
    Authorization: 'Bearer ' + sessionToken,
    'X-Public-Key': 'rexql_pub_xyz789...'
  }
})
```

## 💾 Intelligentes Caching

### Schema-Caching

Das GraphQL-Schema wird automatisch gecacht und nur bei Änderungen neu generiert.

### Query-Caching

Wiederholte Queries werden gecacht (Standard: 5 Minuten):

```bash
# Cache umgehen für Entwicklung
curl -X POST "/api/rexql/?noCache=1" \
  -H "X-API-KEY: rexql_abc123..." \
  -d '{"query": "{ articles { id name } }"}'
```

### Cache-Management

```php
// Programmatische Cache-Kontrolle
use FriendsOfRedaxo\RexQL\Cache;

// Kompletten Cache löschen
Cache::invalidateAll();

// Nur Schema-Cache löschen
Cache::invalidateSchema();

// Nur Query-Cache löschen
Cache::invalidateQueries();
```

## 🎯 GraphQL Playground

Der erweiterte Playground bietet:

- **Schema-Explorer:** Vollständige Schema-Dokumentation
- **CodeMirror-Editor:** Syntax-Highlighting und Autovervollständigung
- **Query-Validation:** Echtzeit-Fehlerprüfung
- **Variable-Support:** JSON-Variablen für Queries

### Playground-Nutzung

1. **rexQL → Playground** öffnen
2. API-Key eingeben
3. Query schreiben mit Autovervollständigung:

```graphql
query GetArticleWithContent($id: ID!) {
  article(id: $id) {
    id
    name
    slices {
      id
      value1
      module {
        name
      }
    }
  }
}
```

4. **Variablen** definieren:

```json
{
  "id": "1"
}
```

## 📋 Query-Beispiele

### Core-Queries

```graphql
# Artikel mit verschachtelten Beziehungen
{
  articles(limit: 5, status: true) {
    id
    name
    slug
    template {
      name
    }
    slices {
      value1
      value2
      module {
        name
      }
    }
  }
}

# Medien mit Kategorien
{
  medias(categoryId: 1) {
    id
    filename
    title
    category {
      name
      parentId
    }
  }
}

# Navigation-Struktur
{
  navigation(categoryId: 1, depth: 2, nested: true) {
    id
    name
    slug
    children {
      id
      name
      slug
    }
  }
}

# System-Informationen
{
  system {
    version
    serverName
    startArticleId
    domainLanguages {
      id
      name
      code
    }
  }
}
```

### YForm-Queries

```graphql
# News-Artikel mit Autor und Kategorien
{
  rexNewsCollection(status: true, orderBy: "publish_date DESC", limit: 10) {
    id
    title
    slug
    publishDate
    author {
      name
      email
    }
    categories {
      name
    }
  }
}

# Event-Details mit Location
{
  rexEventDataset(id: 1) {
    id
    title
    description
    startDate
    endDate
    location {
      name
      address
      city
    }
  }
}
```

## 📈 Statistiken & Monitoring

### Query-Statistiken

**rexQL → Statistiken** zeigt:

- Häufigste Queries
- Performance-Metriken
- API-Key Nutzung
- Fehler-Logs

### Performance-Monitoring

```graphql
# Debug-Informationen in Antworten
{
  "data": { ... },
  "extensions": {
    "executionTime": "25.57ms",
    "memoryUsage": "1.29 KiB",
    "cacheStatus": "hit"
  }
}
```

## 🛠️ Entwicklung & Extension Points

### Extension Points

- **`REXQL_EXTEND`** - Haupt-Extension Point für Schema und Resolver
- **`REXQL_EXTEND_FIELD_RESOLVERS`** - Custom Field Resolver
- **`REXQL_EXTEND_TYPE_RESOLVERS`** - Custom Type Resolver

### ResolverBase Methoden

Die `ResolverBase` Klasse bietet hilfreiche Methoden:

```php
// Automatische Query-Generierung
$results = $this->query();

// Berechtigungsprüfung
$this->checkPermissions($typeName);

// Logging
$this->log('Debug-Nachricht');

// Fehler-Behandlung
$this->error('Fehlermeldung');

// Field-Selection aus GraphQL-Query
$fields = $this->getFields($table, $selection);
```

## 🔧 Konfiguration

### Backend-Einstellungen

**rexQL → Konfiguration:**

- **API-Endpoint aktivieren** - Ein/Aus
- **Authentifizierung erforderlich** - Für geschützte APIs
- **CORS-Origins** - `domain1.de,domain2.de,localhost:3000`
- **Rate Limiting** - Anfragen pro Minute
- **Query-Tiefe-Limit** - Schutz vor DoS-Angriffen
- **Debug-Modus** - Detaillierte Logs und Timing
- **Cache aktivieren** - Schema- und Query-Caching

### .htaccess Kurz-URLs

```apache
# Kurze API-URLs aktivieren
RewriteRule ^api/rexql/?$ index.php?rex-api-call=rexql [L,QSA]
RewriteRule ^api/rexql/proxy/?$ index.php?rex-api-call=proxy [L,QSA]
RewriteRule ^api/rexql/auth/?$ index.php?rex-api-call=auth [L,QSA]
```

## � Migration & Breaking Changes

Da v1.0 ein kompletter Rewrite ist, sind keine Migrations-Pfade verfügbar. Neu aufsetzen empfohlen.

## 📚 Weiterführende Ressourcen

- **GraphQL Spezifikation:** https://graphql.org/
- **REDAXO Dokumentation:** https://redaxo.org/doku/main
- **YForm Addon:** https://github.com/yakamara/redaxo_yform

## 🤝 Support & Community

- **GitHub Issues:** https://github.com/FriendsOfREDAXO/rexql
- **REDAXO Slack:** #addon-rexql
- **REDAXO Community:** https://redaxo.org/community/

## 📄 Lizenz

MIT License - siehe [LICENSE](LICENSE) Datei

---

**Entwickelt von [Yves Torres](https://github.com/ynamite) für die REDAXO Community**

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

## 🗓️ Geplante Features

- 🔄 **Mutationen** - Unterstützung für GraphQL-Mutationen zur Datenmanipulation

## 📋 Inhaltsverzeichnis

1. [Installation & Schnellstart](#installation--schnellstart)
2. [API-Endpoints](#api-endpoints)
3. [Schema-Erweiterung mit SDL](#schema-erweiterung-mit-sdl)
4. [Automatische YForm-Integration](#automatische-yform-integration)
5. [Unbegrenzte Query-Verschachtelung](#unbegrenze-query-verschachtelung)
6. [Webhooks](#webhooks)
7. [Berechtigungen & Sicherheit](#berechtigungen--sicherheit)
8. [Intelligentes Caching](#intelligentes-caching)
9. [GraphQL Playground](#graphql-playground)
10. [Query-Beispiele](#query-beispiele)
11. [Statistiken & Monitoring](#statistiken--monitoring)
12. [Entwicklung & Extension Points](#entwicklung--extension-points)
13. [Konfiguration](#konfiguration)
14. [Migration & Breaking Changes](#migration--breaking-changes)
15. [Weiterführende Ressourcen](#weiterführende-ressourcen)
16. [Support & Community](#support--community)
17. [Lizenz](#lizenz)

## 🚀 Installation & Schnellstart <a id="installation--schnellstart"></a>

### 1. Installation

Installieren Sie das Addon über den REDAXO Installer oder manuell:

#### Manuelle Installation

1. Lade das Addon von GitHub herunter und entpacke es in den `src/addons/rexql` Ordner deines REDAXO-Projekts.
2. Installiere die Abhängigkeiten im `src/addons/rexql` Ordner über Composer:

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
     - Falls API-Keys verwendet werden, wähle Berechtigung für gewünschte Typen (z.B. `article`, `media`)

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

## 📡 API-Endpoints <a id="api-endpoints"></a>

### Haupt-Endpoint

```
POST /index.php?rex-api-call=rexql
```

#### Kurz-URL (mit .htaccess/.nginx Regel)

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

## 🧩 Schema-Erweiterung mit SDL <a id="schema-erweiterung-mit-sdl"></a>

Die wichtigste Neuerung in v1.0 ist die SDL-basierte Schema-Definition. Erweitere die GraphQL-API über REDAXO Extension Points:

### Basis-SDL Schema

Das Core-Schema in `data/schema.graphql` definiert alle REDAXO Core-Typen.
Das gesamte Schema kann im Playground oder in `data/schema.graphql` eingesehen werden.

Das Core-Schema beinhaltet folgende Typen:

- `article`: Artikel-Typ mit Beziehungen zu `language`, `template` und `slices`.
- `config`
- `language`
- `media`: Medientyp mit Beziehungen zu `mediaCategory`.
- `mediaCategory`: Mediacategory-Typ mit Beziehungen zu `children` (Unterkategorien).
- `module`: Modultyp mit Beziehungen zu `slices`.
- `navigationItem`
- `route`
- `slice`: Slice-Typ mit Beziehungen zu `module`, `article` und `language`.
- `system`: System-Informationen wie REDAXO-Version und Server-Name.
- `template`: Template-Typ mit Beziehungen zu `articles`.
- `wildcard`: Wildcard-Typ für Sprog-Übersetzungen.
- sowie Yform-Tabellen wie `rex_news`, `rex_event`, etc.

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
  # usw...
}
```

### Custom Schema-Erweiterung

Erweitere das Schema über den `REXQL_EXTEND` Extension Point.
Der ExtensionPoint sollte einen Array zurückgeben, der die `sdl` und `rootResolvers` enthält.
**Achtung**: `SDL` erweitern und nicht überschreiben!
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
                'relations' => [
                    'rex_media_category' => [
                        'alias' => 'category',
                        'type' => 'hasOne',
                        'localKey' => 'category_id',
                        'foreignKey' => 'id',
                    ]
                ]
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

## 📊 Automatische YForm-Integration <a id="automatische-yform-integration"></a>

Alle YForm-Tabellen werden automatisch als GraphQL-Typen verfügbar gemacht (Berechtigungen beachten!).
Damit kannst du YForm-Daten direkt über GraphQL abfragen, ohne manuelle Schema-Definitionen.
Ist eine YForm-Tabelle mit einem Profil des REDAXO URL-Addons verknüpft, kann man auch die von URL generierten Slugs für einen Datensatz abfragen. Dabei versucht rexQL, automatisch den korrekten Slug-Namespace zu verwenden, der im YForm-Profil definiert ist. Um sicher zu gehen, dass der korrekte Namespace verwendet wird, kann man den `slugNamespace`-Parameter in der Query angeben.
**Gut zu wissen:** die `routes`-Query gibt alle Routen aus, auch für eine YForm-Tabelle, die mit dem URL-Addon verknüpft ist.

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

Wenn das REDAXO URL-Addon installiert ist, kann der von URL generierte Slug abgefragt werden, wenn die Tabelle mit einem Profil des URL-Addons verknüpft ist:

```graphql
{
  rexNewsDataset(id: 1, slugNamespace: "news") {
    id
    title
    slug # Automatisch: "/news/mein-artikel-titel"
  }
}
```

## 🔄 Unbegrenzte Query-Verschachtelung <a id="unbegrenzte-query-verschachtelung"></a>

Das v1.0 Resolver-System löst automatisch 1:n und n:1 Beziehungen ohne zusätzliche SQL-Queries auf:

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

## 📡 Webhooks <a id="webhooks"></a>

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

## 🔒 Berechtigungen & Sicherheit <a id="berechtigungen--sicherheit"></a>

### Typ-basierte Berechtigungen

Berechtigungen werden automatisch für alle Schema-Typen generiert:

**Verfügbare Berechtigungen:**

- `Article` - Zugriff auf `rex_article`
- `Config` - Zugriff auf `rex_config`
- `Language` - Zugriff auf `rex_language`
- `Media` - Zugriff auf `rex_media`
- `MediaCategory` - Zugriff auf `rex_media_category`
- `Module` - Zugriff auf `rex_module`
- `NavigationItem` - Zugriff auf `rex_article` um verschachtelte Navigationen zu ermöglichen
- `Route` - Zugriff auf `rex_article`, `yrewrite` und `url` um alle möglichen Routen auszulesen
- `Slice` - Zugriff auf `rex_article_slice` um Slices zu laden
- `System` - Zugriff auf System-Informationen von REDAXO
- `Template` - Zugriff auf `rex_template`
- `Wildcard` - Zugriff auf `sprog` um Wildcards zu laden

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

## 💾 Intelligentes Caching <a id="intelligentes-caching"></a>

### Schema-Caching

Das GraphQL-Schema wird automatisch gecacht und nur bei Änderungen neu generiert.

### Query-Caching

Wiederholte Queries werden gecacht (Standard: 5 Minuten; konfigurierbar in den Backend-Einstellungen):

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

## 🎯 GraphQL Playground <a id="graphql-playground"></a>

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

## 📋 Query-Beispiele <a id="query-beispiele"></a>

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

## 📈 Statistiken & Monitoring <a id="statistiken--monitoring"></a>

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

## 🛠️ Entwicklung & Extension Points <a id="entwicklung--extension-points"></a>

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
$fieldSelection = $this->info->getFieldSelection(5); // 5 ist die maximale Tiefe
$fields = $this->getFields($table /* oder */ $typeName, $fieldSelection);
```

## 🔧 Konfiguration <a id="konfiguration"></a>

### Backend-Einstellungen

**rexQL → Konfiguration:**

- **API-Endpoint aktivieren** - Ein/Aus
- **Authentifizierung erforderlich** - Für geschützte APIs
- **CORS-Origins** - `domain1.de,domain2.de,localhost:3000`
- **Rate Limiting** - Anfragen pro Minute
- **Query-Tiefe-Limit** - Schutz vor DoS-Angriffen
- **Debug-Modus** - Detaillierte Logs und Timing
- **Cache aktivieren** - Schema- und Query-Caching
- **Cache-TTL** - Standard: 5 Minuten, anpassbar

### .htaccess Kurz-URLs

```apache
# Kurze API-URLs aktivieren
RewriteRule ^api/rexql/?$ index.php?rex-api-call=rexql [L,QSA]
RewriteRule ^api/rexql/proxy/?$ index.php?rex-api-call=proxy [L,QSA]
RewriteRule ^api/rexql/auth/?$ index.php?rex-api-call=auth [L,QSA]
```

## 📦 Migration & Breaking Changes <a id="migration--breaking-changes"></a>

Da v1.0 ein kompletter Rewrite ist, sind keine Migrations-Pfade verfügbar. Neu aufsetzen empfohlen.

## 📚 Weiterführende Ressourcen <a id="weiterfuehrende-ressourcen"></a>

- **GraphQL Spezifikation:** https://graphql.org/
- **REDAXO Dokumentation:** https://redaxo.org/doku/main
- **YForm Addon:** https://github.com/yakamara/redaxo_yform

## 🤝 Support & Community <a id="support--community"></a>

- **GitHub Issues:** https://github.com/FriendsOfREDAXO/rexql
- **REDAXO Slack:** #addon-rexql
- **REDAXO Community:** https://redaxo.org/community/

## 📄 Lizenz <a id="lizenz"></a>

MIT License - siehe [LICENSE](LICENSE) Datei

---

**Entwickelt von [Yves Torres](https://github.com/ynamite) für die REDAXO Community**

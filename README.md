# rexQL - GraphQL API für REDAXO CMS

rexQL erweitert REDAXO CMS um eine vollständige GraphQL-API, die speziell für **Public Headless CMS** Nutzung optimiert ist.

## 🎯 Hauptfeatures

- 🌐 **Public Headless CMS** - Direkte API-Nutzung ohne Benutzer-Authentifizierung
- 🔒 **Domain/IP-Beschränkungen** - API-Keys beschränkt auf spezifische Domains/IPs
- ⚡ **CORS-Support** - Vollständige CORS-Konfiguration für Frontend-Apps
- 🛠️ **Dev-Mode** - Offener Zugriff in Development-Umgebungen
- 🔑 **Optionale Authentifizierung** - Für sensible Daten über Proxy-Modus
- 📊 **YForm-Integration** - Automatische API-Generierung für YForm-Tabellen
- 🚀 **Rate Limiting** und **Query-Tiefe-Begrenzung** für Sicherheit
- 🌍 **Mehrsprachigkeit** - Native Unterstützung für REDAXO Sprachen
- 🔗 **URL-Addon Integration** - URLs für Datensätze abfragen
- 🌐 **YRewrite-Integration** - Domain-Management über GraphQL
- 📈 **Query-Logging** und **Statistiken**
- 🎯 **GraphQL Playground** im Backend
- 💾 **Intelligentes Caching** für bessere Performance

## 🚀 Schnellstart für Public Headless CMS

### 1. Installation & Setup

```bash
cd src/addons/rexql
composer install
```

Aktivieren Sie das Addon im REDAXO Backend und konfigurieren Sie:

1. **rexQL → Konfiguration**:
   - ✅ API-Endpoint aktivieren
   - ✅ CORS-Origins für Ihre Frontend-Domain(s) eintragen
   - ❌ "Authentifizierung erforderlich" deaktivieren (für Public CMS)

2. **rexQL → Berechtigungen**:
   - Erstellen Sie einen API-Key mit Domain-Beschränkung
   - Wählen Sie die Tabellen aus, die öffentlich verfügbar sein sollen

### 2. Frontend Integration (React Beispiel)

```javascript
import { RexQLClient } from './rexql-client.js'

const cmsClient = new RexQLClient({
  baseUrl: 'https://cms.ihre-domain.de',
  apiKey: 'rexql_abc123...', // Domain-beschränkter API Key
  useProxy: false, // Direkter API-Zugriff
  enableAuth: false // Keine Benutzer-Authentifizierung
})

// React Hook für Artikel
function useArticles(limit = 10) {
  const [articles, setArticles] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    async function loadArticles() {
      try {
        const result = await cmsClient.getArticles(limit)
        setArticles(result.data.rexArticleList)
      } catch (err) {
        console.error('Failed to load articles:', err)
      } finally {
        setLoading(false)
      }
    }
    loadArticles()
  }, [limit])

  return { articles, loading }
}

function ArticleList() {
  const { articles, loading } = useArticles(10)

  if (loading) return <div>Loading...</div>

  return (
    <div>
      {articles.map((article) => (
        <article key={article.id}>
          <h2>{article.name}</h2>
          <time>{new Date(article.createdate).toLocaleDateString()}</time>
        </article>
      ))}
    </div>
  )
}
```

## 🔒 Sicherheitskonzept

### Public Headless CMS (Standard)

- **Domain-/IP-Beschränkungen**: API-Keys nur von erlaubten Domains/IPs nutzbar
- **CORS-Konfiguration**: Kontrolliert welche Frontend-Domains API zugreifen können
- **Tabellen-Whitelist**: Nur explizit freigegebene Tabellen verfügbar
- **Rate Limiting**: Schutz vor API-Missbrauch
- **Dev-Mode**: Automatisch offener Zugriff in Development-Umgebung

### Optionale Authentifizierung (für sensible Daten)

- **Proxy-Modus**: API-Zugriff über Custom Session Tokens
- **Public/Private Key Pairs**: Sichere Authentifizierung
- **Granulare Berechtigungen**: Pro-API-Key Zugriffskontrolle

## 📡 API Endpoints

### Hauptendpoint

```
POST /index.php?rex-api-call=rexql_graphql
```

### Authentifizierung (Public Headless CMS)

```bash
# API Key im Header
curl -H "X-API-KEY: rexql_abc123..." \
     -H "Content-Type: application/json" \
     -d '{"query": "{ rexArticleList { id name } }"}' \
     https://cms.ihre-domain.de/index.php?rex-api-call=rexql_graphql
```

> **Hinweis**: rexQL nutzt ein eigenständiges API-Key System und ist **nicht** in REDAXO's Backend-Benutzerverwaltung integriert. Dies ermöglicht sichere Public Headless CMS Nutzung ohne Backend-Zugriff.

## 📝 Query-Namenskonvention

rexQL verwendet eine konsistente Namenskonvention für alle GraphQL-Queries:

- **Einzelne Datensätze**: `rexTableName(id: Int!)` - z.B. `rexArticle(id: 1)`
- **Listen von Datensätzen**: `rexTableNameList(limit: Int, offset: Int)` - z.B. `rexArticleList(limit: 10)`

**Beispiele:**

- `rex_article` → `rexArticle` (einzeln) / `rexArticleList` (Liste)
- `rex_clang` → `rexClang` (einzeln) / `rexClangList` (Liste)
- `rex_yf_news` → `rexYfNews` (einzeln) / `rexYfNewsList` (Liste)

## 📋 Beispiel-Queries

```graphql
# Artikel abfragen (Liste)
{
  rexArticleList(limit: 5, clang_id: 1) {
    id
    name
    createdate
    status
  }
}

# Einzelnen Artikel abfragen
{
  rexArticle(id: 1) {
    id
    name
    createdate
  }
}

# Artikel mit Inhalten (Slices)
{
  rexArticle(id: 1) {
    id
    name
    slices: rexArticleSliceList(limit: 10) {
      id
      module_id
      value1
      value2
    }
  }
}

# YForm-Tabelle abfragen (Liste)
{
  rexYfNewsList(limit: 10) {
    id
    name
    topic
    description
    pub_date
  }
}

# Sprachen abfragen
{
  rexClangList {
    id
    name
    code
  }
}

# Medien abfragen
{
  rexMediaList(limit: 20) {
    id
    filename
    title
    category_id
  }
}
```

## Verfügbare Tabellen

### Core-Tabellen

- `rex_article` - Artikel
- `rex_article_slice` - Artikel-Inhalte
- `rex_clang` - Sprachen
- `rex_media` - Medien
- `rex_media_category` - Medien-Kategorien
- `rex_module` - Module
- `rex_template` - Templates
- `rex_user` - Benutzer

### Addon-Tabellen

- Alle YForm-Tabellen (konfigurierbar)
- URL-Addon Tabellen
- YRewrite-Addon Tabellen

## Sicherheit

- **API-Schlüssel-basierte Authentifizierung**
- **Granulare Berechtigungen** pro API-Schlüssel
- **Rate Limiting** (konfigurierbar)
- **Query-Tiefe-Begrenzung** gegen DoS-Angriffe
- **Audit-Logging** aller API-Zugriffe

### JavaScript Client Sicherheit

⚠️ **Wichtig**: API-Schlüssel sollten niemals direkt in JavaScript-Client-Anwendungen verwendet werden, da sie öffentlich sichtbar sind.

**Verfügbare Sicherheitsansätze:**

#### 1. Backend-Proxy (Empfohlen) ✅ Implementiert

```javascript
// rexQL Client mit Proxy-Unterstützung verwenden
const client = new RexQLClient({
  baseUrl: 'https://ihre-domain.de',
  publicKey: 'rexql_pub_abc123...', // Public Key (sicher exponierbar)
  sessionToken: 'your_session_token', // Session Token
  useProxy: true
})

// Query ausführen
const result = await client.query(`{
    rexArticleList(limit: 5) { id name }
}`)
```

**Funktionsweise:**

- **Public Key** kann sicher im Frontend verwendet werden
- **Private Key** bleibt auf dem Server und wird nie exponiert
- **Custom Session Token** für Benutzer-Authentifizierung in Ihrer Anwendung
- **Proxy-Endpoint**: `POST /index.php?rex-api-call=rexql_proxy`

**Setup:**

1. Erstellen Sie einen **Public/Private Key Pair** im Backend
2. Aktivieren Sie den **Proxy** in der Konfiguration
3. Implementieren Sie **Custom Session Token Management** in Ihrer Anwendung

#### 2. Domain-Restrictions ✅ Implementiert

API-Schlüssel können auf bestimmte Domains/IPs beschränkt werden:

**Konfigurierbare Restrictions:**

- **Allowed Domains**: Nur von bestimmten Domains zugänglich
- **Allowed IP Addresses**: IP-Adress-Beschränkungen
- **HTTPS-Only**: Nur über sichere Verbindungen

```javascript
// Domain-beschränkter API Key (weniger sicher als Proxy)
const client = new RexQLClient({
  baseUrl: 'https://ihre-domain.de',
  publicKey: 'rexql_restricted_xyz789...', // Domain-beschränkter Key
  useProxy: false
})
```

### API-Schlüssel Typen

#### Standard API-Schlüssel

```
rexql_abc123def456...
```

- Klassischer API-Schlüssel
- Für Server-zu-Server Kommunikation
- Sollte nie im Frontend exponiert werden

#### Public/Private Key Pair

```
Public Key:  rexql_pub_abc123...    (Frontend-sicher)
Private Key: rexql_priv_xyz789...   (Server-only)
```

- **Public Key** kann sicher im Frontend verwendet werden
- **Private Key** nur auf dem Server für Proxy-Calls
- Funktioniert nur mit aktiviertem Proxy

#### Domain-Restricted Key

```
rexql_abc123def456...
```

- Standard API-Schlüssel mit zusätzlichen Restrictions
- Domain/IP/HTTPS Einschränkungen
- Reduziert Risiko bei versehentlicher Exposition

## Konfiguration

### Allgemeine Einstellungen

- API-Endpoint aktivieren/deaktivieren
- Authentifizierung erforderlich (ja/nein)
- Rate Limit (Anfragen pro Minute)
- Maximale Query-Tiefe
- Introspection aktivieren
- Debug-Modus

### Berechtigungen

- `read:all` - Alle Tabellen lesen
- `read:core` - Nur Core-Tabellen lesen
- `read:yform` - Nur YForm-Tabellen lesen
- `read:media` - Nur Medien lesen
- `*` - Alle Berechtigungen

## GraphQL Playground

Das Addon enthält einen integrierten GraphQL Playground im Backend unter "rexQL" > "Playground". Hier können Sie:

- Queries interaktiv testen
- Schema-Dokumentation einsehen (Introspection)
- API-Schlüssel testen
- Die konsistente Namenskonvention kennenlernen

**Tipp:** Verwenden Sie die Schema-Dokumentation (Introspection), um alle verfügbaren Felder und Query-Namen zu erkunden.

## Client-Beispiele

### JavaScript (Empfohlen: Proxy-Modus)

```javascript
// RexQL Client verwenden (inkludiert in assets/rexql-client.js)
const client = new RexQLClient({
  baseUrl: 'https://ihre-domain.de',
  publicKey: 'rexql_pub_abc123...', // Public Key
  useProxy: true // Proxy verwenden
})

// 1. Login (generiert Session Token automatisch)
await client.login('testuser', 'testpass')

// 2. Artikel abfragen
const articles = await client.getArticles(5)
console.log(articles.data.rexArticleList)

// 3. Custom Query
const result = await client.query(`{
    rexArticleList(limit: 5) {
        id
        name
        createdate
    }
}`)

// 4. Logout
await client.logout()
```

### Kompletter Workflow für Frontend-Apps

**1. Backend Setup (einmalig):**

```
1. Gehen Sie zu "rexQL" > "Berechtigungen"
2. Klicken Sie "Hinzufügen"
3. Wählen Sie "Public/Private Key Pair"
4. Notieren Sie sich den Public Key (z.B. rexql_pub_abc123...)
5. Aktivieren Sie den Proxy unter "rexQL" > "Konfiguration"
```

**2. Frontend Integration:**

```javascript
// Client initialisieren
const client = new RexQLClient({
  baseUrl: 'https://ihre-domain.de',
  publicKey: 'rexql_pub_abc123...',
  useProxy: true
})

// Login-Flow
try {
  await client.login(username, password)
  // Jetzt können Sie GraphQL Queries ausführen
  const data = await client.query('{ rexArticleList { id name } }')
} catch (error) {
  console.error('Authentication failed:', error)
}
```

**3. Test-Client:**
Öffnen Sie `assets/test-client.html` in Ihrem Browser für eine vollständige Demo-Anwendung.

### JavaScript (Legacy: Direkter Zugriff)

```javascript
// Nur für Server-zu-Server Kommunikation empfohlen
const query = `{
  rexArticleList(limit: 5) {
    id
    name
    createdate
  }
}`

fetch('/index.php?rex-api-call=rexql_graphql', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'X-API-KEY': 'your_api_key'
  },
  body: JSON.stringify({ query })
})
  .then((response) => response.json())
  .then((data) => console.log(data))
```

### PHP

```php
$query = '{ rexArticleList(limit: 5) { id name createdate } }';

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://ihre-domain.de/index.php?rex-api-call=rexql_graphql");
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-API-KEY: your_api_key"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$result = curl_exec($ch);
$data = json_decode($result, true);
curl_close($ch);
```

## Entwicklung

### Migration von älteren Versionen

Wenn Sie von einer älteren Version upgraden, müssen Sie Ihre GraphQL-Queries aktualisieren:

```graphql
# Alte Namenskonvention (funktioniert nicht mehr):
{
  rexArticles(limit: 5) { ... }    # ❌
  rexClangs { ... }                # ❌
}

# Neue Namenskonvention:
{
  rexArticleList(limit: 5) { ... } # ✅
  rexClangList { ... }             # ✅
}
```

### Abhängigkeiten

- PHP 8.1+
- REDAXO 5.17+
- webonyx/graphql-php ^15.0
- YForm Addon (für YForm-Integration)
- URL Addon (für URL-Integration)
- YRewrite Addon (für Domain-Integration)

### Tests ausführen

```bash
cd src/addons/rexql
composer test
```

## Support

- **GitHub Issues**: [GitHub Repository](https://github.com/FriendsOfREDAXO/rexql)
- **REDAXO Slack**: [friendsofredaxo.slack.com](friendsofredaxo.slack.com)
- **Community**: [REDAXO Community](https://redaxo.org/community/)

## Lizenz

MIT License - siehe [LICENSE](LICENSE) Datei

## Credits

Entwickelt von der REDAXO Community mit ❤️
**[Yves Torres](https://github.com/ynamite)**

Basiert auf [webonyx/graphql-php](https://github.com/webonyx/graphql-php)

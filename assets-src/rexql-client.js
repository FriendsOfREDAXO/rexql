/**
 * rexQL JavaScript Client für Public Headless CMS
 *
 * Entwickelt für öffentliche React/Vue/Angular Apps ohne Benutzerauthentifizierung.
 * Sicherheit durch Domain-/IP-Beschränkungen und CORS anstatt Benutzer-Sessions.
 *
 * Setup:
 * 1. Erstellen Sie einen domain-beschränkten API-Schlüssel im REDAXO Backend
 * 2. Konfigurieren Sie CORS für Ihre Frontend-Domain(s)
 * 3. Verwenden Sie diesen Client in Ihrer öffentlichen App
 *
 * Optionale Authentifizierung ist verfügbar für sensible Daten, aber nicht erforderlich.
 */

class RexQLClient {
  constructor(options = {}) {
    this.baseUrl = options.baseUrl || ''
    this.apiKey = options.apiKey // Haupt-API-Key für Public Headless CMS
    this.publicKey = options.publicKey // Public Key für optional Auth-Proxy-Modus
    this.sessionToken = options.sessionToken
    this.useProxy = options.useProxy || false // Standard: direkte API-Nutzung
    this.enableAuth = options.enableAuth || false // Standard: keine Authentifizierung
  }

  /**
   * GraphQL Query ausführen
   *
   * Für Public Headless CMS wird standardmäßig der direkte API-Zugriff
   * mit Domain-/IP-Beschränkungen und CORS verwendet.
   */
  async query(query, variables = null, operationName = null) {
    const endpoint = this.useProxy
      ? `${this.baseUrl}/index.php?rex-api-call=rexql_proxy`
      : `${this.baseUrl}/index.php?rex-api-call=rexql_graphql`

    const headers = {
      'Content-Type': 'application/json'
    }

    if (this.useProxy && this.enableAuth) {
      // Proxy-Modus mit Authentifizierung (für sensible Daten)
      if (this.sessionToken) {
        headers['Authorization'] = `Bearer ${this.sessionToken}`
      }
      if (this.publicKey) {
        headers['X-Public-Key'] = this.publicKey
      }
    } else {
      // Direkter Modus (Standard für Public Headless CMS)
      if (this.apiKey) {
        headers['X-API-KEY'] = this.apiKey
      }
    }

    const body = {
      query,
      variables,
      operationName
    }

    try {
      const response = await fetch(endpoint, {
        method: 'POST',
        headers,
        body: JSON.stringify(body),
        // CORS credentials nur bei Auth-Proxy erforderlich
        credentials: this.useProxy && this.enableAuth ? 'include' : 'omit'
      })

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}: ${response.statusText}`)
      }

      const result = await response.json()

      if (result.errors && result.errors.length > 0) {
        throw new Error(result.errors[0].message)
      }

      return result
    } catch (error) {
      console.error('RexQL Query Error:', error)
      throw error
    }
  }

  /**
   * Session Token setzen (für optionale Authentifizierung)
   */
  setSessionToken(token) {
    this.sessionToken = token
  }

  /**
   * Login und Session Token erhalten
   */
  async login(username, password) {
    const response = await fetch(
      `${this.baseUrl}/index.php?rex-api-call=rexql_auth&action=login`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          username: username,
          password: password
        })
      }
    )

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${response.statusText}`)
    }

    const result = await response.json()

    if (!result.success) {
      throw new Error(result.error || 'Login failed')
    }

    // Session Token speichern
    this.sessionToken = result.session_token

    // Optional: In localStorage speichern für persistente Sessions
    if (typeof localStorage !== 'undefined') {
      localStorage.setItem('rexql_session_token', this.sessionToken)
    }

    return result
  }

  /**
   * Session Token validieren
   */
  async validateSession() {
    if (!this.sessionToken) {
      // Versuche Token aus localStorage zu laden
      if (typeof localStorage !== 'undefined') {
        this.sessionToken = localStorage.getItem('rexql_session_token')
      }
    }

    if (!this.sessionToken) {
      throw new Error('No session token available')
    }

    const response = await fetch(
      `${this.baseUrl}/index.php?rex-api-call=rexql_auth&action=validate`,
      {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body: new URLSearchParams({
          token: this.sessionToken
        })
      }
    )

    const result = await response.json()

    if (!result.success) {
      // Token ungültig - löschen
      this.sessionToken = null
      if (typeof localStorage !== 'undefined') {
        localStorage.removeItem('rexql_session_token')
      }
      throw new Error(result.error || 'Session validation failed')
    }

    return result
  }

  /**
   * Logout
   */
  async logout() {
    if (this.sessionToken) {
      try {
        await fetch(
          `${this.baseUrl}/index.php?rex-api-call=rexql_auth&action=logout`,
          {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
              token: this.sessionToken
            })
          }
        )
      } catch (e) {
        console.warn('Logout request failed:', e)
      }
    }

    // Token lokal löschen
    this.sessionToken = null
    if (typeof localStorage !== 'undefined') {
      localStorage.removeItem('rexql_session_token')
    }
  }

  /**
   * Artikel abfragen (Beispiel)
   */
  async getArticles(limit = 10, clangId = 1) {
    return this.query(`
            {
                rexArticleList(limit: ${limit}, clang_id: ${clangId}) {
                    id
                    name
                    createdate
                    status
                }
            }
        `)
  }

  /**
   * Einzelnen Artikel abfragen (Beispiel)
   */
  async getArticle(id) {
    return this.query(`
            {
                rexArticle(id: ${id}) {
                    id
                    name
                    createdate
                    status
                    slices: rexArticleSliceList {
                        id
                        module_id
                        value1
                        value2
                    }
                }
            }
        `)
  }

  /**
   * Medien abfragen (Beispiel)
   */
  async getMedia(limit = 20) {
    return this.query(`
            {
                rexMediaList(limit: ${limit}) {
                    id
                    filename
                    title
                    category_id
                    filesize
                }
            }
        `)
  }
}

// Verwendungsbeispiele:

// 1. Public Headless CMS (empfohlen für öffentliche React/Vue/Angular Apps)
const cmsClient = new RexQLClient({
  baseUrl: 'https://cms.ihre-domain.de',
  apiKey: 'rexql_abc123...', // Domain-beschränkter API Key
  useProxy: false, // Direkter API-Zugriff
  enableAuth: false // Keine Benutzer-Authentifizierung
})

// 2. Dev-Modus (ohne API Key in Development, falls isSecure=false im project addon)
const devClient = new RexQLClient({
  baseUrl: 'http://redaxo-graph-ql.test',
  // apiKey nicht erforderlich in dev mode
  useProxy: false,
  enableAuth: false
})

// 3. Optionale Authentifizierung für sensible Daten (Proxy-Modus)
const authClient = new RexQLClient({
  baseUrl: 'https://cms.ihre-domain.de',
  publicKey: 'rexql_pub_abc123...',
  useProxy: true,
  enableAuth: true
})

// ==========================================
// REACT USAGE EXAMPLE für Public Headless CMS
// ==========================================

/*
// 1. Setup des rexQL Clients
import { useState, useEffect } from 'react';

const cmsClient = new RexQLClient({
  baseUrl: 'http://redaxo-graph-ql.test',
  apiKey: 'rexql_abc123...', // Domain-beschränkter API Key
  useProxy: false,
  enableAuth: false
});

// 2. React Hook für Artikel
function useArticles(limit = 10) {
  const [articles, setArticles] = useState([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function loadArticles() {
      try {
        setLoading(true);
        setError(null);
        const result = await cmsClient.getArticles(limit);
        setArticles(result.data.rexArticleList);
      } catch (err) {
        setError(err.message);
        console.error('Failed to load articles:', err);
      } finally {
        setLoading(false);
      }
    }
    
    loadArticles();
  }, [limit]);

  return { articles, loading, error };
}

// 3. React Component
function ArticleList() {
  const { articles, loading, error } = useArticles(10);

  if (loading) return <div>Loading articles...</div>;
  if (error) return <div>Error: {error}</div>;

  return (
    <div className="articles">
      <h2>Latest Articles</h2>
      {articles.map(article => (
        <article key={article.id} className="article-card">
          <h3>{article.name}</h3>
          <time>{new Date(article.createdate).toLocaleDateString()}</time>
          <p>Status: {article.status === '1' ? 'Published' : 'Draft'}</p>
        </article>
      ))}
    </div>
  );
}

// 4. Einzelner Artikel mit Navigation
function ArticleDetail({ articleId }) {
  const [article, setArticle] = useState(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    async function loadArticle() {
      try {
        const result = await cmsClient.getArticle(articleId);
        setArticle(result.data.rexArticle);
      } catch (err) {
        console.error('Failed to load article:', err);
      } finally {
        setLoading(false);
      }
    }
    
    if (articleId) {
      loadArticle();
    }
  }, [articleId]);

  if (loading) return <div>Loading...</div>;
  if (!article) return <div>Article not found</div>;

  return (
    <article>
      <h1>{article.name}</h1>
      <time>{new Date(article.createdate).toLocaleDateString()}</time>
      
      {article.slices && article.slices.map(slice => (
        <div key={slice.id} className="content-slice">
          <div dangerouslySetInnerHTML={{ __html: slice.value1 }} />
        </div>
      ))}
    </article>
  );
}

// 5. Custom GraphQL Query Hook
function useCustomQuery(query, variables = {}) {
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  useEffect(() => {
    async function executeQuery() {
      try {
        setLoading(true);
        setError(null);
        const result = await cmsClient.query(query, variables);
        setData(result.data);
      } catch (err) {
        setError(err.message);
      } finally {
        setLoading(false);
      }
    }

    if (query) {
      executeQuery();
    }
  }, [query, JSON.stringify(variables)]);

  return { data, loading, error };
}

// Verwendung:
// const { data, loading, error } = useCustomQuery(`
//   {
//     rexArticleList(limit: 5, status: 1) {
//       id
//       name
//       createdate
//     }
//   }
// `);
*/

// ==========================================
// VANILLA JAVASCRIPT BEISPIEL
// ==========================================

// ==========================================
// VANILLA JAVASCRIPT BEISPIEL
// ==========================================

/*
// Basic Setup
const cmsClient = new RexQLClient({
  baseUrl: 'https://cms.ihre-domain.de',
  apiKey: 'rexql_abc123...'
});

// Load articles on page load
async function loadArticles() {
  try {
    const result = await cmsClient.getArticles(5);
    const articles = result.data.rexArticleList;
    
    const container = document.getElementById('articles');
    container.innerHTML = articles.map(article => `
      <article class="article-card">
        <h3>${article.name}</h3>
        <time>${new Date(article.createdate).toLocaleDateString()}</time>
      </article>
    `).join('');
    
  } catch (error) {
    console.error('Failed to load articles:', error);
    document.getElementById('articles').innerHTML = 
      '<p>Error loading articles: ' + error.message + '</p>';
  }
}

// Event Listener
document.addEventListener('DOMContentLoaded', loadArticles);

// Dynamic loading with button
document.getElementById('load-more').addEventListener('click', async () => {
  try {
    const result = await cmsClient.query(`
      {
        rexArticleList(limit: 10, offset: 5) {
          id
          name
          createdate
          status
        }
      }
    `);
    
    // Process results...
    console.log('More articles:', result.data.rexArticleList);
  } catch (error) {
    console.error('Error:', error);
  }
});
*/

// Export für Module
if (typeof module !== 'undefined' && module.exports) {
  module.exports = RexQLClient
}

// Global für Browser
if (typeof window !== 'undefined') {
  window.RexQLClient = RexQLClient
}

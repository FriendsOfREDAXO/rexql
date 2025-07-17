export const playground = {
  init: function () {
    console.log('rexQL.playground.init()')
    const queryTextarea = document.getElementById('graphql-query')
    const apiKeyInput = document.getElementById('api-key-input')
    const executeButton = document.getElementById('execute-query')
    const clearButton = document.getElementById('clear-result')
    const introspectButton = document.getElementById('introspect')
    const resultPre = document.getElementById('query-result')

    if (!queryTextarea || !executeButton) return // Nicht auf Playground-Seite

    this.initCodemirror(queryTextarea)

    executeButton.addEventListener('click', function () {
      const query = queryTextarea.value.trim()
      rexQL.playground.executeQuery(
        query,
        apiKeyInput.value.trim(),
        resultPre,
        executeButton,
        introspectButton
      )
    })

    introspectButton.addEventListener('click', function () {
      const introspectionQuery = `
{
  __schema {
    queryType {
      name
      fields {
        name
        type {
          name
          kind
        }
      }
    }
  }
}`
      rexQL.playground.executeQuery(
        introspectionQuery,
        apiKeyInput.value.trim(),
        resultPre,
        executeButton,
        introspectButton
      )
    })

    clearButton.addEventListener('click', function () {
      resultPre.textContent =
        'Führen Sie eine Query aus, um Ergebnisse zu sehen...'
    })
  },

  executeQuery: function (
    query,
    apiKey,
    resultPre,
    executeButton,
    introspectButton
  ) {
    if (!query) {
      alert('Bitte geben Sie eine GraphQL Query ein.')
      return
    }

    resultPre.textContent = 'Führe Query aus...'
    executeButton.disabled = true
    introspectButton.disabled = true

    const requestData = { query: query }
    const headers = { 'Content-Type': 'application/json' }

    if (apiKey) {
      headers['X-API-KEY'] = apiKey
    }

    // Get endpoint URL from data attribute or global variable
    const endpointUrl =
      document
        .querySelector('[data-endpoint-url]')
        ?.getAttribute('data-endpoint-url') ||
      window.rexQLEndpointUrl ||
      '/index.php?rex-api-call=rexql_graphql'

    fetch(endpointUrl, {
      method: 'POST',
      headers: headers,
      body: JSON.stringify(requestData)
    })
      .then(async (response) => {
        const data = await response.json()
        if (!response.ok) {
          let errorMsg =
            'HTTP ' + response.status + ': ' + response.statusText + '\n\n'
          if (data.errors && data.errors.length > 0) {
            errorMsg += 'GraphQL Fehler:\n'
            data.errors.forEach((error) => {
              errorMsg += '• ' + error.message + '\n'
            })
          }

          throw new Error(errorMsg)
        } else {
          resultPre.textContent = JSON.stringify(data, null, 2)
        }
      })
      .catch((error) => {
        resultPre.textContent =
          'Netzwerkfehler: ' +
          error.message +
          '\n\nTipps: Stellen Sie sicher, dass:\n• Der GraphQL Endpoint erreichbar ist\n• Sie einen gültigen API-Schlüssel eingegeben haben\n• Die Query syntaktisch korrekt ist'
      })
      .finally(() => {
        executeButton.disabled = false
        introspectButton.disabled = false
      })
  },

  initCodemirror: async function (textarea) {
    const lib = await import('./graphql-editor')
    const container = document.getElementById('graphql-editor-container')
    textarea.style.display = 'none'
    const graphqlEditor = new lib.GraphQLEditor(container, {
      schema,
      initialValue: textarea.value || '',
      height: '500px',
      onChange: (content) => {
        textarea.value = content
      }
    })
  }
}

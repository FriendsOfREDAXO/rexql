export const playground = {
  init: function () {
    console.log('rexQL.playground.init()')
    const queryTextarea = document.getElementById('graphql-query')
    const apiKeyInput = document.getElementById('api-key-input')
    const executeButton = document.getElementById('execute-query')
    const clearButton = document.getElementById('clear-result')
    const resultPre = document.getElementById('query-result')

    if (!queryTextarea || !executeButton) return // Not on playground page

    this.initCodemirror(queryTextarea)

    executeButton.addEventListener('click', function () {
      const query = queryTextarea.value.trim()
      rexQL.playground.executeQuery(
        query,
        apiKeyInput.value.trim(),
        resultPre,
        executeButton
      )
    })

    clearButton.addEventListener('click', function () {
      resultPre.textContent =
        'Führen Sie eine Query aus, um Ergebnisse zu sehen...'
    })
  },

  executeQuery: function (query, apiKey, resultPre, executeButton) {
    if (!query) {
      alert('Bitte geben Sie eine GraphQL Query ein.')
      return
    }

    resultPre.textContent = 'Führe Query aus...'
    executeButton.disabled = true

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
      '/index.php?rex-api-call=rexql'

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
      })
  },

  initCodemirror: async function (textarea) {
    const lib = await import('./graphql-editor')

    const schemaContainer = document.getElementById('graphql-schema')
    new lib.GraphQLEditor(schemaContainer, {
      schema,
      initialValue: '',
      height: '600px',
      readOnly: true,
      renderSchema: true,
      onChange: (content) => {
        textarea.value = content
      }
    })

    const container = document.getElementById('graphql-editor')
    new lib.GraphQLEditor(container, {
      schema,
      initialValue: textarea.value || '',
      height: '400px',
      onChange: (content) => {
        textarea.value = content
      }
    })
  }
}

/**
 * rexQL JavaScript Functions
 */

// Globales rexQL Objekt
window.rexQL = window.rexQL || {}

/**
 * GraphQL Playground funktionalität
 */
rexQL.playground = {
  init: function () {
    console.log('rexQL.playground.init()')
    const queryTextarea = document.getElementById('graphql-query')
    const apiKeyInput = document.getElementById('api-key-input')
    const executeButton = document.getElementById('execute-query')
    const clearButton = document.getElementById('clear-result')
    const introspectButton = document.getElementById('introspect')
    const resultPre = document.getElementById('query-result')

    if (!queryTextarea || !executeButton) return // Nicht auf Playground-Seite

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
      .then((response) => {
        if (!response.ok) {
          throw new Error(
            'HTTP ' + response.status + ': ' + response.statusText
          )
        }
        return response.json()
      })
      .then((data) => {
        if (data.errors && data.errors.length > 0) {
          let errorMsg = 'GraphQL Fehler:\n'
          data.errors.forEach((error) => {
            if (error.message.includes('API-Schlüssel erforderlich')) {
              errorMsg +=
                '• Authentifizierung erforderlich: Bitte geben Sie einen gültigen API-Schlüssel ein.\n'
            } else if (error.message.includes('invalid_api_key')) {
              errorMsg +=
                '• Ungültiger API-Schlüssel: Überprüfen Sie Ihren API-Schlüssel.\n'
            } else {
              errorMsg += '• ' + error.message + '\n'
            }
          })

          resultPre.textContent = JSON.stringify(
            {
              data: data.data,
              errors: data.errors,
              info: errorMsg
            },
            null,
            2
          )
        } else {
          resultPre.textContent = JSON.stringify(data, null, 2)
        }
      })
      .catch((error) => {
        resultPre.textContent =
          'Netzwerkfehler: ' +
          error.message +
          '\n\nStellen Sie sicher, dass:\n• Der GraphQL Endpoint erreichbar ist\n• Sie einen gültigen API-Schlüssel eingegeben haben\n• Die Query syntaktisch korrekt ist'
      })
      .finally(() => {
        executeButton.disabled = false
        introspectButton.disabled = false
      })
  }
}

/**
 * API-Schlüssel in Zwischenablage kopieren - allgemeine Funktion
 */
rexQL.copyToClipboard = function (text, successMessage) {
  successMessage = successMessage || 'In Zwischenablage kopiert'

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard
      .writeText(text)
      .then(function () {
        alert(successMessage)
      })
      .catch(function () {
        rexQL.fallbackCopyToClipboard(text, successMessage)
      })
  } else {
    rexQL.fallbackCopyToClipboard(text, successMessage)
  }
}

/**
 * Fallback für ältere Browser
 */
rexQL.fallbackCopyToClipboard = function (text, successMessage) {
  var textArea = document.createElement('textarea')
  textArea.value = text
  textArea.style.position = 'fixed'
  textArea.style.top = '0'
  textArea.style.left = '0'
  textArea.style.width = '2em'
  textArea.style.height = '2em'
  textArea.style.padding = '0'
  textArea.style.border = 'none'
  textArea.style.outline = 'none'
  textArea.style.boxShadow = 'none'
  textArea.style.background = 'transparent'

  document.body.appendChild(textArea)
  textArea.focus()
  textArea.select()

  try {
    var successful = document.execCommand('copy')
    if (successful) {
      alert(successMessage)
    } else {
      prompt('Bitte manuell kopieren:', text)
    }
  } catch (err) {
    prompt('Bitte manuell kopieren:', text)
  }

  document.body.removeChild(textArea)
}

/**
 * Legacy-Funktionen für Kompatibilität
 */
window.copyToClipboard = function (text) {
  if (text.includes('rexql_')) {
    rexQL.copyToClipboard(text, 'API-Schlüssel in Zwischenablage kopiert')
  } else {
    rexQL.copyToClipboard(text, 'URL in Zwischenablage kopiert')
  }
}

/**
 * Benachrichtigung anzeigen
 */
rexQL.showNotification = function (message, type) {
  type = type || 'info'

  // Bootstrap Alert erstellen
  var alertClass = 'alert-info'
  if (type === 'success') alertClass = 'alert-success'
  if (type === 'error') alertClass = 'alert-danger'
  if (type === 'warning') alertClass = 'alert-warning'

  var alert = document.createElement('div')
  alert.className = 'alert ' + alertClass + ' alert-dismissible'
  alert.innerHTML =
    '<button type="button" class="close" data-dismiss="alert">&times;</button>' +
    '<strong>' +
    message +
    '</strong>'

  // Alert am Anfang der Seite einfügen
  var container = document.querySelector('.rex-page-header') || document.body
  container.insertBefore(alert, container.firstChild)

  // Nach 3 Sekunden automatisch ausblenden
  setTimeout(function () {
    if (alert.parentNode) {
      alert.parentNode.removeChild(alert)
    }
  }, 3000)
}

/**
 * GraphQL Query Formatter
 */
rexQL.formatGraphQL = function (query) {
  // Einfache Formatierung - in Produktion würde man eine richtige GraphQL-Bibliothek verwenden
  return query
    .replace(/\s+/g, ' ')
    .replace(/\{\s*/g, '{\n  ')
    .replace(/\s*\}/g, '\n}')
    .replace(/,\s*/g, ',\n  ')
    .trim()
}

/**
 * GraphQL Query validieren
 */
rexQL.validateGraphQL = function (query) {
  // Einfache Validierung
  var openBraces = (query.match(/\{/g) || []).length
  var closeBraces = (query.match(/\}/g) || []).length

  if (openBraces !== closeBraces) {
    return 'Unbalanced braces in GraphQL query'
  }

  if (query.trim().length === 0) {
    return 'Empty query'
  }

  return null // Keine Fehler
}

/**
 * Konfiguration: Table Selection funktionalität
 */
rexQL.config = {
  init: function () {
    // Handler für "Alle Core-Tabellen auswählen"
    const coreSelectAll = document.getElementById('select_all_core_tables')
    if (coreSelectAll) {
      coreSelectAll.addEventListener('change', function () {
        const checkboxes = document.querySelectorAll(
          '.table-checkbox.core-table'
        )
        checkboxes.forEach((checkbox) => {
          checkbox.checked = this.checked
        })
      })
    }

    // Handler für "Alle YForm-Tabellen auswählen" (falls vorhanden)
    const yformSelectAll = document.getElementById('select_all_yform_tables')
    if (yformSelectAll) {
      yformSelectAll.addEventListener('change', function () {
        const checkboxes = document.querySelectorAll(
          '.table-checkbox.yform-table'
        )
        checkboxes.forEach((checkbox) => {
          checkbox.checked = this.checked
        })
      })
    }

    // Status der "Alle auswählen" Checkboxen aktualisieren
    const updateSelectAllStatus = (groupSelector, selectAllId) => {
      const checkboxes = document.querySelectorAll(groupSelector)
      const selectAll = document.getElementById(selectAllId)

      if (!selectAll) return

      const allChecked = Array.from(checkboxes).every(
        (checkbox) => checkbox.checked
      )
      selectAll.checked = allChecked
    }

    // Event-Listener für Core-Tabellen
    const coreCheckboxes = document.querySelectorAll(
      '.table-checkbox.core-table'
    )
    coreCheckboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', function () {
        updateSelectAllStatus(
          '.table-checkbox.core-table',
          'select_all_core_tables'
        )
      })
    })

    // Event-Listener für YForm-Tabellen
    const yformCheckboxes = document.querySelectorAll(
      '.table-checkbox.yform-table'
    )
    yformCheckboxes.forEach((checkbox) => {
      checkbox.addEventListener('change', function () {
        updateSelectAllStatus(
          '.table-checkbox.yform-table',
          'select_all_yform_tables'
        )
      })
    })

    // Initial-Status der "Alle auswählen" Checkboxen setzen
    updateSelectAllStatus(
      '.table-checkbox.core-table',
      'select_all_core_tables'
    )
    updateSelectAllStatus(
      '.table-checkbox.yform-table',
      'select_all_yform_tables'
    )
  }
}

$(document).on('rex:ready', function () {
  if (window.location.href.indexOf('page=rexql') === -1) return

  console.log('rexQL Addon rex:ready')

  if (typeof copyToClipboard === 'undefined') {
    window.copyToClipboard = function (text) {
      if (navigator.clipboard) {
        navigator.clipboard
          .writeText(text)
          .then(function () {
            // Success feedback could be added here
          })
          .catch(function (err) {
            console.error('Failed to copy text: ', err)
          })
      } else {
        // Fallback for older browsers
        const textArea = document.createElement('textarea')
        textArea.value = text
        document.body.appendChild(textArea)
        textArea.select()
        try {
          document.execCommand('copy')
        } catch (err) {
          console.error('Failed to copy text: ', err)
        }
        document.body.removeChild(textArea)
      }
    }
  }

  // Syntax-Highlighting für Code-Blöcke (einfach)
  var codeBlocks = document.querySelectorAll('pre code')
  codeBlocks.forEach(function (block) {
    if (block.textContent.trim().startsWith('{')) {
      block.classList.add('language-graphql')
    }
  })

  // API-Schlüssel Copy-Buttons initialisieren
  var copyButtons = document.querySelectorAll('[data-copy]')
  copyButtons.forEach(function (button) {
    button.addEventListener('click', function (e) {
      e.preventDefault()
      var value = this.getAttribute('data-copy')
      rexQL.copyToClipboard(value)
    })
  })

  // Playground initialisieren (falls vorhanden)
  if (window.location.href.indexOf('page=rexql/playground') !== -1) {
    rexQL.playground.init()
  }

  // Konfiguration initialisieren (falls vorhanden) 
  if (window.location.href.indexOf('page=rexql/config') !== -1) {
    rexQL.config.init()
  }
})

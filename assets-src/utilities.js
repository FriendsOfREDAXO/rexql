export const copyToClipboard = function (event, text, successMessage) {
  successMessage = successMessage || 'In Zwischenablage kopiert'

  if (navigator.clipboard && navigator.clipboard.writeText) {
    navigator.clipboard
      .writeText(text)
      .then(function () {
        // alert(successMessage)
        showNotification(successMessage, 'success', event.target)
      })
      .catch(function () {
        rexQL.fallbackCopyToClipboard(event, text, successMessage)
      })
  } else {
    rexQL.fallbackCopyToClipboard(event, text, successMessage)
  }
}

/**
 * Fallback for older browsers that don't support Clipboard API
 */
export const fallbackCopyToClipboard = function (event, text, successMessage) {
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
      showNotification(successMessage, 'success', event.target)
    } else {
      prompt('Bitte manuell kopieren:', text)
    }
  } catch (err) {
    console.error('Copy to clipboard failed:', err)
    prompt('Bitte manuell kopieren:', text)
  }

  document.body.removeChild(textArea)
}

/**
 * Show a notification message
 */
export const showNotification = function (message, type, node) {
  type = type || 'info'

  // Bootstrap Alert erstellen
  var alertClass = 'alert-info'
  if (type === 'success') alertClass = 'alert-success'
  if (type === 'error') alertClass = 'alert-danger'
  if (type === 'warning') alertClass = 'alert-warning'

  var alert = document.createElement('div')
  alert.className =
    'alert rexql-notificiation ' + alertClass + ' alert-dismissible'
  alert.innerHTML =
    '<div><button type="button" class="close" data-dismiss="alert">&times;</button>' +
    '<strong>' +
    message +
    '</strong></div>'

  // Alert am Anfang der Seite einfügen
  try {
    var container = document.querySelector('.rex-page-header') || document.body
    container.appendChild(alert)
  } catch (err) {
    console.error('showNotification:', err)
  }

  // Nach 3 Sekunden automatisch ausblenden
  setTimeout(function () {
    if (alert.parentNode) {
      alert.parentNode.removeChild(alert)
    }
  }, 3000)
}

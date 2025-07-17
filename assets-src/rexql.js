import { playground } from './playground'
import {
  copyToClipboard,
  fallbackCopyToClipboard,
  showNotification,
  formatGraphQL,
  validateGraphQL
} from './utilities'
import { config } from './config'

// Globales rexQL Objekt
window.rexQL = window.rexQL || {
  playground,
  copyToClipboard,
  fallbackCopyToClipboard,
  showNotification,
  formatGraphQL,
  validateGraphQL,
  config
}

/**
 * Legacy-Funktionen für Kompatibilität
 */
// window.copyToClipboard = function (text) {
//   rexQL.copyToClipboard(text, 'In Zwischenablage kopiert')
// }

console.log('rexQL Addon rex:ready')

// API-Schlüssel Copy-Buttons initialisieren
var copyButtons = document.querySelectorAll('[data-copy]')
copyButtons.forEach(function (button) {
  button.addEventListener('click', function (e) {
    e.preventDefault()
    var value = this.getAttribute('data-copy')
    rexQL.copyToClipboard(e, value)
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

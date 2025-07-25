import { playground } from './playground'
import {
  copyToClipboard,
  fallbackCopyToClipboard,
  showNotification
} from './utilities'
import { config } from './config'

// Globales rexQL Objekt
window.rexQL = window.rexQL || {
  playground,
  copyToClipboard,
  fallbackCopyToClipboard,
  showNotification,
  config
}

console.log('rexQL Addon rex:ready')

// Initialize copy to clipboard buttons
var copyButtons = document.querySelectorAll('[data-copy]')
copyButtons.forEach(function (button) {
  button.addEventListener('click', function (e) {
    e.preventDefault()
    var value = this.getAttribute('data-copy')
    rexQL.copyToClipboard(e, value)
  })
})

// Initialize playground if on playground page
if (window.location.href.indexOf('page=rexql/playground') !== -1) {
  rexQL.playground.init()
}

// Initialize config if on config page
if (window.location.href.indexOf('page=rexql/config') !== -1) {
  rexQL.config.init()
}

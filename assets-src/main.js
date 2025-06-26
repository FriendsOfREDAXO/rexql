/**
 * Main entry point for rexQL assets
 * This file is used by Vite to build all assets
 */

// Import CSS
import './rexql.css'

// Import and initialize main rexQL functionality
import './rexql.js'

// Initialize rexQL on DOM ready
document.addEventListener('DOMContentLoaded', function () {
  // Initialize rexQL playground if on playground page
  if (typeof rexQL !== 'undefined' && rexQL.playground) {
    rexQL.playground.init()
  }

  // Initialize permissions page functionality if present
  if (typeof rexQL !== 'undefined' && rexQL.permissions) {
    rexQL.permissions.init()
  }

  // Initialize copy to clipboard functionality
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
})

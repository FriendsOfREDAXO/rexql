/**
 * Konfiguration: Table Selection funktionalität
 */
export const config = {
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

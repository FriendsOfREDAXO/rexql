/**
 * Configuration: Table Selection functionality
 */
export const config = {
  init: function () {
    // Handler for "Select All Core Tables"
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

    // Handler for "Select All YForm Tables" (if available)
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

    // update status when "select all" checkbox is toggled
    const updateSelectAllStatus = (groupSelector, selectAllId) => {
      const checkboxes = document.querySelectorAll(groupSelector)
      const selectAll = document.getElementById(selectAllId)

      if (!selectAll) return

      const allChecked = Array.from(checkboxes).every(
        (checkbox) => checkbox.checked
      )
      selectAll.checked = allChecked
    }

    // Event-Listener for Core Tables
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

    // Event-Listener for YForm Tables
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

    // Initial status of "select all" checkboxes
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

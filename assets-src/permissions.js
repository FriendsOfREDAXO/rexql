/**
 * Permissions
 */
export const permissions = {
  init: function () {
    // Handler for "Select All Core Tables"
    const checkboxes = Array.from(
      document.querySelectorAll('input[type="checkbox"][name="permissions[]"]')
    )
    if (checkboxes.length === 0) {
      console.warn('No checkboxes found for permissions.')
      return
    }
    const checkboxesFiltered = checkboxes.filter(
      (checkbox) => checkbox.value !== 'read:all'
    )
    const selectAll = checkboxes[0]
    if (selectAll) {
      selectAll.addEventListener('change', function () {
        checkboxes.forEach((checkbox) => {
          checkbox.checked = this.checked
        })
      })
    }

    // update status when "select all" checkbox is toggled
    const updateSelectAllStatus = () => {
      if (!selectAll.checked) return

      checkboxesFiltered.forEach((checkbox) => {
        checkbox.checked = true
      })
    }

    // Initial status of "select all" checkboxes
    updateSelectAllStatus()
  }
}

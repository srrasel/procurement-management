// Procurement Management Frontend JavaScript

// Use existing jQuery and procurement_ajax if available, don't redeclare
const jQuery = window.jQuery || $ // Use existing jQuery

// Debug logging
console.log('Procurement Frontend JS loaded')
console.log('jQuery available:', typeof jQuery !== 'undefined')
console.log('procurement_ajax available:', typeof window.procurement_ajax !== 'undefined')

jQuery(document).ready(($) => {
  console.log('Document ready, initializing procurement functionality')

  // Form validation and submission
  $(".procurement-form").on("submit", function (e) {
    if (!validateForm(this)) {
      e.preventDefault()
      return false
    }
  })

  // File upload validation
  $('input[type="file"]').on("change", function () {
    validateFileUpload(this)
  })

  // Real-time form validation
  $(".form-group input, .form-group textarea, .form-group select").on("blur", function () {
    validateField(this)
  })

  // IFU and RCCM number formatting
  $("#ifu_number, #rccm_number").on("input", function () {
    this.value = this.value.replace(/[^0-9A-Za-z-]/g, "").toUpperCase()
  })

  // Phone number formatting
  $("#contact_phone").on("input", function () {
    this.value = this.value.replace(/[^0-9+\-\s()]/g, "")
  })

  // Tender search and filtering
  $("#tender-search").on(
    "input",
    debounce(function () {
      filterTenders($(this).val())
    }, 300),
  )

  $("#tender-category-filter").on("change", function () {
    filterTendersByCategory($(this).val())
  })

  // Bid submission
  $(document).on("submit", "#bid-form", function (e) {
    e.preventDefault()
    console.log('Bid form submitted')
    submitBid(this)
  })

  // PPM document download tracking
  $(".ppm-download-link").on("click", function () {
    trackPPMDownload($(this).data("ppm-id"))
  })

  // Auto-save draft functionality
  let autoSaveTimer
  $(".auto-save-form input, .auto-save-form textarea").on("input", () => {
    clearTimeout(autoSaveTimer)
    autoSaveTimer = setTimeout(() => {
      autoSaveDraft()
    }, 2000)
  })

  // Functions
  function validateForm(form) {
    let isValid = true
    const $form = $(form)

    // Clear previous errors
    $form.find(".error-message").remove()
    $form.find(".error").removeClass("error")

    // Validate required fields
    $form.find("[required]").each(function () {
      if (!validateField(this)) {
        isValid = false
      }
    })

    // Validate email format
    $form.find('input[type="email"]').each(function () {
      if (this.value && !isValidEmail(this.value)) {
        showFieldError(this, "Please enter a valid email address")
        isValid = false
      }
    })

    // Validate file uploads
    $form.find('input[type="file"]').each(function () {
      if (!validateFileUpload(this)) {
        isValid = false
      }
    })

    return isValid
  }

  function validateField(field) {
    const $field = $(field)
    const value = field.value.trim()

    // Clear previous error
    $field.removeClass("error")
    $field.siblings(".error-message").remove()

    // Check required fields
    if (field.hasAttribute("required") && !value) {
      showFieldError(field, "This field is required")
      return false
    }

    // Specific validations
    switch (field.type) {
      case "email":
        if (value && !isValidEmail(value)) {
          showFieldError(field, "Please enter a valid email address")
          return false
        }
        break

      case "tel":
        if (value && !isValidPhone(value)) {
          showFieldError(field, "Please enter a valid phone number")
          return false
        }
        break
    }

    // Custom validations
    if (field.id === "ifu_number" && value && !isValidIFU(value)) {
      showFieldError(field, "Please enter a valid IFU number")
      return false
    }

    if (field.id === "rccm_number" && value && !isValidRCCM(value)) {
      showFieldError(field, "Please enter a valid RCCM number")
      return false
    }

    return true
  }

  function showFieldError(field, message) {
    const $field = $(field)
    $field.addClass("error")
    $field.after(
      '<div class="error-message" style="color: #e74c3c; font-size: 12px; margin-top: 5px;">' + message + "</div>",
    )
  }

  function validateFileUpload(fileInput) {
    const files = fileInput.files
    const maxSize = 5 * 1024 * 1024 // 5MB
    const allowedTypes = [
      "application/pdf",
      "application/msword",
      "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
    ]

    for (let i = 0; i < files.length; i++) {
      const file = files[i]

      if (file.size > maxSize) {
        showFieldError(fileInput, "File size must be less than 5MB")
        return false
      }

      if (!allowedTypes.includes(file.type)) {
        showFieldError(fileInput, "Only PDF and Word documents are allowed")
        return false
      }
    }

    return true
  }

  function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
    return emailRegex.test(email)
  }

  function isValidPhone(phone) {
    const phoneRegex = /^[+]?[0-9\s\-$$$$]{8,}$/
    return phoneRegex.test(phone)
  }

  function isValidIFU(ifu) {
    // Basic IFU validation - adjust based on your country's format
    return ifu.length >= 8 && /^[0-9A-Z-]+$/.test(ifu)
  }

  function isValidRCCM(rccm) {
    // Basic RCCM validation - adjust based on your country's format
    return rccm.length >= 8 && /^[0-9A-Z-]+$/.test(rccm)
  }

  function filterTenders(searchTerm) {
    $(".tender-card").each(function () {
      const $card = $(this)
      const title = $card.find("h4").text().toLowerCase()
      const description = $card.find(".tender-description").text().toLowerCase()

      if (title.includes(searchTerm.toLowerCase()) || description.includes(searchTerm.toLowerCase())) {
        $card.show()
      } else {
        $card.hide()
      }
    })
  }

  function filterTendersByCategory(category) {
    if (!category) {
      $(".tender-card").show()
      return
    }

    $(".tender-card").each(function () {
      const $card = $(this)
      const cardCategory = $card.data("category")

      if (cardCategory === category) {
        $card.show()
      } else {
        $card.hide()
      }
    })
  }

  function submitBid(form) {
    console.log('submitBid function called')
    const $form = $(form)
    const formData = new FormData(form)
    formData.append("action", "submit_bid")
    formData.append("nonce", window.procurement_ajax.nonce)

    console.log('Form data prepared, submitting AJAX request')
    console.log('AJAX URL:', window.procurement_ajax.ajax_url)
    console.log('Nonce:', window.procurement_ajax.nonce)

    $form.addClass("loading")
    $form.find('button[type="submit"]').prop('disabled', true).text('Submitting...')

    $.ajax({
      url: window.procurement_ajax.ajax_url,
      type: "POST",
      data: formData,
      processData: false,
      contentType: false,
      success: (response) => {
        console.log('AJAX success response:', response)
        $form.removeClass("loading")
        $form.find('button[type="submit"]').prop('disabled', false).text('Submit Bid')

        if (response.success) {
          showNotification("Bid submitted successfully!", "success")
          $form[0].reset()
          // Close modal if it exists
          if ($('#bid-modal').length) {
            $('#bid-modal').removeClass('show')
            $('body').removeClass('modal-open')
          }
          if ($('#bid-submission-modal').length) {
            $('#bid-submission-modal').removeClass('show')
            $('body').removeClass('modal-open')
          }
        } else {
          showNotification("Error: " + response.data, "error")
        }
      },
      error: (xhr, status, error) => {
        console.error('AJAX error:', {xhr, status, error})
        $form.removeClass("loading")
        $form.find('button[type="submit"]').prop('disabled', false).text('Submit Bid')
        showNotification("Network error. Please try again.", "error")
        console.error('AJAX Error:', error)
      },
    })
  }

  function trackPPMDownload(ppmId) {
    $.post(window.procurement_ajax.ajax_url, {
      action: "track_ppm_download",
      ppm_id: ppmId,
      nonce: window.procurement_ajax.nonce,
    })
  }

  function autoSaveDraft() {
    const formData = $(".auto-save-form").serialize()

    $.post(window.procurement_ajax.ajax_url, {
      action: "auto_save_draft",
      form_data: formData,
      nonce: window.procurement_ajax.nonce,
    })
  }

  function showNotification(message, type) {
    const notification = $('<div class="notification notification-' + type + '">' + message + "</div>")
    $("body").append(notification)

    setTimeout(() => {
      notification.fadeOut(() => {
        notification.remove()
      })
    }, 5000)
  }

  function debounce(func, wait) {
    let timeout
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout)
        func(...args)
      }
      clearTimeout(timeout)
      timeout = setTimeout(later, wait)
    }
  }

  // Modal functionality
  function closeModal(modalSelector) {
    $(modalSelector).removeClass('show').hide()
    $('body').removeClass('modal-open')
  }

  function openModal(modalSelector) {
    $(modalSelector).addClass('show').show()
    $('body').addClass('modal-open')
  }

  // Close modals on escape key
  $(document).on('keydown', function(e) {
    if (e.key === 'Escape') {
      closeModal('.procurement-modal')
      closeModal('.tender-modal')
    }
  })

  // Close modal when clicking outside
  $(document).on('click', '.procurement-modal, .tender-modal', function(e) {
    if (e.target === this) {
      closeModal($(this))
    }
  })

  // Close modal when clicking close button
  $(document).on('click', '.modal-close, .tender-modal-close', function(e) {
    e.preventDefault()
    const $modal = $(this).closest('.procurement-modal, .tender-modal')
    closeModal($modal)
  })


})

// Notification styles
const notificationCSS = `
.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    padding: 15px 20px;
    border-radius: 6px;
    color: white;
    font-weight: 600;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.notification-success {
    background-color: #27ae60;
}

.notification-error {
    background-color: #e74c3c;
}

.notification-info {
    background-color: #3498db;
}
`

// Inject notification styles
if (!document.getElementById("procurement-notification-styles")) {
  const style = document.createElement("style")
  style.id = "procurement-notification-styles"
  style.textContent = notificationCSS
  document.head.appendChild(style)
}

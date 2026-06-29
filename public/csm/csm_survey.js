// Client Satisfaction Measurement (CSM) survey behaviours

// Ensure only one checkbox per logical group (for CC questions)
function onlyOne(currentCheckbox, groupName) {
    const checkboxes = document.querySelectorAll('input[name="' + groupName + '[]"]');
    checkboxes.forEach((cb) => {
        if (cb !== currentCheckbox) {
            cb.checked = false;
        }
    });
}

// Ensure only one checkbox per row (for SQD questions)
function onlyOnePerRow(currentCheckbox, groupName) {
    const checkboxes = document.querySelectorAll('input[name="' + groupName + '"]');
    checkboxes.forEach((cb) => {
        if (cb !== currentCheckbox) {
            cb.checked = false;
        }
    });
}

function updateConsentState() {
    const consentYes = document.querySelector('input[name="consent"][value="yes"]');
    const consentNo = document.querySelector('input[name="consent"][value="no"]');
    const submitBtn = document.getElementById('submitBtn');

    const questionInputs = document.querySelectorAll(
        'input[name^="cc"], input[name^="sqd"], textarea[name="suggestions"]'
    );

    const consentYesChecked = consentYes && consentYes.checked;
    const consentNoChecked = consentNo && consentNo.checked;

    if (consentYesChecked) {
        questionInputs.forEach((el) => {
            el.disabled = false;
        });
        if (submitBtn) {
            submitBtn.disabled = false;
        }
    } else {
        // When consent is not "yes" (either "no" or not chosen), clear and disable question part and submit
        questionInputs.forEach((el) => {
            if (el.type === 'checkbox' || el.type === 'radio') {
                el.checked = false;
            } else {
                el.value = '';
            }
            el.disabled = true;
        });
        if (submitBtn) {
            submitBtn.disabled = true;
        }
    }

    // Optional: if explicitly "no", you may show a simple notice
    const body = document.body;
    if (body && consentNoChecked) {
        body.classList.add('consent-declined');
    } else if (body) {
        body.classList.remove('consent-declined');
    }
}

function validateForm(event) {
    const consent = document.querySelector('input[name="consent"]:checked');
    let isValid = true;

    if (!consent || consent.value !== 'yes') {
        alert('You must agree to the consent notice before answering the survey.');
        if (event) {
            event.preventDefault();
        }
        return false;
    }

    // Helper to validate a checkbox group and show/hide error messages
    function validateCheckboxGroup(selector, errorSelector) {
        const inputs = document.querySelectorAll(selector);
        const errorEl = document.querySelector(errorSelector);
        let anyChecked = false;
        inputs.forEach((el) => {
            if (el.checked) {
                anyChecked = true;
            }
        });

        if (!anyChecked) {
            if (errorEl) {
                errorEl.style.display = 'block';
            }
            isValid = false;
        } else if (errorEl) {
            errorEl.style.display = 'none';
        }
    }

    // CC questions
    validateCheckboxGroup('input[name="cc1[]"]', '.cc1-error');
    validateCheckboxGroup('input[name="cc2[]"]', '.cc2-error');
    validateCheckboxGroup('input[name="cc3[]"]', '.cc3-error');

    // SQD questions (1–9)
    validateCheckboxGroup('input[name="sqd1"]', '.sqd1-error');
    validateCheckboxGroup('input[name="sqd2"]', '.sqd2-error');
    validateCheckboxGroup('input[name="sqd3"]', '.sqd3-error');
    validateCheckboxGroup('input[name="sqd4"]', '.sqd4-error');
    validateCheckboxGroup('input[name="sqd5"]', '.sqd5-error');
    validateCheckboxGroup('input[name="sqd6"]', '.sqd6-error');
    validateCheckboxGroup('input[name="sqd7"]', '.sqd7-error');
    validateCheckboxGroup('input[name="sqd8"]', '.sqd8-error');
    validateCheckboxGroup('input[name="sqd9"]', '.sqd9-error');

    if (!isValid && event) {
        event.preventDefault();
    }

    return isValid;
}

document.addEventListener('DOMContentLoaded', function () {
    // Initialize consent-dependent state
    updateConsentState();

    const consentRadios = document.querySelectorAll('input[name="consent"]');
    consentRadios.forEach((radio) => {
        radio.addEventListener('change', updateConsentState);
    });
});

// Expose validateForm globally for inline onsubmit handler
window.validateForm = validateForm;

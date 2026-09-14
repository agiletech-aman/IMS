/**
 * Shared real-time form validation for Name, Email, and Mobile/Phone fields
 * across every Add/Edit form in the app. Applies to any input matching:
 *   - input[name="name"]            -> 3-15 characters
 *   - input[type="email"]           -> valid email format
 *   - input[name="contact"|"phone"] -> exactly 10 digits, numbers only
 *
 * Delegated on document so it works for modal forms already present in the
 * DOM (Bootstrap modals are hidden via CSS, not removed).
 */
(function () {
    'use strict';

    const NAME_MIN = 3;
    const NAME_MAX = 15;
    const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    const PHONE_LENGTH = 10;
    const PHONE_FIELD_NAMES = ['contact', 'phone'];

    function ensureFeedback(input) {
        let feedback = input.nextElementSibling;
        if (!feedback || !feedback.classList.contains('rt-invalid-feedback')) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback rt-invalid-feedback d-block';
            input.insertAdjacentElement('afterend', feedback);
        }
        return feedback;
    }

    function setError(input, message) {
        const feedback = ensureFeedback(input);
        input.setCustomValidity(message || '');
        if (message) {
            input.classList.add('is-invalid');
            feedback.textContent = message;
            feedback.style.display = 'block';
        } else {
            input.classList.remove('is-invalid');
            feedback.textContent = '';
            feedback.style.display = 'none';
        }
    }

    function validateName(input) {
        const value = input.value.trim();
        if (value === '') {
            setError(input, input.required ? 'Name is required.' : '');
            return;
        }
        if (value.length < NAME_MIN) {
            setError(input, `Name must be at least ${NAME_MIN} characters.`);
        } else if (value.length > NAME_MAX) {
            setError(input, `Name cannot exceed ${NAME_MAX} characters.`);
        } else {
            setError(input, '');
        }
    }

    function validateEmail(input) {
        const value = input.value.trim();
        if (value === '') {
            setError(input, input.required ? 'Email is required.' : '');
            return;
        }
        setError(input, EMAIL_PATTERN.test(value) ? '' : 'Enter a valid email address.');
    }

    function validatePhone(input) {
        const digitsOnly = input.value.replace(/\D/g, '').slice(0, PHONE_LENGTH);
        if (digitsOnly !== input.value) {
            input.value = digitsOnly;
        }

        const value = input.value.trim();
        if (value === '') {
            setError(input, input.required ? 'Phone number is required.' : '');
            return;
        }
        setError(input, value.length === PHONE_LENGTH ? '' : `Phone number must be exactly ${PHONE_LENGTH} digits.`);
    }

    function isNameField(input) {
        return input.name === 'name';
    }

    function isEmailField(input) {
        return input.type === 'email';
    }

    function isPhoneField(input) {
        return PHONE_FIELD_NAMES.includes(input.name);
    }

    function handle(event) {
        const input = event.target;
        if (!input || input.tagName !== 'INPUT' || input.type === 'hidden') {
            return;
        }

        if (isNameField(input)) {
            validateName(input);
        } else if (isEmailField(input)) {
            validateEmail(input);
        } else if (isPhoneField(input)) {
            validatePhone(input);
        }
    }

    document.addEventListener('input', handle, true);
    document.addEventListener('blur', handle, true);
})();

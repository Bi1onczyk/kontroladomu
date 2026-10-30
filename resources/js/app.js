// Alpine w wersji zgodnej z CSP (bez eval, ADR-007). Komponenty rejestrujemy tutaj,
// a w Blade używamy tylko nazw: x-data="passwordToggle". Zero wyrażeń inline typu {{ ... }}.
import Alpine from '@alpinejs/csp';

import passwordToggle from './alpine/passwordToggle';
import formLock from './alpine/formLock';
import copyToClipboard from './alpine/copyToClipboard';
import resendCountdown from './alpine/resendCountdown';
import printPage from './alpine/printPage';
import turnstileGuard from './alpine/turnstileGuard';
import focusOnLoad from './alpine/focusOnLoad';
import dismissible from './alpine/dismissible';
import challengeMode from './alpine/challengeMode';
import disclosure from './alpine/disclosure';

Alpine.data('passwordToggle', passwordToggle);
Alpine.data('formLock', formLock);
Alpine.data('copyToClipboard', copyToClipboard);
Alpine.data('resendCountdown', resendCountdown);
Alpine.data('printPage', printPage);
Alpine.data('turnstileGuard', turnstileGuard);
Alpine.data('focusOnLoad', focusOnLoad);
Alpine.data('dismissible', dismissible);
Alpine.data('challengeMode', challengeMode);
Alpine.data('disclosure', disclosure);

window.Alpine = Alpine;
Alpine.start();

import {
    browserSupportsWebAuthn,
    startAuthentication,
    startRegistration,
} from '@simplewebauthn/browser';

/**
 * Passkey ceremonies.
 *
 * The browser half is deliberately thin: it asks the server for options, hands
 * them to the authenticator, and posts the result straight back. Every decision
 * about what is valid stays on the server.
 */

function csrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.content ?? '';
}

async function postJson(url, body) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(body ?? {}),
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        // Laravel returns 422 with {errors: {field: [messages]}}.
        const message = Object.values(payload.errors ?? {}).flat()[0];

        throw new Error(message ?? 'Something went wrong. Please try again.');
    }

    return payload;
}

/**
 * The user dismissing the system prompt is a normal outcome, not an error
 * worth showing. Everything else is worth surfacing.
 */
function isCancellation(error) {
    return error?.name === 'NotAllowedError' || error?.name === 'AbortError';
}

export const supported = () => browserSupportsWebAuthn();

/**
 * Create an account with a passkey. Resolves to the URL to continue to.
 */
export async function register({ optionsUrl, storeUrl, name, email }) {
    const options = await postJson(optionsUrl, { name, email });

    const passkey = await startRegistration({ optionsJSON: options });

    const { redirect } = await postJson(storeUrl, {
        passkey: JSON.stringify(passkey),
    });

    return redirect;
}

/**
 * Run a creation ceremony against options the server already handed us — the
 * Account page gets them from Livewire rather than a fetch. Resolves to the
 * credential JSON for the caller to send back.
 */
export async function createWithOptions(options) {
    return JSON.stringify(await startRegistration({ optionsJSON: options }));
}

/**
 * Sign in with a passkey. Resolves to the assertion JSON.
 */
export async function authenticate({ optionsUrl }) {
    const response = await fetch(optionsUrl, {
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
    });

    if (!response.ok) {
        throw new Error('Could not start signing in with a passkey.');
    }

    const assertion = await startAuthentication({ optionsJSON: await response.json() });

    return JSON.stringify(assertion);
}

window.passkeys = { supported, register, createWithOptions, authenticate, isCancellation };

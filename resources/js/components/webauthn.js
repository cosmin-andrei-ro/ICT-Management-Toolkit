// SPDX-License-Identifier: AGPL-3.0-or-later
// Copyright (C) 2026 ICT Management Toolkit contributors

// Security keys and passkeys (WebAuthn, ADR 0037). The Livewire component
// gives the options (data-start) and checks the answer (data-finish); this
// only translates between the server's base64url JSON and the browser API.
// Registered by name for Alpine's CSP build (ADR 0006).

const toBytes = (value) => {
    const base64 = value.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64.padEnd(Math.ceil(base64.length / 4) * 4, '=');

    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
};

const toBase64Url = (buffer) => {
    let binary = '';
    new Uint8Array(buffer).forEach((byte) => {
        binary += String.fromCharCode(byte);
    });

    return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
};

const descriptors = (list) => (list ?? []).map((descriptor) => ({ ...descriptor, id: toBytes(descriptor.id) }));

const creationOptions = (options) => ({
    ...options,
    challenge: toBytes(options.challenge),
    user: { ...options.user, id: toBytes(options.user.id) },
    excludeCredentials: descriptors(options.excludeCredentials),
    // Tells the server whether the key kept a passkey.
    extensions: { ...(options.extensions ?? {}), credProps: true },
});

const requestOptions = (options) => ({
    ...options,
    challenge: toBytes(options.challenge),
    allowCredentials: descriptors(options.allowCredentials),
});

const serialize = (credential) => {
    const answer = credential.response;
    const response = { clientDataJSON: toBase64Url(answer.clientDataJSON) };

    if (answer.attestationObject) {
        response.attestationObject = toBase64Url(answer.attestationObject);
        response.transports = typeof answer.getTransports === 'function' ? answer.getTransports() : [];
    } else {
        response.authenticatorData = toBase64Url(answer.authenticatorData);
        response.signature = toBase64Url(answer.signature);
        response.userHandle = answer.userHandle ? toBase64Url(answer.userHandle) : null;
    }

    return JSON.stringify({
        id: credential.id,
        rawId: toBase64Url(credential.rawId),
        type: credential.type,
        response,
        clientExtensionResults: credential.getClientExtensionResults(),
    });
};

export default () => ({
    busy: false,
    message: '',

    async run() {
        const { mode, start, finish, unsupported, failed } = this.$root.dataset;

        if (!window.PublicKeyCredential || !navigator.credentials) {
            this.message = unsupported;

            return;
        }

        this.busy = true;
        this.message = '';

        try {
            const options = await this.$wire[start]();

            if (!options) {
                return;
            }

            const credential = mode === 'create'
                ? await navigator.credentials.create({ publicKey: creationOptions(options) })
                : await navigator.credentials.get({ publicKey: requestOptions(options) });

            await this.$wire[finish](serialize(credential));
        } catch {
            // Cancelled, timed out, or no key for this site.
            this.message = failed;
        } finally {
            this.busy = false;
        }
    },
});

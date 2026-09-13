import {csrfToken} from '../helpers/csrf.ts';

function toBuffer(base64Url: string): ArrayBuffer {
  return Uint8Array.fromBase64(base64Url, {alphabet: 'base64url'}).buffer;
}

function toBase64Url(buffer: ArrayBuffer): string {
  return new Uint8Array(buffer).toBase64({alphabet: 'base64url', omitPadding: true});
}

// The server sends ids and challenges as base64url strings; the WebAuthn API needs ArrayBuffers.
function reviveCreation(options: Record<string, unknown>): PublicKeyCredentialCreationOptions {
  const opts = options as Record<string, any>;
  return {
    ...opts,
    challenge: toBuffer(opts.challenge),
    user: {...opts.user, id: toBuffer(opts.user.id)},
    excludeCredentials: (opts.excludeCredentials ?? []).map((c: any) => ({...c, id: toBuffer(c.id)}))
  };
}

function reviveRequest(options: Record<string, unknown>): PublicKeyCredentialRequestOptions {
  const opts = options as Record<string, any>;
  return {
    ...opts,
    challenge: toBuffer(opts.challenge),
    allowCredentials: (opts.allowCredentials ?? []).map((c: any) => ({...c, id: toBuffer(c.id)}))
  };
}

// Serialize the browser's credential back to the JSON shape webauthn-lib deserializes.
function credentialToJson(credential: PublicKeyCredential): Record<string, unknown> {
  const response = credential.response as AuthenticatorAttestationResponse & AuthenticatorAssertionResponse;
  const out: Record<string, unknown> = {
    id: credential.id,
    type: credential.type,
    rawId: toBase64Url(credential.rawId),
    clientExtensionResults: credential.getClientExtensionResults(),
    response: {clientDataJSON: toBase64Url(response.clientDataJSON)}
  };
  const body = out.response as Record<string, unknown>;

  if (response.attestationObject) body.attestationObject = toBase64Url(response.attestationObject);
  if (response.authenticatorData) body.authenticatorData = toBase64Url(response.authenticatorData);
  if (response.signature) body.signature = toBase64Url(response.signature);
  if (response.userHandle) body.userHandle = toBase64Url(response.userHandle);

  return out;
}

async function postJson(url: string, body: unknown): Promise<Response> {
  return fetch(url, {
    method: 'POST',
    headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken()},
    body: JSON.stringify(body)
  });
}

async function register(button: HTMLButtonElement): Promise<void> {
  button.disabled = true;

  try {
    const name = window.prompt('Name this passkey (e.g. "MacBook", "iPhone")', 'My device')?.trim() || 'Passkey';

    const options = await (await fetch(button.dataset.optionsUrl ?? '')).json();
    const credential = await navigator.credentials.create({publicKey: reviveCreation(options.publicKey)}) as PublicKeyCredential;

    const result = await postJson(button.dataset.registerUrl ?? '', {credential: credentialToJson(credential), name});

    if (result.ok) window.location.reload();
    else window.alert('That passkey could not be registered. Please try again.');
  } catch (error) {
    if (!(error instanceof DOMException && error.name === 'NotAllowedError')) window.alert('Passkey setup was cancelled or failed.');
  } finally {
    button.disabled = false;
  }
}

async function authenticate(button: HTMLButtonElement): Promise<void> {
  button.disabled = true;

  try {
    const options = await (await fetch(button.dataset.optionsUrl ?? '')).json();
    const credential = await navigator.credentials.get({publicKey: reviveRequest(options.publicKey)}) as PublicKeyCredential;

    const result = await postJson(button.dataset.verifyUrl ?? '', credentialToJson(credential));
    const data = result.ok ? await result.json() : null;

    if (data?.ok && typeof data.redirect === 'string') window.location.assign(data.redirect);
    else window.alert('That passkey was not accepted. Try another sign-in method.');
  } catch (error) {
    if (!(error instanceof DOMException && error.name === 'NotAllowedError')) window.alert('Passkey sign-in was cancelled or failed.');
  } finally {
    button.disabled = false;
  }
}

export const passkeyModule = {
  init(): void {
    const usable = !!window.PublicKeyCredential && !!navigator.credentials;

    if (!usable) {
      const message = window.isSecureContext
        ? "This browser can't use passkeys. Choose another method."
        : 'Passkeys need a secure (HTTPS) connection. Open this site over HTTPS to use one.';

      document.querySelectorAll<HTMLElement>('[data-passkey-unsupported]').forEach(el => {
        el.textContent = message;
        el.hidden = false;
      });
      document.querySelectorAll<HTMLElement>('[data-passkey-register], [data-passkey-login]').forEach(el => el.hidden = true);
      return;
    }

    document.querySelector<HTMLButtonElement>('[data-passkey-register]')?.addEventListener('click', event => register(event.currentTarget as HTMLButtonElement));

    const loginButton = document.querySelector<HTMLButtonElement>('[data-passkey-login]');
    if (loginButton) {
      loginButton.addEventListener('click', () => authenticate(loginButton));
      if (loginButton.hasAttribute('data-passkey-auto')) authenticate(loginButton);
    }
  }
};

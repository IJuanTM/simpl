function csrfToken(): string {
  return document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content ?? '';
}

function toBuffer(base64Url: string): ArrayBuffer {
  const base64 = base64Url.replace(/-/g, '+').replace(/_/g, '/').padEnd(Math.ceil(base64Url.length / 4) * 4, '=');
  const binary = atob(base64);
  return Uint8Array.from(binary, char => char.charCodeAt(0)).buffer;
}

function toBase64Url(buffer: ArrayBuffer): string {
  const binary = String.fromCharCode(...new Uint8Array(buffer));
  return btoa(binary).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, '');
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

    if (result.ok) window.location.assign('/user/settings/security');
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
    const data = await result.json();

    if (data.ok && typeof data.redirect === 'string') window.location.assign(data.redirect);
    else window.alert('That passkey was not accepted. Try another sign-in method.');
  } catch (error) {
    if (!(error instanceof DOMException && error.name === 'NotAllowedError')) window.alert('Passkey sign-in was cancelled or failed.');
  } finally {
    button.disabled = false;
  }
}

export const passkeyModule = {
  init(): void {
    if (!window.PublicKeyCredential) {
      document.querySelectorAll<HTMLElement>('[data-passkey-unsupported]').forEach(el => el.hidden = false);
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

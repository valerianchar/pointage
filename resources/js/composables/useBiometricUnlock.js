import { onMounted, ref } from 'vue';

const CREDENTIAL_STORAGE_KEY = 'pointage.biometric-credential-id';
const CHALLENGE_BYTES = 32;

function randomBytes(length) {
    return crypto.getRandomValues(new Uint8Array(length));
}

function toBase64Url(buffer) {
    return btoa(String.fromCharCode(...new Uint8Array(buffer)))
        .replaceAll('+', '-')
        .replaceAll('/', '_')
        .replaceAll('=', '');
}

function fromBase64Url(value) {
    const padded = value.replaceAll('-', '+').replaceAll('_', '/');

    return Uint8Array.from(atob(padded), (character) => character.charCodeAt(0));
}

/**
 * Confirmation biométrique locale (Face ID / Touch ID) pour lever le verrou.
 *
 * L'identité reste portée par la session déjà authentifiée : la biométrie joue le
 * même rôle que le verrouillage d'écran d'un téléphone, celui d'une confirmation
 * sur l'appareil. Aucun secret ne circule, et le premier usage enregistre une clé
 * propre à l'appareil, conservée localement.
 */
export function useBiometricUnlock(accountName) {
    const isSupported = ref(false);
    const isConfirming = ref(false);
    const error = ref('');

    onMounted(async () => {
        isSupported.value = await hasPlatformAuthenticator();
    });

    async function hasPlatformAuthenticator() {
        if (!window.PublicKeyCredential?.isUserVerifyingPlatformAuthenticatorAvailable) {
            return false;
        }

        try {
            return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
        } catch {
            return false;
        }
    }

    async function enrollThisDevice() {
        const credential = await navigator.credentials.create({
            publicKey: {
                challenge: randomBytes(CHALLENGE_BYTES),
                rp: { name: 'Pointage' },
                user: { id: randomBytes(16), name: accountName, displayName: accountName },
                pubKeyCredParams: [
                    { type: 'public-key', alg: -7 },
                    { type: 'public-key', alg: -257 },
                ],
                authenticatorSelection: {
                    authenticatorAttachment: 'platform',
                    userVerification: 'required',
                    residentKey: 'preferred',
                },
                timeout: 60_000,
            },
        });

        localStorage.setItem(CREDENTIAL_STORAGE_KEY, toBase64Url(credential.rawId));
    }

    async function confirmWithEnrolledDevice(credentialId) {
        await navigator.credentials.get({
            publicKey: {
                challenge: randomBytes(CHALLENGE_BYTES),
                allowCredentials: [{ type: 'public-key', id: fromBase64Url(credentialId) }],
                userVerification: 'required',
                timeout: 60_000,
            },
        });
    }

    async function confirm() {
        error.value = '';
        isConfirming.value = true;

        try {
            const enrolledCredentialId = localStorage.getItem(CREDENTIAL_STORAGE_KEY);

            if (enrolledCredentialId === null) {
                await enrollThisDevice();
            } else {
                await confirmWithEnrolledDevice(enrolledCredentialId);
            }

            return true;
        } catch (failure) {
            // Une clé effacée côté appareil doit pouvoir être réenregistrée.
            if (failure.name === 'InvalidStateError' || failure.name === 'NotFoundError') {
                localStorage.removeItem(CREDENTIAL_STORAGE_KEY);
            }

            error.value =
                failure.name === 'NotAllowedError'
                    ? 'Confirmation annulée.'
                    : "La biométrie n'est pas disponible sur cet appareil.";

            return false;
        } finally {
            isConfirming.value = false;
        }
    }

    return { isSupported, isConfirming, error, confirm };
}

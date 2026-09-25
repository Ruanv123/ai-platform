import { useCallback, useState } from 'react';
import { Button } from '@/components/ui/button';

type FacebookLoginResponse = {
    authResponse?: { code?: string };
};

type FacebookSdk = {
    init: (options: { appId: string; version: string }) => void;
    login: (
        callback: (response: FacebookLoginResponse) => void,
        options: {
            config_id: string;
            response_type: 'code';
            override_default_response_type: true;
        },
    ) => void;
};

type ConnectionStatus = 'idle' | 'authorizing' | 'connecting';

type Props = {
    appId: string | null;
    configId: string | null;
    graphApiVersion: string;
    status: ConnectionStatus;
    onStatusChange: (status: ConnectionStatus) => void;
    onAuthorization: (code: string) => void;
};

let facebookInitialization: {
    appId: string;
    version: string;
    promise: Promise<FacebookSdk>;
} | null = null;

function facebookSdk(): FacebookSdk | null {
    return (window as Window & { FB?: FacebookSdk }).FB ?? null;
}

function loadFacebookSdk(): Promise<FacebookSdk> {
    const loadedSdk = facebookSdk();

    if (loadedSdk !== null) {
        return Promise.resolve(loadedSdk);
    }

    return new Promise((resolve, reject) => {
        const currentScript = document.getElementById('facebook-jssdk');
        const script =
            currentScript instanceof HTMLScriptElement
                ? currentScript
                : document.createElement('script');
        const handleLoad = () => {
            const sdk = facebookSdk();

            if (sdk === null) {
                reject(new Error('SDK unavailable'));

                return;
            }

            resolve(sdk);
        };

        script.addEventListener('load', handleLoad, { once: true });
        script.addEventListener(
            'error',
            () => {
                script.remove();
                reject(new Error('SDK unavailable'));
            },
            { once: true },
        );

        if (currentScript === null) {
            script.id = 'facebook-jssdk';
            script.async = true;
            script.defer = true;
            script.src = 'https://connect.facebook.net/pt_BR/sdk.js';
            document.head.append(script);
        }
    });
}

function initializeFacebookSdk(
    appId: string,
    version: string,
): Promise<FacebookSdk> {
    if (
        facebookInitialization?.appId === appId &&
        facebookInitialization.version === version
    ) {
        return facebookInitialization.promise;
    }

    const promise = loadFacebookSdk().then((sdk) => {
        sdk.init({ appId, version });

        return sdk;
    });
    facebookInitialization = { appId, version, promise };
    void promise.catch(() => {
        if (facebookInitialization?.promise === promise) {
            facebookInitialization = null;
        }
    });

    return promise;
}

export default function WhatsAppMetaSignupButton({
    appId,
    configId,
    graphApiVersion,
    status,
    onStatusChange,
    onAuthorization,
}: Props) {
    const [sdkLoading, setSdkLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const isConfigured = appId !== null && configId !== null;

    const preloadSdkRef = useCallback(
        (node: HTMLDivElement | null) => {
            if (node !== null && appId !== null && configId !== null) {
                void initializeFacebookSdk(appId, graphApiVersion).catch(
                    () => undefined,
                );
            }
        },
        [appId, configId, graphApiVersion],
    );

    const connect = () => {
        if (appId === null || configId === null) {
            return;
        }

        setError(null);
        onStatusChange('authorizing');

        const beginLogin = (sdk: FacebookSdk) => {
            sdk.login(
                (response) => {
                    const code = response.authResponse?.code;

                    if (!code) {
                        onStatusChange('idle');
                        setError(
                            'A autorização foi cancelada. Tente novamente.',
                        );

                        return;
                    }

                    onStatusChange('connecting');
                    onAuthorization(code);
                },
                {
                    config_id: configId,
                    response_type: 'code',
                    override_default_response_type: true,
                },
            );
        };

        const sdk = facebookSdk();

        if (
            sdk !== null &&
            facebookInitialization?.appId === appId &&
            facebookInitialization.version === graphApiVersion
        ) {
            beginLogin(sdk);

            return;
        }

        setSdkLoading(true);
        void initializeFacebookSdk(appId, graphApiVersion)
            .then(beginLogin)
            .catch(() => {
                onStatusChange('idle');
                setError(
                    'Não foi possível abrir a janela da Meta. Tente novamente.',
                );
            })
            .finally(() => setSdkLoading(false));
    };

    const isBusy = sdkLoading || status !== 'idle';
    const buttonLabel =
        status === 'connecting'
            ? 'Finalizando conexão...'
            : status === 'authorizing' || sdkLoading
              ? 'Aguardando autorização...'
              : 'Conectar com a Meta';

    return (
        <div ref={preloadSdkRef} className="grid justify-items-start gap-3">
            <Button
                type="button"
                onClick={connect}
                disabled={!isConfigured || isBusy}
            >
                {buttonLabel}
            </Button>
            {isBusy && (
                <p className="text-muted-foreground text-sm" role="status">
                    {status === 'connecting'
                        ? 'Autorização recebida. Ativando o número e sincronizando Templates.'
                        : 'Conclua as etapas na janela segura da Meta.'}
                </p>
            )}
            {!isConfigured && (
                <p className="text-muted-foreground text-sm">
                    O cadastro incorporado da Meta ainda não está configurado
                    nesta plataforma.
                </p>
            )}
            {error !== null && (
                <p className="text-destructive text-sm" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

import { createElement, type AnchorHTMLAttributes, type ReactNode } from 'react';
import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vitest';
import { cleanup, fireEvent, render, screen, waitFor } from '@testing-library/react';
import WhatsAppProfilePhotoBlock from '@/components/channels/whatsapp-profile-photo-block';

const { post } = vi.hoisted(() => ({ post: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: AnchorHTMLAttributes<HTMLAnchorElement> & {
        href: string;
        children: ReactNode;
    }) => createElement('a', { href, ...props }, children),
    router: { post },
}));

vi.mock('react-easy-crop', () => ({
    default: ({
        image,
        onCropComplete,
    }: {
        image: string;
        onCropComplete: (
            area: unknown,
            areaPixels: { x: number; y: number; width: number; height: number },
        ) => void;
    }) =>
        createElement(
            'div',
            null,
            createElement('img', { src: image, alt: 'Foto para recortar' }),
            createElement(
                'button',
                {
                    type: 'button',
                    onClick: () =>
                        onCropComplete(null, {
                            x: 10,
                            y: 20,
                            width: 120,
                            height: 120,
                        }),
                },
                'Ajustar recorte',
            ),
        ),
}));

const defaultProps = {
    profilePictureUrl: 'https://example.test/profile.jpg',
    appId: '123456789012345',
    requiresAppId: true,
    connectionUrl: '/channels/whatsapp?tab=conexao',
    updateProfilePictureUrl: '/channels/whatsapp/channel-1/profile/photo',
    canEdit: true,
};

let canvasSize = { width: 0, height: 0 };

beforeEach(() => {
    post.mockClear();
    canvasSize = { width: 0, height: 0 };

    Object.defineProperty(URL, 'createObjectURL', {
        configurable: true,
        value: vi.fn(() => 'blob:whatsapp-photo'),
    });
    Object.defineProperty(URL, 'revokeObjectURL', {
        configurable: true,
        value: vi.fn(),
    });
    Object.defineProperty(HTMLCanvasElement.prototype, 'getContext', {
        configurable: true,
        value: vi.fn(function (this: HTMLCanvasElement) {
            canvasSize = { width: this.width, height: this.height };

            return { drawImage: vi.fn() };
        }),
    });
    Object.defineProperty(HTMLCanvasElement.prototype, 'toBlob', {
        configurable: true,
        value: vi.fn((callback: BlobCallback) =>
            callback(new Blob(['cropped photo'], { type: 'image/jpeg' })),
        ),
    });

    class LoadedImage {
        onload: (() => void) | null = null;
        onerror: (() => void) | null = null;

        set src(_value: string) {
            queueMicrotask(() => this.onload?.());
        }
    }

    vi.stubGlobal('Image', LoadedImage);
});

afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
});

describe('WhatsAppProfilePhotoBlock', () => {
    it('lets an Owner choose, crop, preview, and submit a 640-pixel JPEG', async () => {
        render(<WhatsAppProfilePhotoBlock {...defaultProps} />);

        fireEvent.change(screen.getByLabelText('Escolher foto'), {
            target: {
                files: [new File(['original'], 'logo.png', { type: 'image/png' })],
            },
        });
        fireEvent.click(await screen.findByRole('button', { name: 'Ajustar recorte' }));
        fireEvent.click(screen.getByRole('button', { name: 'Pré-visualizar' }));

        expect(await screen.findByAltText('Prévia da foto recortada')).toBeInTheDocument();
        expect(canvasSize).toEqual({ width: 640, height: 640 });

        fireEvent.click(screen.getByRole('button', { name: 'Confirmar foto' }));

        await waitFor(() =>
            expect(post).toHaveBeenCalledWith(
                '/channels/whatsapp/channel-1/profile/photo',
                { photo: expect.any(File) },
                expect.objectContaining({
                    forceFormData: true,
                    preserveScroll: true,
                }),
            ),
        );
        const submittedPhoto = post.mock.calls[0]?.[1].photo as File;

        expect(submittedPhoto.type).toBe('image/jpeg');
        expect(submittedPhoto.name).toBe('whatsapp-profile.jpg');
    });

    it('rejects a file that is not JPEG or PNG before opening the crop dialog', () => {
        render(<WhatsAppProfilePhotoBlock {...defaultProps} />);

        fireEvent.change(screen.getByLabelText('Escolher foto'), {
            target: {
                files: [new File(['image'], 'logo.gif', { type: 'image/gif' })],
            },
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'Escolha uma imagem JPEG ou PNG.',
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('rejects a photo larger than 5 MB before opening the crop dialog', () => {
        render(<WhatsAppProfilePhotoBlock {...defaultProps} />);

        fireEvent.change(screen.getByLabelText('Escolher foto'), {
            target: {
                files: [
                    new File(
                        [new Uint8Array(5 * 1024 * 1024 + 1)],
                        'large.jpg',
                        { type: 'image/jpeg' },
                    ),
                ],
            },
        });

        expect(screen.getByRole('alert')).toHaveTextContent(
            'A imagem não pode ultrapassar 5 MB.',
        );
        expect(screen.queryByRole('dialog')).not.toBeInTheDocument();
    });

    it('shows the connection link and hides upload controls when Meta has no App ID', () => {
        render(
            <WhatsAppProfilePhotoBlock
                {...defaultProps}
                appId={null}
                updateProfilePictureUrl={null}
            />,
        );

        expect(screen.getByRole('status')).toHaveTextContent(
            'Informe o App ID da Meta para trocar a foto.',
        );
        expect(screen.getByRole('link', { name: 'Ir para Conexão' })).toHaveAttribute(
            'href',
            '/channels/whatsapp?tab=conexao',
        );
        expect(screen.queryByRole('button', { name: 'Trocar foto' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Escolher foto')).not.toBeInTheDocument();
    });

    it('hides photo actions from a Member', () => {
        render(<WhatsAppProfilePhotoBlock {...defaultProps} canEdit={false} />);

        expect(screen.getByAltText('Foto do Perfil do WhatsApp')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Trocar foto' })).not.toBeInTheDocument();
        expect(screen.queryByLabelText('Escolher foto')).not.toBeInTheDocument();
    });

    it('does not show the Meta App ID warning for another provider', () => {
        render(
            <WhatsAppProfilePhotoBlock
                {...defaultProps}
                appId={null}
                requiresAppId={false}
                updateProfilePictureUrl="/channels/whatsapp-test/channel-1/profile/photo"
            />,
        );

        expect(screen.queryByRole('status')).not.toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Trocar foto' })).toBeInTheDocument();
    });
});

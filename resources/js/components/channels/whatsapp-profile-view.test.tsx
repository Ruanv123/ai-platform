import { createElement, type AnchorHTMLAttributes, type ReactNode } from 'react';
import { fireEvent, render, screen } from '@testing-library/react';
import { afterEach, describe, expect, it, vi } from 'vitest';
import WhatsAppProfileView from '@/components/channels/whatsapp-profile-view';
import type {
    WhatsAppProfileCapabilities,
    WhatsAppProfileResult,
} from '@/components/channels/whatsapp-profile-types';

const { reload } = vi.hoisted(() => ({ reload: vi.fn() }));

vi.mock('@inertiajs/react', () => ({
    Link: ({
        href,
        children,
        ...props
    }: AnchorHTMLAttributes<HTMLAnchorElement> & {
        href: string;
        children: ReactNode;
    }) => createElement('a', { href, ...props }, children),
    router: { reload },
    useForm: (data: Record<string, unknown>) => ({
        data,
        errors: {},
        processing: false,
        setData: () => undefined,
        transform: () => ({ post: () => undefined }),
    }),
}));

const capabilities: WhatsAppProfileCapabilities = {
    readableFields: ['profilePicture', 'displayName', 'about', 'email'],
    editableFields: [],
    canRequestDisplayName: false,
};

const profile: WhatsAppProfileResult = {
    state: 'ready',
    data: {
        profilePictureUrl: 'https://example.test/profile.jpg',
        displayName: 'Loja Aurora',
        nameStatus: 'APPROVED',
        newDisplayName: null,
        newNameStatus: null,
        about: 'Atendimento de segunda a sexta',
        description: 'Descrição que não deve aparecer',
        address: 'Rua das Flores, 10',
        email: 'contato@example.test',
        websites: ['https://example.test'],
        category: 'RETAIL',
    },
};

afterEach(() => {
    reload.mockClear();
});

describe('WhatsAppProfileView', () => {
    it('renders only the readable fields declared by the provider', () => {
        render(
            <WhatsAppProfileView
                profile={profile}
                capabilities={capabilities}
                connectionUrl="/channels/whatsapp?tab=conexao"
                channelId={null}
                canEdit={false}
                categories={[]}
            />,
        );

        expect(screen.getByText('Nome de exibição')).toBeInTheDocument();
        expect(screen.getByText('Recado')).toBeInTheDocument();
        expect(screen.getByText('E-mail')).toBeInTheDocument();
        expect(screen.queryByText('Descrição')).not.toBeInTheDocument();
        expect(screen.queryByText('Endereço')).not.toBeInTheDocument();
        expect(screen.queryByText('Sites')).not.toBeInTheDocument();
        expect(screen.queryByText('Categoria')).not.toBeInTheDocument();
    });

    it('shows the connection link when the channel is not connected', () => {
        render(
            <WhatsAppProfileView
                profile={{ state: 'not_connected' }}
                capabilities={capabilities}
                connectionUrl="/channels/whatsapp?tab=conexao"
                channelId={null}
                canEdit={false}
                categories={[]}
            />,
        );

        expect(
            screen.getByText(
                'Conecte o Canal para consultar como a Empresa aparece no WhatsApp.',
            ),
        ).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Ir para Conexão' })).toHaveAttribute(
            'href',
            '/channels/whatsapp?tab=conexao',
        );
    });

    it('shows the provider error and reloads only the profile when retried', () => {
        render(
            <WhatsAppProfileView
                profile={{ state: 'error', message: 'A Meta recusou a consulta.' }}
                capabilities={capabilities}
                connectionUrl="/channels/whatsapp?tab=conexao"
                channelId={null}
                canEdit={false}
                categories={[]}
            />,
        );

        expect(screen.getByRole('alert')).toHaveTextContent(
            'A Meta recusou a consulta.',
        );
        fireEvent.click(screen.getByRole('button', { name: 'Tentar de novo' }));

        expect(reload).toHaveBeenCalledWith({ only: ['whatsappProfile'] });
    });

    it('shows editable text fields as disabled to Members without a save button', () => {
        render(
            <WhatsAppProfileView
                profile={profile}
                capabilities={{
                    ...capabilities,
                    editableFields: [
                        'about',
                        'description',
                        'address',
                        'email',
                        'websites',
                        'category',
                    ],
                }}
                connectionUrl="/channels/whatsapp?tab=conexao"
                channelId="channel-1"
                canEdit={false}
                categories={[{ value: 'RETAIL', label: 'Varejo' }]}
            />,
        );

        expect(screen.getByLabelText('Recado')).toBeDisabled();
        expect(screen.getByLabelText('Descrição')).toBeDisabled();
        expect(screen.getByLabelText('Endereço')).toBeDisabled();
        expect(screen.getByLabelText('E-mail')).toBeDisabled();
        expect(screen.getByLabelText('Site 1')).toBeDisabled();
        expect(screen.getByRole('combobox', { name: 'Categoria' })).toBeDisabled();
        expect(screen.queryByRole('button', { name: 'Salvar' })).not.toBeInTheDocument();
    });
});

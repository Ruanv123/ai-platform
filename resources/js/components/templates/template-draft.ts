import { fillVariables, variablePositions } from './whatsapp-text';

export type TemplateCategory = 'utility' | 'marketing' | 'authentication';

export type TemplateFormat =
    | 'standard'
    | 'carousel'
    | 'coupon'
    | 'limited_time_offer'
    | 'call_permission'
    | 'authentication';

export type HeaderType = 'NONE' | 'TEXT' | 'IMAGE' | 'VIDEO' | 'DOCUMENT';

export type ButtonType = 'quick_reply' | 'url' | 'phone_number' | 'copy_code';

export type CardButtonType = 'quick_reply' | 'url' | 'phone_number';

export type OtpType = 'copy_code' | 'one_tap' | 'zero_tap';

export type ButtonDraft = {
    key: string;
    type: ButtonType;
    text: string;
    url: string;
    phoneNumber: string;
    example: string;
};

export type CardButtonDraft = {
    text: string;
    url: string;
    phoneNumber: string;
    example: string;
};

export type CardDraft = {
    key: string;
    existingIndex: number | null;
    media: File | null;
    existingMediaName: string | null;
    body: string;
    bodyExamples: Record<number, string>;
    buttons: CardButtonDraft[];
};

export type TemplateDraft = {
    accountId: string;
    name: string;
    language: string;
    category: TemplateCategory;
    format: TemplateFormat;
    headerType: HeaderType;
    headerText: string;
    headerMedia: File | null;
    existingHeaderMediaName: string | null;
    existingHeaderType: HeaderType | null;
    body: string;
    headerExamples: Record<number, string>;
    bodyExamples: Record<number, string>;
    headerDescriptions: Record<number, string>;
    bodyDescriptions: Record<number, string>;
    libraryKey: string | null;
    footer: string;
    buttons: ButtonDraft[];
    couponCode: string;
    couponQuickReply: string;
    offerText: string;
    offerHasExpiration: boolean;
    offerCode: string;
    offerUrlText: string;
    offerUrl: string;
    offerUrlExample: string;
    cardHeaderFormat: 'IMAGE' | 'VIDEO';
    cardsHaveBody: boolean;
    cardButtonTypes: CardButtonType[];
    cards: CardDraft[];
    otpType: OtpType;
    packageName: string;
    signatureHash: string;
    addSecurityRecommendation: boolean;
    expirationEnabled: boolean;
    codeExpirationMinutes: string;
};

export type SavedTemplateButton = {
    type: string;
    text?: string;
    url?: string;
    phone_number?: string;
    example?: string;
};

export type SavedTemplate = {
    id: number;
    name: string;
    language: string;
    format: TemplateFormat;
    requestedCategory: TemplateCategory;
    accountId: number;
    body: string;
    headerType: string | null;
    headerText: string | null;
    headerMediaName: string | null;
    footer: string | null;
    buttons: SavedTemplateButton[];
    variables: {
        position: number;
        component: string;
        example: string | null;
        description?: string;
        buttonIndex?: number;
    }[];
    offer: { text: string; hasExpiration: boolean } | null;
    cards: {
        headerFormat: string | null;
        mediaAvailable: boolean;
        mediaName: string | null;
        body: string | null;
        bodyExamples: (string | null)[];
        buttons: {
            type: string;
            text: string | null;
            url: string | null;
            phoneNumber: string | null;
            example: string | null;
        }[];
    }[];
    authentication: {
        otpType: OtpType;
        packageName: string;
        signatureHash: string;
        addSecurityRecommendation: boolean;
        codeExpirationMinutes: number | null;
    } | null;
};

export type PreviewButton = {
    text: string;
    type: ButtonType;
};

export const categoryOptions: {
    value: TemplateCategory;
    label: string;
    description: string;
}[] = [
    {
        value: 'utility',
        label: 'Utilidade',
        description:
            'Confirmações, lembretes e atualizações sobre algo que o Contato já pediu.',
    },
    {
        value: 'marketing',
        label: 'Marketing',
        description:
            'Ofertas, novidades, convites e mensagens de reengajamento.',
    },
    {
        value: 'authentication',
        label: 'Autenticação',
        description: 'Códigos de verificação de uso único.',
    },
];

export const formatCatalog: Record<
    TemplateFormat,
    { label: string; description: string }
> = {
    standard: {
        label: 'Personalizado',
        description: 'Texto ou mídia, com até 3 botões.',
    },
    carousel: {
        label: 'Carrossel de mídia',
        description:
            'De 2 a 10 cards com imagem ou vídeo, deslizando na horizontal.',
    },
    coupon: {
        label: 'Cupom de desconto',
        description: 'Um botão que copia o código do cupom.',
    },
    limited_time_offer: {
        label: 'Oferta por tempo limitado',
        description: 'Destaque da oferta com contagem regressiva e código.',
    },
    call_permission: {
        label: 'Pedido de ligação',
        description: 'Pede permissão para a empresa ligar para o Contato.',
    },
    authentication: {
        label: 'Código de verificação',
        description: 'Senha de uso único com texto definido pela Meta.',
    },
};

export const catalogOnlyFormats = [
    'Catálogo',
    'Carrossel de produtos',
    'Vários produtos',
    'Produto único',
];

export const buttonTypeLabels: Record<ButtonType, string> = {
    quick_reply: 'Resposta rápida',
    url: 'URL',
    phone_number: 'Telefone',
    copy_code: 'Copiar código',
};

export function formatsForCategory(
    category: TemplateCategory,
): TemplateFormat[] {
    if (category === 'marketing') {
        return [
            'standard',
            'carousel',
            'coupon',
            'limited_time_offer',
            'call_permission',
        ];
    }

    if (category === 'authentication') {
        return ['authentication'];
    }

    return ['standard', 'call_permission'];
}

export function headerTypesForFormat(format: TemplateFormat): HeaderType[] {
    if (format === 'standard') {
        return ['NONE', 'TEXT', 'IMAGE', 'VIDEO', 'DOCUMENT'];
    }

    if (format === 'coupon') {
        return ['NONE', 'TEXT'];
    }

    if (format === 'limited_time_offer') {
        return ['NONE', 'IMAGE', 'VIDEO'];
    }

    return [];
}

export function defaultHeaderType(format: TemplateFormat): HeaderType {
    if (format === 'standard' || format === 'coupon') {
        return 'TEXT';
    }

    return 'NONE';
}

export function newKey(): string {
    return crypto.randomUUID();
}

export function emptyButton(type: ButtonType = 'quick_reply'): ButtonDraft {
    return {
        key: newKey(),
        type,
        text: '',
        url: '',
        phoneNumber: '',
        example: '',
    };
}

export function emptyCard(buttonCount: number): CardDraft {
    return {
        key: newKey(),
        existingIndex: null,
        media: null,
        existingMediaName: null,
        body: '',
        bodyExamples: {},
        buttons: Array.from({ length: buttonCount }, () => ({
            text: '',
            url: '',
            phoneNumber: '',
            example: '',
        })),
    };
}

function examplesFor(
    template: SavedTemplate,
    component: string,
): Record<number, string> {
    return Object.fromEntries(
        template.variables
            .filter((variable) => variable.component === component)
            .map((variable) => [variable.position, variable.example ?? '']),
    );
}

function descriptionsFor(
    template: SavedTemplate,
    component: string,
): Record<number, string> {
    return Object.fromEntries(
        template.variables
            .filter((variable) => variable.component === component)
            .map((variable) => [variable.position, variable.description ?? '']),
    );
}

function savedButtonType(type: string): ButtonType {
    const normalized = type.toLowerCase();

    return normalized === 'url' ||
        normalized === 'phone_number' ||
        normalized === 'copy_code'
        ? normalized
        : 'quick_reply';
}

export function initialDraft(
    template: SavedTemplate | null,
    defaultAccountId: number | undefined,
): TemplateDraft {
    const cards = template?.cards ?? [];
    const firstCard = cards[0];
    const buttons = (template?.buttons ?? []).filter(
        (button) => button.type !== 'OTP',
    );
    const copyCode = buttons.find((button) => button.type === 'COPY_CODE');
    const quickReply = buttons.find((button) => button.type === 'QUICK_REPLY');
    const urlButton = buttons.find((button) => button.type === 'URL');
    const savedHeader = (template?.headerType ?? null) as HeaderType | null;
    const format = template?.format ?? 'standard';

    return {
        accountId: String(template?.accountId ?? defaultAccountId ?? ''),
        name: template?.name ?? '',
        language: template?.language ?? 'pt_BR',
        category: template?.requestedCategory ?? 'utility',
        format,
        headerType: savedHeader ?? (template ? 'NONE' : 'TEXT'),
        headerText: template?.headerText ?? '',
        headerMedia: null,
        existingHeaderMediaName: template?.headerMediaName ?? null,
        existingHeaderType: savedHeader,
        body: template?.body ?? '',
        headerExamples: template ? examplesFor(template, 'header') : {},
        bodyExamples: template ? examplesFor(template, 'body') : {},
        headerDescriptions: template ? descriptionsFor(template, 'header') : {},
        bodyDescriptions: template ? descriptionsFor(template, 'body') : {},
        libraryKey: null,
        footer: template?.footer ?? '',
        buttons:
            format === 'standard'
                ? buttons.map((button) => ({
                      key: newKey(),
                      type: savedButtonType(button.type),
                      text: button.text ?? '',
                      url: button.url ?? '',
                      phoneNumber: button.phone_number ?? '',
                      example: button.example ?? '',
                  }))
                : [],
        couponCode: format === 'coupon' ? (copyCode?.example ?? '') : '',
        couponQuickReply: quickReply?.text ?? '',
        offerText: template?.offer?.text ?? '',
        offerHasExpiration: template?.offer?.hasExpiration ?? true,
        offerCode:
            format === 'limited_time_offer' ? (copyCode?.example ?? '') : '',
        offerUrlText: urlButton?.text ?? '',
        offerUrl: urlButton?.url ?? '',
        offerUrlExample: urlButton?.example ?? '',
        cardHeaderFormat:
            firstCard?.headerFormat === 'VIDEO' ? 'VIDEO' : 'IMAGE',
        cardsHaveBody: firstCard ? firstCard.body !== null : true,
        cardButtonTypes: firstCard
            ? firstCard.buttons.map(
                  (button) => savedButtonType(button.type) as CardButtonType,
              )
            : ['url', 'quick_reply'],
        cards:
            cards.length > 0
                ? cards.map((card, index) => ({
                      key: newKey(),
                      existingIndex: card.mediaAvailable ? index : null,
                      media: null,
                      existingMediaName: card.mediaAvailable
                          ? (card.mediaName ?? 'Mídia atual')
                          : null,
                      body: card.body ?? '',
                      bodyExamples: Object.fromEntries(
                          card.bodyExamples.map((example, index) => [
                              index + 1,
                              example ?? '',
                          ]),
                      ),
                      buttons: card.buttons.map((button) => ({
                          text: button.text ?? '',
                          url: button.url ?? '',
                          phoneNumber: button.phoneNumber ?? '',
                          example: button.example ?? '',
                      })),
                  }))
                : [emptyCard(2), emptyCard(2)],
        otpType: template?.authentication?.otpType ?? 'copy_code',
        packageName: template?.authentication?.packageName ?? '',
        signatureHash: template?.authentication?.signatureHash ?? '',
        addSecurityRecommendation:
            template?.authentication?.addSecurityRecommendation ?? true,
        expirationEnabled: template
            ? template.authentication?.codeExpirationMinutes !== null
            : true,
        codeExpirationMinutes: String(
            template?.authentication?.codeExpirationMinutes ?? 10,
        ),
    };
}

function withoutEmpty(
    values: Record<string, string | undefined>,
): Record<string, string> {
    return Object.fromEntries(
        Object.entries(values).filter(
            (entry): entry is [string, string] =>
                entry[1] !== undefined && entry[1] !== '',
        ),
    );
}

function standardButtonPayload(button: ButtonDraft) {
    if (button.type === 'copy_code') {
        return { type: 'copy_code', example: button.example };
    }

    if (button.type === 'url') {
        return withoutEmpty({
            type: 'url',
            text: button.text,
            url: button.url,
            example: button.example,
        });
    }

    if (button.type === 'phone_number') {
        return {
            type: 'phone_number',
            text: button.text,
            phone_number: button.phoneNumber,
        };
    }

    return { type: 'quick_reply', text: button.text };
}

function headerPayload(draft: TemplateDraft) {
    const header: Record<string, unknown> = { header_type: draft.headerType };

    if (draft.headerType === 'TEXT') {
        header.header_text = draft.headerText;
    } else {
        header.header_text = '';
    }

    if (draft.headerMedia && draft.headerType !== 'TEXT') {
        header.header_media = draft.headerMedia;
    }

    return header;
}

function variablesPayload(draft: TemplateDraft) {
    const headerVariables =
        draft.headerType === 'TEXT'
            ? variablePositions(draft.headerText).map((position) => ({
                  position,
                  component: 'header',
                  example: draft.headerExamples[position] ?? '',
                  description: draft.headerDescriptions[position] ?? '',
              }))
            : [];
    const bodyVariables = variablePositions(draft.body).map((position) => ({
        position,
        component: 'body',
        example: draft.bodyExamples[position] ?? '',
        description: draft.bodyDescriptions[position] ?? '',
    }));

    return [...headerVariables, ...bodyVariables];
}

export function buildPayload(
    draft: TemplateDraft,
    submit: boolean,
): Record<string, unknown> {
    const base = {
        whatsapp_account_id: draft.accountId,
        name: draft.name,
        language: draft.language,
        requested_category: draft.category,
        format: draft.format,
        submit,
        ...(draft.libraryKey ? { library_key: draft.libraryKey } : {}),
    };

    if (draft.format === 'authentication') {
        return {
            ...base,
            otp_type: draft.otpType,
            package_name:
                draft.otpType === 'copy_code' ? '' : draft.packageName,
            signature_hash:
                draft.otpType === 'copy_code' ? '' : draft.signatureHash,
            add_security_recommendation: draft.addSecurityRecommendation,
            code_expiration_minutes: draft.expirationEnabled
                ? draft.codeExpirationMinutes
                : '',
        };
    }

    const message = {
        ...base,
        body: draft.body,
        variables: variablesPayload(draft),
    };

    if (draft.format === 'call_permission') {
        return { ...message, header_type: 'NONE' };
    }

    if (draft.format === 'carousel') {
        return {
            ...message,
            header_type: 'NONE',
            card_header_format: draft.cardHeaderFormat,
            cards: draft.cards.map((card) => ({
                body: draft.cardsHaveBody ? card.body : '',
                body_examples: variablePositions(card.body).map(
                    (position) => card.bodyExamples[position] ?? '',
                ),
                ...(card.media ? { media: card.media } : {}),
                ...(!card.media && card.existingIndex !== null
                    ? { existing_index: card.existingIndex }
                    : {}),
                buttons: draft.cardButtonTypes.map((type, index) =>
                    withoutEmpty({
                        type,
                        text: card.buttons[index]?.text ?? '',
                        url:
                            type === 'url'
                                ? card.buttons[index]?.url
                                : undefined,
                        phone_number:
                            type === 'phone_number'
                                ? card.buttons[index]?.phoneNumber
                                : undefined,
                        example:
                            type === 'url'
                                ? card.buttons[index]?.example
                                : undefined,
                    }),
                ),
            })),
        };
    }

    if (draft.format === 'coupon') {
        return {
            ...message,
            ...headerPayload(draft),
            buttons: [
                { type: 'copy_code', example: draft.couponCode },
                ...(draft.couponQuickReply.trim() !== ''
                    ? [{ type: 'quick_reply', text: draft.couponQuickReply }]
                    : []),
            ],
        };
    }

    if (draft.format === 'limited_time_offer') {
        return {
            ...message,
            ...headerPayload(draft),
            offer_text: draft.offerText,
            offer_has_expiration: draft.offerHasExpiration,
            buttons: [
                { type: 'copy_code', example: draft.offerCode },
                withoutEmpty({
                    type: 'url',
                    text: draft.offerUrlText,
                    url: draft.offerUrl,
                    example: draft.offerUrlExample,
                }),
            ],
        };
    }

    return {
        ...message,
        ...headerPayload(draft),
        footer: draft.footer,
        buttons: draft.buttons.map(standardButtonPayload),
    };
}

function hasMissingExamples(
    text: string,
    examples: Record<number, string>,
): boolean {
    return variablePositions(text).some(
        (position) => (examples[position] ?? '').trim() === '',
    );
}

export function draftIssue(draft: TemplateDraft): string | null {
    if (draft.format === 'authentication') {
        if (
            draft.otpType !== 'copy_code' &&
            (draft.packageName.trim() === '' ||
                draft.signatureHash.trim() === '')
        ) {
            return 'Informe o pacote e o hash do app Android.';
        }

        return null;
    }

    if (draft.body.trim() === '') {
        return 'Escreva a mensagem para enviar.';
    }

    const headerMissing =
        draft.headerType === 'TEXT' &&
        hasMissingExamples(draft.headerText, draft.headerExamples);

    if (headerMissing || hasMissingExamples(draft.body, draft.bodyExamples)) {
        return 'Falta o exemplo de alguma variável.';
    }

    if (
        ['IMAGE', 'VIDEO', 'DOCUMENT'].includes(draft.headerType) &&
        draft.headerMedia === null &&
        draft.existingHeaderType !== draft.headerType
    ) {
        return 'Escolha o arquivo do cabeçalho.';
    }

    if (draft.format === 'standard') {
        const invalidButton = draft.buttons.some((button) =>
            button.type === 'copy_code'
                ? button.example.trim() === ''
                : button.text.trim() === '' ||
                  (button.type === 'url' && button.url.trim() === '') ||
                  (button.type === 'phone_number' &&
                      button.phoneNumber.trim() === ''),
        );

        return invalidButton ? 'Complete os botões.' : null;
    }

    if (draft.format === 'coupon') {
        return draft.couponCode.trim() === ''
            ? 'Informe o código do cupom.'
            : null;
    }

    if (draft.format === 'limited_time_offer') {
        return draft.offerText.trim() === '' ||
            draft.offerCode.trim() === '' ||
            draft.offerUrlText.trim() === '' ||
            draft.offerUrl.trim() === ''
            ? 'Preencha título, código e link da oferta.'
            : null;
    }

    if (draft.format === 'carousel') {
        if (
            draft.cards.some((card) => !card.media && !card.existingMediaName)
        ) {
            return 'Escolha a mídia de todos os cards.';
        }

        if (
            draft.cardsHaveBody &&
            draft.cards.some((card) => card.body.trim() === '')
        ) {
            return 'Preencha o texto de todos os cards.';
        }

        if (
            draft.cards.some(
                (card) =>
                    hasMissingExamples(card.body, card.bodyExamples) ||
                    draft.cardButtonTypes.some(
                        (type, index) =>
                            (card.buttons[index]?.text ?? '').trim() === '' ||
                            (type === 'url' &&
                                (card.buttons[index]?.url ?? '').trim() ===
                                    '') ||
                            (type === 'phone_number' &&
                                (
                                    card.buttons[index]?.phoneNumber ?? ''
                                ).trim() === ''),
                    ),
            )
        ) {
            return 'Complete o texto e os botões de todos os cards.';
        }

        if (draft.cardButtonTypes.length === 0) {
            return 'Adicione pelo menos um botão aos cards.';
        }
    }

    return null;
}

export function previewButtons(draft: TemplateDraft): PreviewButton[] {
    if (draft.format === 'standard') {
        return draft.buttons.map((button) => ({
            type: button.type,
            text:
                button.type === 'copy_code'
                    ? 'Copiar código'
                    : button.text || buttonTypeLabels[button.type],
        }));
    }

    if (draft.format === 'coupon') {
        return [
            { type: 'copy_code', text: 'Copiar código' },
            ...(draft.couponQuickReply.trim() !== ''
                ? [
                      {
                          type: 'quick_reply' as const,
                          text: draft.couponQuickReply,
                      },
                  ]
                : []),
        ];
    }

    if (draft.format === 'limited_time_offer') {
        return [
            { type: 'copy_code', text: 'Copiar código' },
            { type: 'url', text: draft.offerUrlText || 'Ver oferta' },
        ];
    }

    if (draft.format === 'authentication') {
        return [
            {
                type: 'copy_code',
                text:
                    draft.otpType === 'copy_code'
                        ? 'Copiar código'
                        : 'Preencher código',
            },
        ];
    }

    return [];
}

export function previewBody(draft: TemplateDraft): string {
    if (draft.format === 'authentication') {
        return `*123456* é seu código de verificação.${
            draft.addSecurityRecommendation
                ? ' Para sua segurança, não compartilhe este código.'
                : ''
        }`;
    }

    return fillVariables(draft.body, draft.bodyExamples);
}

export function previewFooter(draft: TemplateDraft): string {
    if (draft.format === 'authentication') {
        if (!draft.expirationEnabled) {
            return '';
        }

        const minutes = Number(draft.codeExpirationMinutes) || 1;

        return `Este código expira em ${minutes} ${minutes === 1 ? 'minuto' : 'minutos'}.`;
    }

    return draft.format === 'standard' ? draft.footer : '';
}

export type LibraryModel = {
    key: string;
    name: string;
    title: string;
    purpose: string;
    usage: string;
    usageLabel: string;
    category: TemplateCategory;
    format: TemplateFormat;
    language: string;
    headerType: HeaderType;
    headerText: string;
    body: string;
    footer: string;
    buttons: { type: ButtonType; text: string }[];
    variables: {
        position: number;
        component: 'header' | 'body';
        example: string;
        description: string;
    }[];
};

function variableMap(
    model: LibraryModel,
    component: 'header' | 'body',
    field: 'example' | 'description',
): Record<number, string> {
    return Object.fromEntries(
        model.variables
            .filter((variable) => variable.component === component)
            .map((variable) => [variable.position, variable[field]]),
    );
}

export function draftFromModel(
    draft: TemplateDraft,
    model: LibraryModel,
): Partial<TemplateDraft> {
    return {
        name: draft.name.trim() === '' ? model.name : draft.name,
        language: model.language,
        category: model.category,
        format: model.format,
        headerType: model.headerType,
        headerText: model.headerText,
        headerMedia: null,
        body: model.body,
        footer: model.footer,
        headerExamples: variableMap(model, 'header', 'example'),
        headerDescriptions: variableMap(model, 'header', 'description'),
        bodyExamples: variableMap(model, 'body', 'example'),
        bodyDescriptions: variableMap(model, 'body', 'description'),
        buttons: model.buttons.map((button) => ({
            ...emptyButton(button.type),
            text: button.text,
        })),
        libraryKey: model.key,
    };
}

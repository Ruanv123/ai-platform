import { Form } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { store } from '@/routes/templates';

type ButtonType = 'quick_reply' | 'url' | 'phone_number' | 'copy_code';

type ButtonDraft = {
    type: ButtonType;
    text: string;
    url: string;
    phoneNumber: string;
    example: string;
};

function emptyButton(): ButtonDraft {
    return { type: 'quick_reply', text: '', url: '', phoneNumber: '', example: '' };
}

function positionsForText(text: string): number[] {
    return Array.from(
        new Set(Array.from(text.matchAll(/{{\s*(\d+)\s*}}/g), (match) => Number(match[1]))),
    );
}

function headerMediaValidationMessage(file: File, type: 'IMAGE' | 'DOCUMENT'): string {
    const isImage = type === 'IMAGE';
    const extensions = isImage
        ? ['jpg', 'jpeg', 'png']
        : ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'];
    const mimeTypes = isImage
        ? ['image/jpeg', 'image/png']
        : [
              'application/pdf',
              'application/msword',
              'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
              'application/vnd.ms-excel',
              'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
              'application/vnd.ms-powerpoint',
              'application/vnd.openxmlformats-officedocument.presentationml.presentation',
              'text/plain',
          ];
    const extension = file.name.split('.').pop()?.toLowerCase();
    const invalidType = !extensions.includes(extension ?? '')
        || (file.type !== '' && !mimeTypes.includes(file.type));
    if (invalidType) {
        return isImage
            ? 'A imagem de exemplo deve estar no formato JPEG ou PNG.'
            : 'O documento de exemplo deve estar no formato PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT.';
    }

    if (file.size > (isImage ? 5 : 100) * 1024 * 1024) {
        return isImage
            ? 'A imagem de exemplo deve ter no máximo 5 MB.'
            : 'O documento de exemplo deve ter no máximo 100 MB.';
    }

    return '';
}

export default function CreateTemplateDialog({
    open,
    onOpenChange,
    accounts,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    accounts: { id: number; label: string }[];
}) {
    const [body, setBody] = useState('');
    const [headerType, setHeaderType] = useState<'TEXT' | 'IMAGE' | 'DOCUMENT'>('TEXT');
    const [headerText, setHeaderText] = useState('');
    const [footer, setFooter] = useState('');
    const [buttons, setButtons] = useState<ButtonDraft[]>([]);
    const [headerMediaError, setHeaderMediaError] = useState('');
    const preview = body.replace(
        /{{\s*(\d+)\s*}}/g,
        (_match, position: string) => `valor ${position}`,
    );
    const variables = [
        ...positionsForText(headerText).map((position) => ({
            position,
            component: 'header',
        })),
        ...positionsForText(body).map((position) => ({
            position,
            component: 'body',
        })),
    ];

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Criar Template</DialogTitle>
                    <DialogDescription>
                        O rascunho fica salvo até você decidir enviá-lo para a
                        Meta.
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={store.url()}
                    method="post"
                    onSuccess={() => onOpenChange(false)}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-2">
                                <Label htmlFor="account">
                                    Conta do WhatsApp
                                </Label>
                                <select
                                    id="account"
                                    name="whatsapp_account_id"
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    defaultValue={accounts[0]?.id ?? ''}
                                >
                                    {accounts.map((account) => (
                                        <option
                                            key={account.id}
                                            value={account.id}
                                        >
                                            {account.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="name">Nome técnico</Label>
                                <Input
                                    id="name"
                                    name="name"
                                    placeholder="lembrete_consulta"
                                />
                                {errors.name && (
                                    <p className="text-destructive text-xs">
                                        {errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="language">Idioma</Label>
                                <Input
                                    id="language"
                                    name="language"
                                    defaultValue="pt_BR"
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="category">
                                    Categoria pedida
                                </Label>
                                <select
                                    id="category"
                                    name="requested_category"
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                    defaultValue="utility"
                                >
                                    <option value="utility">Utilidade</option>
                                    <option value="marketing">Marketing</option>
                                    <option value="authentication">
                                        Autenticação
                                    </option>
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="body">Corpo</Label>
                                <textarea
                                    id="body"
                                    name="body"
                                    value={body}
                                    onChange={(event) =>
                                        setBody(event.target.value)
                                    }
                                    className="border-input bg-background min-h-28 rounded-md border px-3 py-2 text-sm"
                                    placeholder="Olá {{1}}, seu agendamento é amanhã."
                                />
                                {errors.body && (
                                    <p className="text-destructive text-xs">
                                        {errors.body}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="header_type">Tipo de cabeçalho</Label>
                                <select
                                    id="header_type"
                                    name="header_type"
                                    value={headerType}
                                    onChange={(event) => {
                                        setHeaderType(event.target.value as typeof headerType);
                                        setHeaderMediaError('');
                                    }}
                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                >
                                    <option value="TEXT">Texto</option>
                                    <option value="IMAGE">Imagem</option>
                                    <option value="DOCUMENT">Documento</option>
                                </select>
                            </div>
                            {headerType === 'TEXT' ? (
                                <div className="grid gap-2">
                                    <Label htmlFor="header_text">
                                        Cabeçalho de texto (opcional)
                                    </Label>
                                    <Input
                                        id="header_text"
                                        name="header_text"
                                        value={headerText}
                                        onChange={(event) =>
                                            setHeaderText(event.target.value)
                                        }
                                    />
                                </div>
                            ) : (
                                <div className="grid gap-2">
                                    <Label htmlFor="header_media">
                                        {headerType === 'IMAGE'
                                            ? 'Imagem de exemplo · JPEG ou PNG · até 5 MB'
                                            : 'Documento de exemplo · PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT · até 100 MB'}
                                    </Label>
                                    <input
                                        key={headerType}
                                        id="header_media"
                                        name="header_media"
                                        type="file"
                                        required
                                        accept={headerType === 'IMAGE'
                                            ? '.jpg,.jpeg,.png,image/jpeg,image/png'
                                            : '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt'}
                                        onChange={(event) => {
                                            const file = event.currentTarget.files?.[0];
                                            if (file === undefined) {
                                                setHeaderMediaError('');
                                                return;
                                            }

                                            const error = headerMediaValidationMessage(file, headerType);
                                            setHeaderMediaError(error);
                                            if (error !== '') {
                                                event.currentTarget.value = '';
                                            }
                                        }}
                                        className="border-input file:bg-muted file:text-foreground rounded-md border px-3 py-2 text-xs file:mr-3 file:rounded file:border-0 file:px-2 file:py-1"
                                    />
                                    {(headerMediaError || errors.header_media) && (
                                        <p className="text-destructive text-xs">
                                            {headerMediaError || errors.header_media}
                                        </p>
                                    )}
                                </div>
                            )}
                            <div className="grid gap-2">
                                <Label htmlFor="footer">
                                    Rodapé (opcional)
                                </Label>
                                <Input
                                    id="footer"
                                    name="footer"
                                    value={footer}
                                    onChange={(event) =>
                                        setFooter(event.target.value)
                                    }
                                />
                            </div>
                            {variables.length > 0 && (
                                <fieldset className="grid gap-3 rounded-lg border p-3">
                                    <legend className="px-1 text-sm font-medium">
                                        Exemplos das variáveis
                                    </legend>
                                    {variables.map((variable, index) => (
                                        <div
                                            key={`${variable.component}-${variable.position}`}
                                            className="grid gap-2"
                                        >
                                            <Label htmlFor={`variable-${index}`}>
                                                {variable.component === 'header'
                                                    ? 'Cabeçalho'
                                                    : 'Corpo'}{' '}
                                                {'{{'}{variable.position}{'}}'}
                                            </Label>
                                            <input
                                                type="hidden"
                                                name={`variables[${index}][position]`}
                                                value={variable.position}
                                            />
                                            <input
                                                type="hidden"
                                                name={`variables[${index}][component]`}
                                                value={variable.component}
                                            />
                                            <Input
                                                id={`variable-${index}`}
                                                name={`variables[${index}][example]`}
                                                placeholder="Exemplo usado pela Meta"
                                            />
                                        </div>
                                    ))}
                                </fieldset>
                            )}
                            <fieldset className="grid gap-3 rounded-lg border p-3">
                                <legend className="px-1 text-sm font-medium">
                                    Botões (opcional)
                                </legend>
                                {buttons.map((button, index) => {
                                    const isConfigured =
                                        button.text.trim() !== '' ||
                                        button.url.trim() !== '' ||
                                        button.phoneNumber.trim() !== '' ||
                                        button.example.trim() !== '';
                                    const fieldName = (field: string) =>
                                        isConfigured
                                            ? `buttons[${index}][${field}]`
                                            : undefined;

                                    return (
                                        <div
                                            key={index}
                                            className="grid gap-3 rounded-md bg-muted/40 p-3 sm:grid-cols-2"
                                        >
                                            <div className="grid gap-2">
                                                <Label htmlFor={`button-type-${index}`}>
                                                    Tipo
                                                </Label>
                                                <select
                                                    id={`button-type-${index}`}
                                                    name={fieldName('type')}
                                                    value={button.type}
                                                    onChange={(event) =>
                                                        setButtons((current) =>
                                                            current.map((item, itemIndex) =>
                                                                itemIndex === index
                                                                    ? { ...item, type: event.target.value as ButtonType }
                                                                    : item,
                                                            ),
                                                        )
                                                    }
                                                    className="border-input bg-background h-9 rounded-md border px-3 text-sm"
                                                >
                                                    <option value="quick_reply">
                                                        Resposta rápida
                                                    </option>
                                                    <option value="url">URL</option>
                                                    <option value="phone_number">
                                                        Telefone
                                                    </option>
                                                    <option value="copy_code">
                                                        Copiar código
                                                    </option>
                                                </select>
                                            </div>
                                            {button.type !== 'copy_code' && (
                                                <div className="grid gap-2">
                                                    <Label htmlFor={`button-text-${index}`}>
                                                        Texto do botão
                                                    </Label>
                                                    <Input
                                                        id={`button-text-${index}`}
                                                        name={fieldName('text')}
                                                        value={button.text}
                                                        onChange={(event) =>
                                                            setButtons((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? { ...item, text: event.target.value }
                                                                        : item,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </div>
                                            )}
                                            {button.type === 'url' && (
                                                <>
                                                    <div className="grid gap-2 sm:col-span-2">
                                                        <Label htmlFor={`button-url-${index}`}>
                                                            Endereço
                                                        </Label>
                                                        <Input
                                                            id={`button-url-${index}`}
                                                            name={fieldName('url')}
                                                            value={button.url}
                                                            onChange={(event) =>
                                                                setButtons((current) =>
                                                                    current.map((item, itemIndex) =>
                                                                        itemIndex === index
                                                                            ? { ...item, url: event.target.value }
                                                                            : item,
                                                                    ),
                                                                )
                                                            }
                                                            placeholder="https://exemplo.com/{{1}}"
                                                        />
                                                    </div>
                                                    {positionsForText(button.url).length > 0 && (
                                                        <div className="grid gap-2 sm:col-span-2">
                                                            <Label htmlFor={`button-example-${index}`}>
                                                                Exemplo do endereço
                                                            </Label>
                                                            <Input
                                                                id={`button-example-${index}`}
                                                                name={fieldName('example')}
                                                                value={button.example}
                                                                onChange={(event) =>
                                                                    setButtons((current) =>
                                                                        current.map((item, itemIndex) =>
                                                                            itemIndex === index
                                                                                ? { ...item, example: event.target.value }
                                                                                : item,
                                                                        ),
                                                                    )
                                                                }
                                                            />
                                                        </div>
                                                    )}
                                                </>
                                            )}
                                            {button.type === 'phone_number' && (
                                                <div className="grid gap-2 sm:col-span-2">
                                                    <Label htmlFor={`button-phone-${index}`}>
                                                        Telefone
                                                    </Label>
                                                    <Input
                                                        id={`button-phone-${index}`}
                                                        name={fieldName('phone_number')}
                                                        value={button.phoneNumber}
                                                        onChange={(event) =>
                                                            setButtons((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? { ...item, phoneNumber: event.target.value }
                                                                        : item,
                                                                ),
                                                            )
                                                        }
                                                        placeholder="+5511999999999"
                                                    />
                                                </div>
                                            )}
                                            {button.type === 'copy_code' && (
                                                <div className="grid gap-2 sm:col-span-2">
                                                    <Label htmlFor={`button-example-${index}`}>
                                                        Código de exemplo
                                                    </Label>
                                                    <Input
                                                        id={`button-example-${index}`}
                                                        name={fieldName('example')}
                                                        value={button.example}
                                                        onChange={(event) =>
                                                            setButtons((current) =>
                                                                current.map((item, itemIndex) =>
                                                                    itemIndex === index
                                                                        ? { ...item, example: event.target.value }
                                                                        : item,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </div>
                                            )}
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                className="w-fit sm:col-span-2"
                                                onClick={() =>
                                                    setButtons((current) =>
                                                        current.filter((_, itemIndex) => itemIndex !== index),
                                                    )
                                                }
                                            >
                                                <X /> Remover botão
                                            </Button>
                                        </div>
                                    );
                                })}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                    disabled={buttons.length >= 3}
                                    onClick={() => setButtons((current) => [...current, emptyButton()])}
                                >
                                    <Plus /> Adicionar botão
                                </Button>
                            </fieldset>
                            {(body || headerText || footer || buttons.length > 0) && (
                                <div className="bg-muted/50 grid gap-2 rounded-lg border p-3">
                                    <span className="text-muted-foreground text-xs font-medium">
                                        Prévia
                                    </span>
                                    {headerText && (
                                        <p className="text-sm font-semibold">
                                            {headerText}
                                        </p>
                                    )}
                                    <p className="text-sm leading-5 whitespace-pre-wrap">
                                        {preview || 'O corpo aparecerá aqui.'}
                                    </p>
                                    {footer && (
                                        <p className="text-muted-foreground text-xs">
                                            {footer}
                                        </p>
                                    )}
                                    {buttons.map((button, index) => (
                                        <span
                                            key={index}
                                            className="text-primary border-primary/20 bg-background rounded-md border px-3 py-2 text-center text-sm"
                                        >
                                            {button.type === 'copy_code'
                                                ? 'Copiar código'
                                                : button.text || `Botão ${index + 1}`}
                                        </span>
                                    ))}
                                </div>
                            )}
                            {errors.template && (
                                <p className="text-destructive text-sm">
                                    {errors.template}
                                </p>
                            )}
                            <Button type="submit" disabled={processing}>
                                <Check /> Salvar rascunho
                            </Button>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

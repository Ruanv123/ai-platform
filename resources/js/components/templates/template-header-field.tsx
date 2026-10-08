import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import MediaDropzone from './media-dropzone';
import { headerTypesForFormat } from './template-draft';
import type { HeaderType, TemplateDraft } from './template-draft';
import { variablePositions } from './whatsapp-text';

const headerLabels: Record<HeaderType, string> = {
    NONE: 'Nenhum',
    TEXT: 'Texto',
    IMAGE: 'Imagem',
    VIDEO: 'Vídeo',
    DOCUMENT: 'Documento',
};

export default function TemplateHeaderField({
    draft,
    errors,
    onChange,
}: {
    draft: TemplateDraft;
    errors: Record<string, string>;
    onChange: (changes: Partial<TemplateDraft>) => void;
}) {
    const headerType = draft.headerType;
    const isMedia =
        headerType === 'IMAGE' ||
        headerType === 'VIDEO' ||
        headerType === 'DOCUMENT';

    return (
        <section className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h3 className="text-sm font-semibold">
                    Cabeçalho{' '}
                    <span className="text-muted-foreground font-normal">
                        (opcional)
                    </span>
                </h3>
                <ToggleGroup
                    type="single"
                    variant="outline"
                    size="sm"
                    value={headerType}
                    aria-label="Tipo de cabeçalho"
                    onValueChange={(value) => {
                        if (value) {
                            onChange({
                                headerType: value as HeaderType,
                                headerMedia: null,
                            });
                        }
                    }}
                >
                    {headerTypesForFormat(draft.format).map((type) => (
                        <ToggleGroupItem
                            key={type}
                            value={type}
                            className="px-2.5"
                        >
                            {headerLabels[type]}
                        </ToggleGroupItem>
                    ))}
                </ToggleGroup>
            </div>

            {headerType === 'TEXT' && (
                <div className="grid gap-2">
                    <Input
                        aria-label="Texto do cabeçalho"
                        value={draft.headerText}
                        maxLength={60}
                        onChange={(event) =>
                            onChange({ headerText: event.target.value })
                        }
                        placeholder="Ex.: Agendamento confirmado"
                    />
                    {variablePositions(draft.headerText).map((position) => (
                        <div
                            key={position}
                            className="flex flex-wrap items-center gap-2"
                        >
                            <span className="bg-background rounded-md border px-2 py-0.5 text-[13px] font-semibold">{`{{${position}}}`}</span>
                            <Input
                                aria-label={`O que é a variável ${position} do cabeçalho`}
                                value={draft.headerDescriptions[position] ?? ''}
                                maxLength={255}
                                onChange={(event) =>
                                    onChange({
                                        headerDescriptions: {
                                            ...draft.headerDescriptions,
                                            [position]: event.target.value,
                                        },
                                    })
                                }
                                placeholder="O que é? Ex.: primeiro nome do Contato"
                                className="min-w-0 flex-1 basis-52"
                            />
                            <Input
                                aria-label={`Exemplo da variável ${position} do cabeçalho`}
                                value={draft.headerExamples[position] ?? ''}
                                onChange={(event) =>
                                    onChange({
                                        headerExamples: {
                                            ...draft.headerExamples,
                                            [position]: event.target.value,
                                        },
                                    })
                                }
                                placeholder="Exemplo usado pela Meta"
                                className="min-w-0 flex-1"
                            />
                        </div>
                    ))}
                    <InputError message={errors.header_text} />
                </div>
            )}

            {isMedia && (
                <MediaDropzone
                    key={headerType}
                    id="header_media"
                    kind={headerType}
                    file={draft.headerMedia}
                    existingName={
                        draft.existingHeaderType === headerType
                            ? (draft.existingHeaderMediaName ?? 'Arquivo atual')
                            : null
                    }
                    error={errors.header_media}
                    onFileChange={(file) => onChange({ headerMedia: file })}
                />
            )}
        </section>
    );
}

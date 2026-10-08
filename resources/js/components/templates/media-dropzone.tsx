import { Check, FileText, Trash2, Upload } from 'lucide-react';
import { useMemo, useState } from 'react';
import type { DragEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type MediaKind = 'IMAGE' | 'VIDEO' | 'DOCUMENT';

const mediaRules: Record<
    MediaKind,
    {
        accept: string;
        extensions: string[];
        maxMegabytes: number;
        noun: string;
        hint: string;
    }
> = {
    IMAGE: {
        accept: '.jpg,.jpeg,.png,image/jpeg,image/png',
        extensions: ['jpg', 'jpeg', 'png'],
        maxMegabytes: 5,
        noun: 'a imagem',
        hint: 'JPEG ou PNG · até 5 MB',
    },
    VIDEO: {
        accept: '.mp4,.3gp,video/mp4,video/3gpp',
        extensions: ['mp4', '3gp'],
        maxMegabytes: 16,
        noun: 'o vídeo',
        hint: 'MP4 ou 3GP · até 16 MB',
    },
    DOCUMENT: {
        accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt',
        extensions: ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt'],
        maxMegabytes: 100,
        noun: 'o documento',
        hint: 'PDF, DOC, DOCX, XLS, XLSX, PPT, PPTX ou TXT · até 100 MB',
    },
};

function formatSize(bytes: number): string {
    if (bytes >= 1024 * 1024) {
        return `${(bytes / (1024 * 1024)).toFixed(1).replace('.', ',')} MB`;
    }

    return `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

export default function MediaDropzone({
    id,
    kind,
    file,
    existingName,
    error,
    compact = false,
    onFileChange,
}: {
    id: string;
    kind: MediaKind;
    file: File | null;
    existingName: string | null;
    error?: string;
    compact?: boolean;
    onFileChange: (file: File | null) => void;
}) {
    const [isDragging, setIsDragging] = useState(false);
    const [localError, setLocalError] = useState('');
    const rules = mediaRules[kind];
    const previewUrl = useMemo(
        () =>
            file && file.type.startsWith('image/')
                ? URL.createObjectURL(file)
                : null,
        [file],
    );

    const accept = (candidate: File | undefined) => {
        setIsDragging(false);

        if (!candidate) {
            return;
        }

        const extension = candidate.name.split('.').pop()?.toLowerCase() ?? '';

        if (!rules.extensions.includes(extension)) {
            setLocalError(`Use ${rules.hint.split(' · ')[0]}.`);

            return;
        }

        if (candidate.size > rules.maxMegabytes * 1024 * 1024) {
            setLocalError(`O arquivo passa de ${rules.maxMegabytes} MB.`);

            return;
        }

        setLocalError('');
        onFileChange(candidate);
    };

    const handleDrop = (event: DragEvent<HTMLLabelElement>) => {
        event.preventDefault();
        accept(event.dataTransfer.files[0]);
    };

    const message = localError || error;
    const chosenName = file?.name ?? existingName;

    return (
        <div className="grid gap-2">
            {chosenName ? (
                <div
                    className={cn(
                        'bg-card flex items-center gap-3 rounded-xl border p-3 shadow-xs',
                        compact && 'flex-col items-stretch',
                    )}
                >
                    {previewUrl ? (
                        <img
                            src={previewUrl}
                            alt="Prévia do arquivo escolhido"
                            className={cn(
                                'shrink-0 rounded-lg border object-cover',
                                compact ? 'h-28 w-full' : 'size-16',
                            )}
                        />
                    ) : (
                        <span
                            className={cn(
                                'bg-muted text-muted-foreground flex shrink-0 items-center justify-center rounded-lg',
                                compact ? 'h-28 w-full' : 'size-16',
                            )}
                        >
                            <FileText className="size-6" />
                        </span>
                    )}
                    <span className="grid min-w-0 flex-1 gap-0.5">
                        <span className="truncate text-sm font-medium">
                            {chosenName}
                        </span>
                        <span className="text-muted-foreground flex items-center gap-1.5 text-xs">
                            <Check className="size-3.5 text-green-700" />
                            {file
                                ? `${formatSize(file.size)} · pronto para enviar`
                                : 'arquivo atual'}
                        </span>
                    </span>
                    <span className="flex items-center gap-1">
                        <Button variant="outline" size="sm" asChild>
                            <label
                                htmlFor={`${id}-replace`}
                                className="cursor-pointer"
                            >
                                Trocar
                                <input
                                    id={`${id}-replace`}
                                    type="file"
                                    accept={rules.accept}
                                    className="sr-only"
                                    onChange={(event) => {
                                        accept(event.target.files?.[0]);
                                        event.target.value = '';
                                    }}
                                />
                            </label>
                        </Button>
                        {file && (
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                aria-label="Remover arquivo"
                                onClick={() => onFileChange(null)}
                            >
                                <Trash2 />
                            </Button>
                        )}
                    </span>
                </div>
            ) : (
                <label
                    htmlFor={id}
                    onDragOver={(event) => {
                        event.preventDefault();
                        setIsDragging(true);
                    }}
                    onDragLeave={() => setIsDragging(false)}
                    onDrop={handleDrop}
                    className={cn(
                        'has-[:focus-visible]:ring-ring/50 flex cursor-pointer flex-col items-center justify-center rounded-xl border-[1.5px] border-dashed text-center transition-colors has-[:focus-visible]:ring-[3px]',
                        compact ? 'min-h-36 gap-1.5 p-3' : 'min-h-42 gap-3 p-6',
                        isDragging
                            ? 'border-primary bg-muted'
                            : 'border-input bg-muted/40',
                    )}
                >
                    <input
                        id={id}
                        type="file"
                        accept={rules.accept}
                        className="sr-only"
                        onChange={(event) => {
                            accept(event.target.files?.[0]);
                            event.target.value = '';
                        }}
                    />
                    <span className="bg-background flex size-11 items-center justify-center rounded-full border shadow-xs">
                        <Upload className="size-5" />
                    </span>
                    <span className="grid gap-1">
                        <span className="text-sm">
                            <span className="font-semibold underline underline-offset-4">
                                {compact ? 'Escolher' : 'Clique para escolher'}
                            </span>{' '}
                            ou arraste {rules.noun} aqui
                        </span>
                        <span className="text-muted-foreground text-xs">
                            {rules.hint}
                        </span>
                    </span>
                </label>
            )}
            {message && (
                <p role="alert" className="text-destructive text-xs">
                    {message}
                </p>
            )}
        </div>
    );
}

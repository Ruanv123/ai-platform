import { Bold, Braces, Code, Italic, Strikethrough } from 'lucide-react';
import { useRef } from 'react';
import type { KeyboardEvent } from 'react';
import { flushSync } from 'react-dom';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import EditorToolbarButton from './editor-toolbar-button';
import VariablesPanel from './variables-panel';
import {
    editorSegments,
    nextVariablePosition,
    removeVariable,
    shiftExamples,
    variablePositions,
} from './whatsapp-text';

type Transform = (
    before: string,
    selected: string,
    after: string,
) => { text: string; selectionStart: number; selectionEnd: number };

export default function MessageEditor({
    id,
    label,
    value,
    maxLength,
    examples,
    descriptions,
    error,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    maxLength: number;
    examples: Record<number, string>;
    descriptions: Record<number, string>;
    error?: string;
    onChange: (
        value: string,
        examples: Record<number, string>,
        descriptions: Record<number, string>,
    ) => void;
}) {
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const positions = variablePositions(value);

    const edit = (transform: Transform) => {
        const element = textareaRef.current;
        const start = element?.selectionStart ?? value.length;
        const end = element?.selectionEnd ?? value.length;
        const result = transform(
            value.slice(0, start),
            value.slice(start, end),
            value.slice(end),
        );

        flushSync(() =>
            onChange(result.text.slice(0, maxLength), examples, descriptions),
        );
        element?.focus();
        element?.setSelectionRange(result.selectionStart, result.selectionEnd);
    };

    const wrap = (marker: string, fallback: string) =>
        edit((before, selected, after) => {
            const content = selected || fallback;

            return {
                text: `${before}${marker}${content}${marker}${after}`,
                selectionStart: before.length + marker.length,
                selectionEnd: before.length + marker.length + content.length,
            };
        });

    const insertVariable = () =>
        edit((before, selected, after) => {
            const separator = before === '' || /\s$/.test(before) ? '' : ' ';
            const inserted = `${separator}{{${nextVariablePosition(value)}}}`;
            const cursor = before.length + inserted.length;

            return {
                text: `${before}${inserted}${after}`,
                selectionStart: cursor,
                selectionEnd: cursor,
            };
        });

    const handleKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (!(event.ctrlKey || event.metaKey)) {
            return;
        }

        const key = event.key.toLowerCase();

        if (key === 'b') {
            event.preventDefault();
            wrap('*', 'negrito');
        } else if (key === 'i') {
            event.preventDefault();
            wrap('_', 'itálico');
        }
    };

    return (
        <section className="grid gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <label htmlFor={id} className="text-sm font-semibold">
                    {label}
                </label>
                <span className="text-muted-foreground text-xs">
                    Selecione um trecho e escolha a formatação
                </span>
            </div>
            <div
                className={cn(
                    'overflow-hidden rounded-md border shadow-xs',
                    'focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px]',
                    error && 'border-destructive',
                )}
            >
                <div
                    role="toolbar"
                    aria-label="Formatação da mensagem"
                    className="bg-background flex flex-wrap items-center gap-0.5 border-b px-1.5 py-1"
                >
                    <EditorToolbarButton
                        label="Negrito (Ctrl+B)"
                        onApply={() => wrap('*', 'negrito')}
                    >
                        <Bold />
                    </EditorToolbarButton>
                    <EditorToolbarButton
                        label="Itálico (Ctrl+I)"
                        onApply={() => wrap('_', 'itálico')}
                    >
                        <Italic />
                    </EditorToolbarButton>
                    <EditorToolbarButton
                        label="Tachado"
                        onApply={() => wrap('~', 'tachado')}
                    >
                        <Strikethrough />
                    </EditorToolbarButton>
                    <EditorToolbarButton
                        label="Monoespaçado"
                        onApply={() => wrap('```', 'código')}
                    >
                        <Code />
                    </EditorToolbarButton>
                    <span
                        aria-hidden="true"
                        className="bg-border mx-1.5 h-5 w-px"
                    />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onMouseDown={(event) => event.preventDefault()}
                        onClick={insertVariable}
                    >
                        <Braces />
                        Variável
                    </Button>
                </div>
                <div className="bg-background relative">
                    <div
                        aria-hidden="true"
                        className="min-h-28 px-3 py-2.5 text-sm leading-[22px] break-words whitespace-pre-wrap"
                    >
                        {editorSegments(value).map((segment, index) => (
                            <span
                                key={index}
                                className={cn(
                                    segment.kind === 'variable' &&
                                        'bg-muted rounded-sm shadow-[inset_0_0_0_1px_var(--border)]',
                                    segment.kind === 'marker' &&
                                        'text-muted-foreground/60',
                                )}
                            >
                                {segment.text}
                            </span>
                        ))}
                        <span> </span>
                    </div>
                    <textarea
                        id={id}
                        ref={textareaRef}
                        value={value}
                        maxLength={maxLength}
                        onChange={(event) =>
                            onChange(event.target.value, examples, descriptions)
                        }
                        onKeyDown={handleKeyDown}
                        placeholder="Escreva a mensagem que o Contato vai receber."
                        className="placeholder:text-muted-foreground caret-foreground selection:bg-primary/20 absolute inset-0 size-full resize-none overflow-hidden bg-transparent px-3 py-2.5 text-sm leading-[22px] break-words whitespace-pre-wrap text-transparent outline-none selection:text-transparent"
                    />
                </div>
                <VariablesPanel
                    idPrefix={id}
                    positions={positions}
                    examples={examples}
                    descriptions={descriptions}
                    counter={`${value.length}/${maxLength} caracteres`}
                    onExampleChange={(position, example) =>
                        onChange(
                            value,
                            { ...examples, [position]: example },
                            descriptions,
                        )
                    }
                    onDescriptionChange={(position, description) =>
                        onChange(value, examples, {
                            ...descriptions,
                            [position]: description,
                        })
                    }
                    onRemove={(position) =>
                        onChange(
                            removeVariable(value, position),
                            shiftExamples(examples, position),
                            shiftExamples(descriptions, position),
                        )
                    }
                />
            </div>
            {error && <p className="text-destructive text-xs">{error}</p>}
        </section>
    );
}

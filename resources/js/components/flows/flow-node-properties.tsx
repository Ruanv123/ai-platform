import { useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { FlowNode, NodeCatalogItem } from '@/components/flows/flow-canvas';

const textareaClassName =
    'border-input placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-ring/50 min-h-24 w-full resize-y rounded-md border bg-transparent px-3 py-2 text-sm outline-none focus-visible:ring-[3px]';

type ChannelOption = {
    id: string;
    label: string;
};

type FlowNodePropertiesProps = {
    node: FlowNode;
    catalog: NodeCatalogItem[];
    channels: ChannelOption[];
    triggerType: string;
    triggerEvent: string | null;
    channelId: string | null;
    onChangeConfig: (nodeId: string, config: Record<string, unknown>) => void;
    onChangeFlowMeta: (changes: Record<string, string | null>) => void;
    onClose: () => void;
};

export function FlowNodeProperties({
    node,
    catalog,
    channels,
    triggerType,
    triggerEvent,
    channelId,
    onChangeConfig,
    onChangeFlowMeta,
    onClose,
}: FlowNodePropertiesProps) {
    const [fullEditorOpen, setFullEditorOpen] = useState(false);
    const label =
        catalog.find((item) => item.type === node.type)?.label ?? node.type;
    const setConfig = (key: string, value: string | number | boolean) => {
        onChangeConfig(node.id, { ...node.config, [key]: value });
    };

    return (
        <aside className="bg-background absolute top-20 right-5 z-30 flex max-h-[calc(100%-7rem)] w-[min(360px,calc(100%-2rem))] flex-col overflow-hidden rounded-2xl border shadow-xl">
            <div className="flex items-start justify-between gap-4 border-b p-5">
                <div>
                    <p className="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">
                        Configurar passo
                    </p>
                    <h2 className="mt-1 text-base font-semibold tracking-tight">
                        {label}
                    </h2>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar propriedades"
                    onClick={onClose}
                >
                    ×
                </Button>
            </div>
            <div className="flex-1 space-y-5 overflow-y-auto p-5">
                {node.type === 'trigger' && (
                    <>
                        <Field label="Como começa">
                            <select
                                className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                value={triggerType}
                                onChange={(event) =>
                                    onChangeFlowMeta({
                                        trigger_type: event.target.value,
                                    })
                                }
                            >
                                <option value="message">
                                    Mensagem recebida
                                </option>
                                <option value="webhook">Webhook</option>
                                <option value="manual">
                                    Manual (Playground)
                                </option>
                            </select>
                        </Field>
                        {triggerType === 'message' && (
                            <Field label="Canal">
                                <select
                                    className="border-input bg-background focus-visible:border-ring focus-visible:ring-ring/50 h-9 w-full rounded-md border px-3 text-sm outline-none focus-visible:ring-[3px]"
                                    value={channelId ?? ''}
                                    onChange={(event) =>
                                        onChangeFlowMeta({
                                            channel_id:
                                                event.target.value || null,
                                        })
                                    }
                                >
                                    <option value="">Escolha um Canal</option>
                                    {channels.map((channel) => (
                                        <option
                                            key={channel.id}
                                            value={channel.id}
                                        >
                                            {channel.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        )}
                        {triggerType === 'webhook' && (
                            <Field label="Nome do evento">
                                <Input
                                    value={triggerEvent ?? ''}
                                    placeholder="appointment.created"
                                    onChange={(event) =>
                                        onChangeFlowMeta({
                                            trigger_event:
                                                event.target.value || null,
                                        })
                                    }
                                />
                            </Field>
                        )}
                        <p className="text-muted-foreground text-xs leading-5">
                            O gatilho é parte do desenho. O motor ignora a
                            posição visual dele e usa apenas a configuração do
                            Fluxo.
                        </p>
                    </>
                )}
                {node.type === 'message' && (
                    <Field label="Mensagem">
                        <textarea
                            className={textareaClassName}
                            value={stringValue(
                                node.config.text ?? node.config.message,
                            )}
                            placeholder="Escreva a mensagem que o Contato vai receber..."
                            onChange={(event) =>
                                setConfig('text', event.target.value)
                            }
                        />
                    </Field>
                )}
                {node.type === 'capture_response' && (
                    <>
                        <Field label="Pergunta">
                            <textarea
                                className={textareaClassName}
                                value={stringValue(
                                    node.config.question ?? node.config.text,
                                )}
                                placeholder="O que você quer perguntar?"
                                onChange={(event) =>
                                    setConfig('question', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Guardar resposta em">
                            <Input
                                value={stringValue(node.config.variable)}
                                placeholder="nome"
                                onChange={(event) =>
                                    setConfig('variable', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Formato">
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={stringValue(
                                    node.config.format ?? 'text',
                                )}
                                onChange={(event) =>
                                    setConfig('format', event.target.value)
                                }
                            >
                                <option value="text">Texto</option>
                                <option value="number">Número</option>
                                <option value="date">Data</option>
                            </select>
                        </Field>
                    </>
                )}
                {node.type === 'wait' && (
                    <Field label="Esperar (segundos)">
                        <Input
                            type="number"
                            min={1}
                            value={stringValue(node.config.seconds ?? 60)}
                            onChange={(event) =>
                                setConfig('seconds', Number(event.target.value))
                            }
                        />
                    </Field>
                )}
                {node.type === 'condition' && (
                    <Field label="Condições (uma por linha)">
                        <textarea
                            className={textareaClassName}
                            value={stringValue(
                                node.config.expression ??
                                    node.config.description,
                            )}
                            placeholder="contact.vip == true"
                            onChange={(event) =>
                                setConfig('expression', event.target.value)
                            }
                        />
                        <p className="text-muted-foreground mt-2 text-xs">
                            Conecte uma saída “senão” para cobrir o restante dos
                            casos.
                        </p>
                    </Field>
                )}
                {node.type === 'http_request' && (
                    <>
                        <Field label="URL">
                            <Input
                                value={stringValue(node.config.url)}
                                placeholder="https://api.exemplo.com/..."
                                onChange={(event) =>
                                    setConfig('url', event.target.value)
                                }
                            />
                        </Field>
                        <Field label="Método">
                            <select
                                className="border-input bg-background h-9 w-full rounded-md border px-3 text-sm"
                                value={stringValue(
                                    node.config.method ?? 'POST',
                                )}
                                onChange={(event) =>
                                    setConfig('method', event.target.value)
                                }
                            >
                                {['GET', 'POST', 'PUT', 'PATCH', 'DELETE'].map(
                                    (method) => (
                                        <option key={method}>{method}</option>
                                    ),
                                )}
                            </select>
                        </Field>
                        <Button
                            variant="outline"
                            className="w-full"
                            onClick={() => setFullEditorOpen(true)}
                        >
                            Editar corpo completo
                        </Button>
                    </>
                )}
                <NodeConfigFields node={node} onChange={setConfig} />
                {![
                    'trigger',
                    'message',
                    'capture_response',
                    'wait',
                    'condition',
                    'http_request',
                    ...dedicatedNodeTypes,
                ].includes(node.type) && (
                    <Field label="Configuração">
                        <textarea
                            className={`${textareaClassName} min-h-40 font-mono text-xs`}
                            value={JSON.stringify(node.config, null, 2)}
                            onChange={(event) => {
                                try {
                                    const parsed = JSON.parse(
                                        event.target.value,
                                    ) as Record<string, unknown>;
                                    onChangeConfig(node.id, parsed);
                                } catch {
                                    // The editor keeps the last valid JSON until the user finishes typing.
                                }
                            }}
                        />
                    </Field>
                )}
            </div>
            {fullEditorOpen && (
                <FullConfigEditor
                    config={node.config}
                    onClose={() => setFullEditorOpen(false)}
                    onSave={(config) => {
                        onChangeConfig(node.id, config);
                        setFullEditorOpen(false);
                    }}
                />
            )}
        </aside>
    );
}

const dedicatedNodeTypes = [
    'set_variable_or_tag',
    'deliver_agent',
    'transfer_human',
    'emit_event',
    'send_template',
    'subflow',
    'ai_task',
    'intent_router',
    'ab_test',
    'exception',
] as const;

function NodeConfigFields({
    node,
    onChange,
}: {
    node: FlowNode;
    onChange: (key: string, value: string) => void;
}) {
    const definitions: Record<
        string,
        Array<{ key: string; label: string; multiline?: boolean }>
    > = {
        set_variable_or_tag: [
            { key: 'name', label: 'Nome' },
            { key: 'value', label: 'Valor' },
            { key: 'kind', label: 'Guardar como (variável ou tag)' },
        ],
        deliver_agent: [
            { key: 'agent_id', label: 'Agente' },
            { key: 'return_when', label: 'Retomar quando', multiline: true },
        ],
        transfer_human: [
            {
                key: 'message',
                label: 'Mensagem para o Contato',
                multiline: true,
            },
        ],
        emit_event: [
            { key: 'event', label: 'Nome do Evento' },
            { key: 'payload', label: 'Payload', multiline: true },
        ],
        send_template: [
            { key: 'template', label: 'Template aprovado' },
            {
                key: 'variables',
                label: 'Variáveis posicionais',
                multiline: true,
            },
        ],
        subflow: [
            { key: 'flow_id', label: 'Fluxo chamado' },
            { key: 'inputs', label: 'Entradas', multiline: true },
            { key: 'outputs', label: 'Retornos', multiline: true },
        ],
        ai_task: [
            { key: 'instruction', label: 'Instrução', multiline: true },
            { key: 'output_variable', label: 'Guardar resultado em' },
        ],
        intent_router: [
            {
                key: 'intents',
                label: 'Intenções e descrições',
                multiline: true,
            },
        ],
        ab_test: [
            { key: 'variants', label: 'Variações e pesos', multiline: true },
        ],
        exception: [
            { key: 'message', label: 'Mensagem de fallback', multiline: true },
        ],
    };
    const fields = definitions[node.type] ?? [];

    if (fields.length === 0) {
        return null;
    }

    return (
        <div className="grid gap-4">
            {fields.map((field) => (
                <div key={field.key} className="grid gap-2">
                    <Label>{field.label}</Label>
                    {field.multiline ? (
                        <textarea
                            className={textareaClassName}
                            value={stringValue(node.config[field.key])}
                            onChange={(event) =>
                                onChange(field.key, event.target.value)
                            }
                        />
                    ) : (
                        <Input
                            value={stringValue(node.config[field.key])}
                            onChange={(event) =>
                                onChange(field.key, event.target.value)
                            }
                        />
                    )}
                </div>
            ))}
        </div>
    );
}

function FullConfigEditor({
    config,
    onClose,
    onSave,
}: {
    config: Record<string, unknown>;
    onClose: () => void;
    onSave: (config: Record<string, unknown>) => void;
}) {
    const [value, setValue] = useState(JSON.stringify(config, null, 2));
    const [error, setError] = useState<string | null>(null);

    return (
        <div className="bg-background absolute inset-0 z-40 flex flex-col">
            <div className="flex items-start justify-between border-b p-5">
                <div>
                    <p className="text-muted-foreground text-[11px] font-medium tracking-[0.16em] uppercase">
                        Janela cheia
                    </p>
                    <h2 className="mt-1 font-semibold">Corpo da Requisição</h2>
                </div>
                <Button
                    variant="ghost"
                    size="icon"
                    aria-label="Fechar editor"
                    onClick={onClose}
                >
                    ×
                </Button>
            </div>
            <div className="flex flex-1 flex-col gap-3 p-5">
                <textarea
                    className={`${textareaClassName} min-h-0 flex-1 font-mono text-xs`}
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                />
                {error && <p className="text-destructive text-xs">{error}</p>}
                <div className="flex justify-end gap-2">
                    <Button variant="outline" onClick={onClose}>
                        Cancelar
                    </Button>
                    <Button
                        onClick={() => {
                            try {
                                const parsed = JSON.parse(value) as Record<
                                    string,
                                    unknown
                                >;
                                onSave(parsed);
                            } catch {
                                setError(
                                    'Use um JSON válido para salvar o corpo.',
                                );
                            }
                        }}
                    >
                        Salvar corpo
                    </Button>
                </div>
            </div>
        </div>
    );
}

function Field({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            {children}
        </div>
    );
}

function stringValue(value: unknown): string {
    return typeof value === 'string' || typeof value === 'number'
        ? String(value)
        : '';
}

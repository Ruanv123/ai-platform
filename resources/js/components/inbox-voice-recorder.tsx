import { type ChangeEvent, useCallback, useRef, useState } from 'react';
import { Mic, Square, Trash2 } from 'lucide-react';
import Recorder from 'opus-recorder';
import encoderWorkerUrl from 'opus-recorder/dist/encoderWorker.min.js?url';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type VoiceMessageRecorderProps = {
    disabled: boolean;
    file: File | null;
    error: string | undefined;
    onFileChange: (file: File | null) => void;
};

const voiceNoteMimeType = 'audio/ogg; codecs=opus';

export function VoiceMessageRecorder({
    disabled,
    file,
    error,
    onFileChange,
}: VoiceMessageRecorderProps) {
    const recorderRef = useRef<Recorder | null>(null);
    const closedRecordersRef = useRef(new WeakSet<Recorder>());
    const [isStarting, setIsStarting] = useState(false);
    const [isRecording, setIsRecording] = useState(false);
    const [recordingError, setRecordingError] = useState<string | null>(null);
    const audioPreviewRef = useCallback((audio: HTMLAudioElement | null) => {
        if (audio === null || file === null) {
            return;
        }

        const previewUrl = URL.createObjectURL(file);
        audio.src = previewUrl;

        return () => URL.revokeObjectURL(previewUrl);
    }, [file]);
    const closeRecorder = useCallback((recorder: Recorder) => {
        if (closedRecordersRef.current.has(recorder)) {
            return;
        }

        closedRecordersRef.current.add(recorder);
        void recorder.close().catch(() => undefined);
    }, []);
    const releaseRecorder = useCallback((recorder: Recorder) => {
        if (recorderRef.current === recorder) {
            recorderRef.current = null;
        }

        void recorder.stop()
            .catch(() => undefined)
            .finally(() => closeRecorder(recorder));
    }, [closeRecorder]);
    const recorderControlRef = useCallback((node: HTMLDivElement | null) => {
        if (node !== null || recorderRef.current === null) {
            return;
        }

        releaseRecorder(recorderRef.current);
    }, [releaseRecorder]);
    const canRecord = typeof window !== 'undefined' && Recorder.isRecordingSupported();

    const startRecording = async () => {
        if (disabled || !canRecord || recorderRef.current !== null) {
            return;
        }

        setRecordingError(null);
        setIsStarting(true);

        let recorder: Recorder;

        try {
            recorder = new Recorder({
                encoderApplication: 2048,
                encoderPath: encoderWorkerUrl,
                numberOfChannels: 1,
            });
        } catch {
            setIsStarting(false);
            setRecordingError('Não foi possível iniciar a gravação. Verifique o suporte do navegador e a conexão segura.');

            return;
        }

        recorderRef.current = recorder;
        recorder.ondataavailable = (data) => {
            if (recorderRef.current !== recorder || data.byteLength === 0) {
                if (data.byteLength === 0 && recorderRef.current === recorder) {
                    setRecordingError('A gravação ficou vazia. Grave novamente ou anexe um arquivo.');
                }

                return;
            }

            const audioBytes = new Uint8Array(data.byteLength);
            audioBytes.set(data);
            onFileChange(new File(
                [audioBytes.buffer],
                `mensagem-de-voz-${Date.now()}.ogg`,
                { type: voiceNoteMimeType },
            ));
        };
        recorder.onstart = () => {
            if (recorderRef.current !== recorder) {
                releaseRecorder(recorder);

                return;
            }

            setIsStarting(false);
            setIsRecording(true);
        };
        recorder.onstop = () => {
            if (recorderRef.current === recorder) {
                recorderRef.current = null;
                setIsStarting(false);
                setIsRecording(false);
            }
        };

        try {
            await recorder.start();
        } catch {
            if (recorderRef.current === recorder) {
                recorderRef.current = null;
                setIsStarting(false);
                setRecordingError('Não foi possível acessar o microfone. Confira a permissão do navegador.');
            }

            closeRecorder(recorder);
        }
    };

    const stopRecording = () => {
        const recorder = recorderRef.current;

        if (recorder === null) {
            return;
        }

        void recorder.stop()
            .catch(() => {
                if (recorderRef.current === recorder) {
                    setRecordingError('Não foi possível finalizar a gravação. Tente novamente.');
                }
            })
            .finally(() => closeRecorder(recorder));
    };

    const selectFile = (event: ChangeEvent<HTMLInputElement>) => {
        onFileChange(event.currentTarget.files?.[0] ?? null);
        event.currentTarget.value = '';
    };

    return (
        <div ref={recorderControlRef} className="grid gap-2">
            <div className="flex flex-wrap items-center gap-2">
                {canRecord ? (
                    <Button
                        type="button"
                        variant={isRecording ? 'destructive' : 'outline'}
                        size="sm"
                        disabled={disabled || isStarting}
                        onClick={isRecording ? stopRecording : startRecording}
                    >
                        {isRecording ? <Square /> : <Mic />}
                        {isStarting ? 'Preparando microfone…' : isRecording ? 'Parar gravação' : 'Gravar áudio'}
                    </Button>
                ) : (
                    <p className="text-muted-foreground text-xs">
                        A gravação precisa de um navegador compatível e uma conexão segura. Você ainda pode anexar um arquivo.
                    </p>
                )}
                <label htmlFor="inbox-audio" className="text-muted-foreground text-xs">
                    Anexar áudio · até 16 MB
                </label>
                <Input
                    id="inbox-audio"
                    type="file"
                    accept=".ogg,.opus,.mp3,.aac,.mp4,.m4a,audio/ogg,audio/mpeg,audio/aac,audio/mp4,video/mp4"
                    onChange={selectFile}
                    disabled={disabled || isStarting || isRecording}
                    className="file:bg-muted file:text-foreground h-auto min-w-0 flex-1 px-3 py-2 text-xs file:mr-3 file:rounded file:border-0 file:px-2 file:py-1"
                />
            </div>
            {file !== null && (
                <div className="flex flex-wrap items-center gap-2">
                    <audio ref={audioPreviewRef} controls preload="metadata" className="h-10 max-w-full flex-1" aria-label="Ouvir áudio antes de enviar" />
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        disabled={disabled || isRecording || isStarting}
                        onClick={() => onFileChange(null)}
                    >
                        <Trash2 />
                        Descartar
                    </Button>
                </div>
            )}
            {recordingError !== null && (
                <p className="text-destructive text-xs" role="alert">{recordingError}</p>
            )}
            {error && <p className="text-destructive text-xs" role="alert">{error}</p>}
        </div>
    );
}

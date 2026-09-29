declare module 'opus-recorder' {
    type RecorderOptions = {
        encoderApplication?: number;
        encoderPath?: string;
        numberOfChannels?: number;
    };

    class Recorder {
        constructor(options?: RecorderOptions);
        static isRecordingSupported(): boolean;
        ondataavailable: (data: Uint8Array) => void;
        onstart: () => void;
        onstop: () => void;
        start(): Promise<void>;
        stop(): Promise<void>;
        close(): Promise<void>;
    }

    export default Recorder;
}

declare module 'opus-recorder/dist/encoderWorker.min.js?url' {
    const workerUrl: string;

    export default workerUrl;
}
